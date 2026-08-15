<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Exception;

class PaymentService
{
    protected $paymentRepository;
    protected $invoiceRepository;
    protected $studentRepository;
    protected $companyRepository;
    protected $accountRepository;
    protected $transactionRepository;
    protected $enterpriseEvent;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        StudentRepositoryInterface $studentRepository,
        CompanyRepositoryInterface $companyRepository,
        AccountRepositoryInterface $accountRepository,
        TransactionRepositoryInterface $transactionRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->studentRepository = $studentRepository;
        $this->companyRepository = $companyRepository;
        $this->accountRepository = $accountRepository;
        $this->transactionRepository = $transactionRepository;
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

    public function getPaymentReceiptData(string $paymentId): array
    {
        $payment = $this->getById($paymentId);
        if (!$payment) {
            throw new Exception("Kuitansi pembayaran #{$paymentId} tidak ditemukan.");
        }

        // IDOR Verification for Student Users
        $user = auth()->user();
        if ($user && ($user->Role ?? '') === 'STUDENT') {
            $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$student || ($payment['Student_ID'] ?? '') !== $student['Student_ID']) {
                throw new Exception("Akses Ditolak: Kuitansi #{$paymentId} bukan milik akun Anda.");
            }
        }

        $invoiceService = app(InvoiceService::class);
        $invoice = null;
        if (!empty($payment['Invoice_ID'])) {
            $invoice = $invoiceService->getById($payment['Invoice_ID']);
        }

        // Customer Lookup
        $customerName = '-';
        $customerCode = '-';
        $customerType = $payment['Payment_Type'] ?? ($invoice['Invoice_Type'] ?? 'STUDENT');

        if ($customerType === 'STUDENT' && !empty($payment['Student_ID'])) {
            $student = $this->studentRepository->findById($payment['Student_ID']);
            $customerName = $student['Full_Name'] ?? $payment['Student_ID'];
            $customerCode = $student['Student_Number'] ?? $payment['Student_ID'];
        } elseif ($customerType === 'COMPANY' && !empty($payment['Company_ID'])) {
            $company = $this->companyRepository->findById($payment['Company_ID']);
            $customerName = $company['Company_Name'] ?? $payment['Company_ID'];
            $customerCode = $company['Company_Code'] ?? $payment['Company_ID'];
        } else {
            $customerName = $payment['Sender_Name'] ?? ($payment['Student_ID'] ?? 'Pelanggan');
            $customerCode = $payment['Student_ID'] ?? $payment['Company_ID'] ?? '-';
        }

        // Receiving Account Lookup
        $receivingAccount = $this->resolvePaymentAccount($payment['Payment_Method'] ?? 'TRANSFER');

        // Financial Balances Breakdown
        $invoiceAmount = (float)($invoice['Amount'] ?? 0);
        $currentPaymentAmount = (float)($payment['Amount_Paid'] ?? 0);
        
        $allPayments = collect($this->paymentRepository->getAll())
            ->where('Invoice_ID', $payment['Invoice_ID'] ?? '')
            ->where('Status', 'Verified');
        
        $totalVerifiedSoFar = (float) $allPayments->sum('Amount_Paid');
        $prevVerified = max(0.0, $totalVerifiedSoFar - $currentPaymentAmount);
        $remainingBalance = max(0.0, $invoiceAmount - $totalVerifiedSoFar);

        // Public Verification URL
        $verificationUrl = route('payments.verify-receipt-public', $paymentId);

        // QR Code SVG
        $qrCodeSvg = null;
        if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
            try {
                $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(90)->margin(1)->generate($verificationUrl);
            } catch (\Exception $e) {
                $qrCodeSvg = null;
            }
        }

        return [
            'company' => [
                'name' => 'WAKAMIYA MANAGEMENT SYSTEM',
                'tagline' => 'Enterprise ERP & Educational Management System',
                'address' => 'Jl. Raya Wakamiya No. 88, Jakarta Selatan 12930',
                'contact' => 'Telp: (021) 8000-9999 | Email: finance@wakamiya.ac.id',
                'website' => 'https://wakamiya.ac.id'
            ],
            'payment' => $payment,
            'invoice' => $invoice,
            'customer' => [
                'type' => $customerType,
                'name' => $customerName,
                'code' => $customerCode
            ],
            'receivingAccount' => $receivingAccount,
            'balances' => [
                'invoiceAmount' => $invoiceAmount,
                'prevVerified' => $prevVerified,
                'currentPayment' => $currentPaymentAmount,
                'remainingBalance' => $remainingBalance
            ],
            'verificationUrl' => $verificationUrl,
            'qrCodeSvg' => $qrCodeSvg
        ];
    }

    public function generateReceiptNumber($type = 'STUDENT')
    {
        $prefix = $type === 'COMPANY' ? 'RCT-CORP' : 'RCT-STU';
        $year = date('Y');
        
        $counterKey = 'receipt_counter_' . $prefix . '_' . $year;
        $lockKey = 'receipt_write_lock';

        return Cache::lock($lockKey, 10)->block(5, function () use ($prefix, $year, $counterKey) {
            if (!Cache::has($counterKey)) {
                $all = $this->paymentRepository->getAll();
                $count = collect($all)->filter(function($item) use ($prefix, $year) {
                    return str_starts_with($item['Payment_ID'] ?? '', "{$prefix}-{$year}-");
                })->count();
                Cache::forever($counterKey, $count);
            }
            
            $nextNumber = Cache::increment($counterKey);
            return sprintf("%s-%s-%06d", $prefix, $year, $nextNumber);
        });
    }

    public function resolvePaymentAccount(string $paymentMethod = 'TRANSFER', ?string $explicitAccountId = null): string
    {
        $allAccounts = collect($this->accountRepository->fetchAll())->where('Is_Active', '!=', 'FALSE');
        
        if (!empty($explicitAccountId)) {
            $matched = $allAccounts->firstWhere('Account_ID', $explicitAccountId) ?? $allAccounts->firstWhere('Account_Code', $explicitAccountId);
            if ($matched) {
                return $matched['Account_Code'] ?? $matched['Account_ID'];
            }
        }

        $assets = $allAccounts->filter(function($acc) {
            $cat = strtoupper($acc['Account_Category'] ?? '');
            return str_contains($cat, 'ASSET') || str_contains($cat, 'ASET');
        });

        $methodUpper = strtoupper(trim($paymentMethod));
        
        if ($methodUpper === 'CASH' || $methodUpper === 'TUNAI') {
            $cashAcc = $assets->first(function($acc) {
                $name = strtolower($acc['Account_Name'] ?? '');
                $code = $acc['Account_Code'] ?? '';
                return str_contains($name, 'kas') || str_contains($name, 'cash') || $code === '101';
            });
            if ($cashAcc) return $cashAcc['Account_Code'] ?? $cashAcc['Account_ID'];
        } else {
            $bankAcc = $assets->first(function($acc) {
                $name = strtolower($acc['Account_Name'] ?? '');
                $code = $acc['Account_Code'] ?? '';
                return str_contains($name, 'bank') || str_contains($name, 'bsi') || $code === '102';
            });
            if ($bankAcc) return $bankAcc['Account_Code'] ?? $bankAcc['Account_ID'];
        }

        $fallbackAsset = $assets->first();
        if ($fallbackAsset) {
            return $fallbackAsset['Account_Code'] ?? $fallbackAsset['Account_ID'];
        }

        return ($methodUpper === 'CASH' || $methodUpper === 'TUNAI') ? '101' : '102';
    }

    public function submitPayment(array $data)
    {
        $invoiceId = $data['Invoice_ID'] ?? null;
        if (empty($invoiceId)) {
            throw new Exception("ID Tagihan (Invoice_ID) wajib diisi.");
        }

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->getById($invoiceId);
        if (!$invoice || ($invoice['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Tagihan #{$invoiceId} tidak ditemukan atau sedang tidak aktif.");
        }

        // 1. IDOR Ownership Verification for STUDENT role
        $user = auth()->user();
        if ($user && ($user->Role ?? '') === 'STUDENT') {
            $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$student || ($invoice['Student_ID'] ?? '') !== $student['Student_ID']) {
                throw new Exception("Akses Ditolak: Tagihan #{$invoiceId} bukan milik akun Anda.");
            }
            $data['Student_ID'] = $student['Student_ID'];
        } else {
            $data['Student_ID'] = $data['Student_ID'] ?? $invoice['Student_ID'] ?? null;
        }

        // 2. Invoice State Protection
        $invoiceStatus = $invoice['Status'] ?? 'Draft';
        if (strcasecmp($invoiceStatus, 'Draft') === 0) {
            throw new Exception("Tagihan #{$invoiceId} masih berstatus Draft dan belum diterbitkan.");
        }
        if (strcasecmp($invoiceStatus, 'Cancelled') === 0) {
            throw new Exception("Tagihan #{$invoiceId} telah Dibatalkan dan tidak dapat menerima pembayaran.");
        }
        if (strcasecmp($invoiceStatus, 'Paid') === 0) {
            throw new Exception("Tagihan #{$invoiceId} telah Lunas (PAID) dan tidak dapat menerima pembayaran lagi.");
        }

        // 3. Overpayment Guard
        $remainingAmount = $invoiceService->calculateRemainingAmount($invoice);
        if ($remainingAmount <= 0) {
            throw new Exception("Tagihan #{$invoiceId} tidak memiliki sisa piutang (Sisa Rp 0).");
        }

        $amountPaid = (float) ($data['Amount_Paid'] ?? 0);
        if ($amountPaid <= 0) {
            throw new Exception("Nominal pembayaran harus lebih besar dari Rp 0.");
        }
        if ($amountPaid > $remainingAmount) {
            throw new Exception("Nominal pembayaran (Rp " . number_format($amountPaid, 0, ',', '.') . ") melebihi sisa tagihan (Rp " . number_format($remainingAmount, 0, ',', '.') . ").");
        }

        // 4. Duplicate Payment Protection (Prevent parallel submissions while waiting verification)
        $allPayments = $this->paymentRepository->getAll();
        $pendingPayment = collect($allPayments)
            ->where('Invoice_ID', $invoiceId)
            ->where('Status', 'Waiting Verification')
            ->first();

        if ($pendingPayment) {
            throw new Exception("Pembayaran untuk tagihan #{$invoiceId} sedang menunggu verifikasi dari bagian Keuangan. Harap tunggu hingga proses verifikasi selesai.");
        }

        // Assign default receipt & metadata
        $data['Payment_Type'] = $data['Payment_Type'] ?? ($invoice['Invoice_Type'] ?? 'STUDENT');
        if (empty($data['Payment_ID'])) {
            $data['Payment_ID'] = $this->generateReceiptNumber($data['Payment_Type']);
        }
        
        $data['Status'] = 'Waiting Verification';
        $data['Payment_Date'] = $data['Payment_Date'] ?? now()->toDateString();
        $data['Created_At'] = now()->toDateTimeString();
        $data['Is_Active'] = 'TRUE';

        $res = $this->paymentRepository->create($data);
        $this->paymentRepository->clearCache();
        
        if (!empty($data['Student_ID'])) {
            Cache::forget("student_billing_{$data['Student_ID']}");
        }
        Cache::forget('finance_dashboard');

        $this->enterpriseEvent->dispatch(
            'FINANCE', 
            'CREATE', 
            'PAYMENT', 
            $res['Payment_ID'] ?? $data['Payment_ID'], 
            auth()->id() ?? 'SYSTEM', 
            ['FINANCE'], 
            !empty($data['Student_ID']) ? [$data['Student_ID']] : [], 
            $data
        );
        
        return $res;
    }

    public function verifyPayment($paymentId, $verifiedBy, $status, $notes = '', $explicitAccountId = null)
    {
        $lockKey = "payment_verify_{$paymentId}";
        
        return Cache::lock($lockKey, 10)->block(5, function () use ($paymentId, $verifiedBy, $status, $notes, $explicitAccountId) {
            $payment = $this->getById($paymentId);
            if (!$payment) {
                throw new Exception("Payment #{$paymentId} tidak ditemukan.");
            }

            if (($payment['Status'] ?? '') === $status) {
                return $payment;
            }

            $data = [
                'Status' => $status,
                'Verified_By' => $verifiedBy,
                'Verified_At' => now()->toDateTimeString(),
                'Notes' => $notes,
                'Updated_At' => now()->toDateTimeString()
            ];

            $res = $this->paymentRepository->update($paymentId, $data);
            $this->paymentRepository->clearCache();

            if (!empty($payment['Student_ID'])) {
                Cache::forget("student_billing_{$payment['Student_ID']}");
            }
            Cache::forget('finance_dashboard');

            if ($status === 'Verified') {
                $invoiceId = $payment['Invoice_ID'] ?? null;
                if ($invoiceId) {
                    $invoiceService = app(InvoiceService::class);
                    $invoice = $invoiceService->getById($invoiceId);
                    
                    if ($invoice) {
                        $verifiedPayments = $this->getVerifiedPaymentTotalForInvoice($invoiceId);
                        $invoiceAmount = (float)($invoice['Amount'] ?? 0);
                        $invStatus = ($verifiedPayments >= $invoiceAmount) ? 'Paid' : 'Partial Paid';
                        
                        $this->invoiceRepository->update($invoiceId, [
                            'Status' => $invStatus, 
                            'Updated_At' => now()->toDateTimeString()
                        ]);
                        $this->invoiceRepository->clearCache();

                        $existingTrans = collect($this->transactionRepository->fetchAll())
                            ->where('Reference_Type', 'Payment')
                            ->where('Reference_ID', $paymentId)
                            ->first();

                        if (!$existingTrans) {
                            $targetAccountId = $this->resolvePaymentAccount($payment['Payment_Method'] ?? 'TRANSFER', $explicitAccountId);
                            $transService = app(TransactionService::class);
                            $transService->create([
                                'Transaction_Date' => now()->format('Y-m-d'),
                                'Account_ID' => $targetAccountId,
                                'Type' => 'Income',
                                'Category' => 'Payment Receipt',
                                'Amount' => (float) ($payment['Amount_Paid'] ?? 0),
                                'Reference_Type' => 'Payment',
                                'Reference_ID' => $paymentId,
                                'Description' => "Pembayaran Verifikasi Kuitansi #{$paymentId} untuk Invoice #{$invoiceId}"
                            ]);
                        }
                    }
                }

                $this->enterpriseEvent->dispatch(
                    'FINANCE', 
                    'VERIFY', 
                    'PAYMENT', 
                    $paymentId, 
                    auth()->id() ?? 'SYSTEM', 
                    ['STUDENT'], 
                    !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [], 
                    ['Status' => $status, 'Notes' => $notes]
                );
            } elseif (in_array($status, ['Rejected', 'Need Revision'])) {
                $this->enterpriseEvent->dispatch(
                    'FINANCE', 
                    'UPDATE', 
                    'PAYMENT', 
                    $paymentId, 
                    auth()->id() ?? 'SYSTEM', 
                    ['STUDENT'], 
                    !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [], 
                    ['Status' => $status, 'Notes' => $notes]
                );
            }

            return $res;
        });
    }

    protected function getVerifiedPaymentTotalForInvoice(string $invoiceId): float
    {
        $all = $this->paymentRepository->getAll();
        return (float) collect($all)
            ->where('Invoice_ID', $invoiceId)
            ->where('Status', 'Verified')
            ->sum('Amount_Paid');
    }

    public function deletePayment($paymentId)
    {
        $payment = $this->getById($paymentId);
        if (!$payment) {
            throw new Exception("Payment not found");
        }

        $this->paymentRepository->delete($paymentId);
        $this->paymentRepository->clearCache();
        
        if (!empty($payment['Student_ID'])) {
            Cache::forget("student_billing_{$payment['Student_ID']}");
        }
        Cache::forget('finance_dashboard');

        $this->enterpriseEvent->dispatch(
            'FINANCE',
            'DELETE',
            'PAYMENT',
            $paymentId,
            auth()->id() ?? 'SYSTEM',
            ['FINANCE'],
            !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [],
            []
        );
        
        return true;
    }
}