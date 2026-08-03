<?php
namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class PaymentService
{
    protected $paymentRepository;
    protected $invoiceRepository;
    protected $studentRepository;
    protected $companyRepository;
    protected $enterpriseEvent;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        StudentRepositoryInterface $studentRepository,
        CompanyRepositoryInterface $companyRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->studentRepository = $studentRepository;
        $this->companyRepository = $companyRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAll() 
    { 
        $payments = collect($this->paymentRepository->getAll())->where('Is_Active', '!=', 'FALSE')->values();
        $user = auth()->user();
        
        if ($user && ($user->Role ?? '') === 'STUDENT') {
            $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($student) {
                return $payments->where('Student_ID', $student['Student_ID'])->values();
            }
            return collect();
        }
        
        return $payments;
    }

    public function getById($id) { 
        return $this->paymentRepository->getById($id); 
    }

    public function generateReceiptNumber($type = 'STUDENT')
    {
        $prefix = $type === 'COMPANY' ? 'RCT-CORP' : 'RCT-STU';
        $year = date('Y');
        
        $counterKey = 'receipt_counter_' . $prefix . '_' . $year;
        $lockKey = 'receipt_write_lock';

        return \Illuminate\Support\Facades\Cache::lock($lockKey, 10)->block(5, function () use ($prefix, $year, $counterKey) {
            if (!\Illuminate\Support\Facades\Cache::has($counterKey)) {
                $all = $this->paymentRepository->getAll();
                $count = collect($all)->filter(function($item) use ($prefix, $year) {
                    return str_starts_with($item['Payment_ID'] ?? '', "{$prefix}-{$year}-");
                })->count();
                \Illuminate\Support\Facades\Cache::forever($counterKey, $count);
            }
            
            $nextNumber = \Illuminate\Support\Facades\Cache::increment($counterKey);
            return sprintf("%s-%s-%06d", $prefix, $year, $nextNumber);
        });
    }

    public function submitPayment(array $data)
    {
        $data['Payment_Type'] = $data['Payment_Type'] ?? 'STUDENT';
        
        if (empty($data['Payment_ID'])) {
            $data['Payment_ID'] = $this->generateReceiptNumber($data['Payment_Type']);
        }
        
        $data['Status'] = 'Waiting Verification';
        $data['Payment_Date'] = now()->toDateString();
        $data['Created_At'] = now()->toDateTimeString();
        $data['Is_Active'] = 'TRUE';

        $res = $this->paymentRepository->create($data);
        $this->paymentRepository->clearCache();
        
        if (!empty($data['Student_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("student_billing_{$data['Student_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('finance_dashboard');

        // Notification Prep: Notify Finance
        $this->enterpriseEvent->dispatch('FINANCE', 'CREATE', 'PAYMENT', $res['Payment_ID'] ?? $data['Payment_ID'], auth()->id() ?? 'SYSTEM', ['FINANCE'], [], $data);
        
        return $res;
    }

    public function verifyPayment($paymentId, $verifiedBy, $status, $notes = '')
    {
        $payment = $this->getById($paymentId);
        if (!$payment) throw new Exception("Payment not found");

        $data = [
            'Status' => $status,
            'Verified_By' => $verifiedBy,
            'Verified_At' => now()->toDateTimeString(),
            'Notes' => $notes,
            'Updated_At' => now()->toDateTimeString()
        ];

        $res = $this->paymentRepository->update($paymentId, $data);
        $this->paymentRepository->clearCache();

        if (isset($payment['Student_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("student_billing_{$payment['Student_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('finance_dashboard');

        if ($status == 'Verified') {
            // Update Invoice Status with Multi-Payment Logic
            $invoiceId = $payment['Invoice_ID'] ?? null;
            if ($invoiceId) {
                $invoice = $this->invoiceRepository->getById($invoiceId);
                if ($invoice) {
                    $verifiedPayments = collect($this->paymentRepository->getAll())
                        ->where('Invoice_ID', $invoiceId)
                        ->where('Status', 'Verified')
                        ->sum('Amount_Paid');
                    
                    $invoiceAmount = (float)($invoice['Amount'] ?? 0);
                    $invStatus = ($verifiedPayments >= $invoiceAmount) ? 'Paid' : 'Partial Paid';
                    
                    $this->invoiceRepository->update($invoiceId, ['Status' => $invStatus, 'Updated_At' => now()->toDateTimeString()]);
                    $this->invoiceRepository->clearCache();
                    
                    // Trigger Automation for Finance Ledger Logging
                    try {
                        // 10.4D Final Audit: Insert Transaction
                        $transSvc = app(\App\Services\Finance\TransactionService::class);
                        $transSvc->create([
                            'Transaction_Date' => now()->format('Y-m-d'),
                            'Account_ID' => 'ACC-DEFAULT', // Ideally should be selected from payment method
                            'Type' => 'Income',
                            'Category' => 'Payment Receipt',
                            'Amount' => $payment['Amount_Paid'],
                            'Reference_Type' => 'Payment',
                            'Reference_ID' => $paymentId,
                            'Description' => "Payment Verified for Invoice " . $invoiceId
                        ]);

                        app(\App\Services\Core\EnterpriseAutomationService::class)->writeAudit('Finance', 'Payment_Verified', 'Finance_Ledger', $paymentId, 'Waiting Verification', 'Verified');
                        app(\App\Services\Core\EnterpriseAutomationService::class)->paymentVerified($payment);
                        
                        // Phase 10.5: Generate Receipt PDF
                        try {
                            $student = [];
                            $company = [];
                            if (!empty($payment['Student_ID'])) {
                                $student = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->findById($payment['Student_ID']) ?? [];
                            }
                            if (!empty($payment['Company_ID'])) {
                                $company = app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class)->findById($payment['Company_ID']) ?? [];
                            }
                            
                            $docAutomation = app(\App\Services\Core\DocumentAutomationService::class);
                            $docAutomation->generateDocument(
                                'Receipt',
                                'Payment',
                                $paymentId,
                                ['payment' => $payment, 'student' => $student, 'company' => $company],
                                'pdf.receipt',
                                auth()->user()->email ?? 'System'
                            );
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Failed to generate PDF Receipt for Payment {$paymentId}: " . $e->getMessage());
                        }

                    } catch(\Exception $e) {}
                }
            }

            // Notification Prep
            if (($payment['Payment_Type'] ?? 'STUDENT') === 'STUDENT' && !empty($payment['Student_ID'])) {
                $this->enterpriseEvent->dispatch('FINANCE', 'VERIFY', 'PAYMENT', $paymentId, auth()->id() ?? 'SYSTEM', ['STUDENT'], [$payment['Student_ID']], ['Status' => $status, 'Notes' => $notes]);
            }
        } elseif (in_array($status, ['Rejected', 'Need Revision'])) {
            if (($payment['Payment_Type'] ?? 'STUDENT') === 'STUDENT' && !empty($payment['Student_ID'])) {
                $this->enterpriseEvent->dispatch('FINANCE', 'UPDATE', 'PAYMENT', $paymentId, auth()->id() ?? 'SYSTEM', ['STUDENT'], [$payment['Student_ID']], ['Status' => $status, 'Notes' => $notes]);
            }
        }

        return $res;
    }

    public function deletePayment($paymentId)
    {
        $payment = $this->getById($paymentId);
        if (!$payment) {
            throw new Exception("Payment not found");
        }

        // Hard delete
        $this->paymentRepository->delete($paymentId);
        $this->paymentRepository->clearCache();
        
        if (!empty($payment['Student_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("student_billing_{$payment['Student_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('finance_dashboard');
        
        return true;
    }
}