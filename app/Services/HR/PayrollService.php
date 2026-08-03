<?php
namespace App\Services\HR;

use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class PayrollService
{
    protected $payrollRepo;
    protected $salaryRepo;
    protected $employeeRepo;
    protected $enterpriseEvent;

    public function __construct(
        PayrollRepositoryInterface $payrollRepo,
        SalaryComponentRepositoryInterface $salaryRepo,
        EmployeeRepositoryInterface $employeeRepo,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->payrollRepo = $payrollRepo;
        $this->salaryRepo = $salaryRepo;
        $this->employeeRepo = $employeeRepo;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAll() { return $this->payrollRepo->getAll(); }
    public function getById($id) { return $this->payrollRepo->getById($id); }
    public function delete($id) { 
        $res = $this->payrollRepo->delete($id); 
        $this->payrollRepo->clearCache();
        return $res;
    }

    public function GeneratePayrollNumber()
    {
        $year = date('Y');
        $counterKey = 'payroll_counter_' . $year;
        
        return \Illuminate\Support\Facades\Cache::lock('payroll_write_lock', 10)->block(5, function () use ($year, $counterKey) {
            if (!\Illuminate\Support\Facades\Cache::has($counterKey)) {
                $count = $this->payrollRepo->getAll()->filter(function($item) use ($year) {
                    return str_starts_with($item['Payroll_Number'] ?? '', "PAY-{$year}-");
                })->count();
                \Illuminate\Support\Facades\Cache::forever($counterKey, $count);
            }
            $nextNumber = \Illuminate\Support\Facades\Cache::increment($counterKey);
            return sprintf("PAY-%s-%06d", $year, $nextNumber);
        });
    }

    public function GenerateDocumentNumber()
    {
        $year = date('Y');
        $counterKey = 'doc_payroll_counter_' . $year;
        
        return \Illuminate\Support\Facades\Cache::lock('doc_payroll_write_lock', 10)->block(5, function () use ($year, $counterKey) {
            if (!\Illuminate\Support\Facades\Cache::has($counterKey)) {
                $count = $this->payrollRepo->getAll()->filter(function($item) use ($year) {
                    return str_starts_with($item['Document_Number'] ?? '', "DOC-PAY-{$year}-");
                })->count();
                \Illuminate\Support\Facades\Cache::forever($counterKey, $count);
            }
            $nextNumber = \Illuminate\Support\Facades\Cache::increment($counterKey);
            return sprintf("DOC-PAY-%s-%06d", $year, $nextNumber);
        });
    }

    public function CalculateBasicSalary($employeeId) { return 0; /* Fetched from employee data if implemented */ }
    public function CalculateAllowance($employeeId) { return 0; }
    public function CalculateBonus($employeeId) { return 0; }
    public function CalculateOvertime($employeeId) { return 0; }
    public function CalculateDeduction($employeeId) { return 0; }
    public function CalculateTax($grossSalary) { return $grossSalary * 0.05; } // Stub 5%
    public function CalculateBPJS($grossSalary) { return $grossSalary * 0.02; } // Stub 2%
    
    public function CalculateNetSalary(array $data)
    {
        // Simplified based on user request to only input Total Gaji (Net Salary)
        $net = floatval($data['Net_Salary'] ?? 0);
        return [
            'Gross' => $net,
            'Tax' => 0,
            'BPJS' => 0,
            'Net_Salary' => $net
        ];
    }

    public function GenerateSalarySlip($payrollId)
    {
        return sprintf("SLIP-%s-%06d", date('Y'), rand(1,999999));
    }

    public function PrepareDocument($payrollId)
    {
        return [
            'Generated_Document' => 'TRUE',
            'Document_Number' => $this->GenerateDocumentNumber()
        ];
    }

    public function PrepareNotification($payrollId, $status)
    {
        $this->enterpriseEvent->dispatch('HR', 'UPDATE', 'PAYROLL', $payrollId, auth()->id() ?? 'SYSTEM', ['HR', 'EMPLOYEE'], [], ['Status' => $status]);
        return true;
    }

    public function processPayroll(array $data)
    {
        if (isset($data['Employee_ID'])) {
            $employee = $this->employeeRepo->findById($data['Employee_ID']);
            if (!$employee || ($employee['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Pegawai tidak valid atau sedang tidak aktif.");
            }
        }

        $data['Payroll_ID'] = uniqid('PRL_');
        
        if (empty($data['Payroll_Number'])) {
            $data['Payroll_Number'] = $this->GeneratePayrollNumber();
        } else {
            $existing = $this->payrollRepo->getAll()->firstWhere('Payroll_Number', $data['Payroll_Number']);
            if ($existing) {
                throw new Exception("Payroll Number {$data['Payroll_Number']} sudah terdaftar.");
            }
        }
        
        $data['Status'] = 'Draft';
        $data['Created_At'] = now()->toDateTimeString();
        
        $calculation = $this->CalculateNetSalary($data);
        $data['Tax'] = $calculation['Tax'];
        $data['BPJS'] = $calculation['BPJS'];
        $data['Net_Salary'] = $calculation['Net_Salary'];

        $docData = $this->PrepareDocument($data['Payroll_ID']);
        $data['Generated_Document'] = $docData['Generated_Document'];
        $data['Document_Number'] = $docData['Document_Number'];

        $res = $this->payrollRepo->create($data);
        $this->payrollRepo->clearCache();

        $this->PrepareNotification($data['Payroll_ID'], 'Payroll Generated');

        return $res;
    }

    public function updateStatus($id, $status, $user, $paymentProof = null, $notes = null)
    {
        $data = [
            'Status' => $status,
            'Updated_At' => now()->toDateTimeString()
        ];
        if ($status == 'Approved') {
            $data['Approved_By'] = $user;
            $data['Approved_Date'] = now()->toDateTimeString();
            $this->PrepareNotification($id, 'Payroll Approved');
        } elseif ($status == 'Paid') {
            $data['Paid_Date'] = now()->toDateTimeString();
            if ($paymentProof) {
                $data['Payment_Proof'] = $paymentProof;
            }
            if ($notes) {
                $data['Notes'] = $notes;
            }
            $this->PrepareNotification($id, 'Payroll Paid');
            
            // 10.4D Final Audit: Insert Transaction
            try {
                $payroll = $this->getById($id);
                if ($payroll) {
                    $transSvc = app(\App\Services\Finance\TransactionService::class);
                    $transSvc->create([
                        'Transaction_Date' => now()->format('Y-m-d'),
                        'Account_ID' => 'ACC-DEFAULT',
                        'Type' => 'Expense',
                        'Category' => 'Salary',
                        'Amount' => $payroll['Net_Salary'] ?? 0,
                        'Reference_Type' => 'Payroll',
                        'Reference_ID' => $id,
                        'Description' => "Payroll Paid for Employee " . ($payroll['Employee_ID'] ?? '-')
                    ]);
                }
            } catch(\Exception $e) {}
            
            // Phase 10.5: Generate Payroll Slip PDF
            try {
                $payroll = $this->getById($id) ?? [];
                $employee = [];
                if (!empty($payroll['Employee_ID'])) {
                    $employee = $this->employeeRepo->findById($payroll['Employee_ID']) ?? [];
                }
                
                $docAutomation = app(\App\Services\Core\DocumentAutomationService::class);
                $docAutomation->generateDocument(
                    'Payroll',
                    'Payroll',
                    $id,
                    ['payroll' => $payroll, 'employee' => $employee],
                    'pdf.payroll',
                    auth()->user()->email ?? 'System'
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to generate PDF Payroll Slip for Payroll {$id}: " . $e->getMessage());
            }
        }

        $res = $this->payrollRepo->update($id, $data);
        $this->payrollRepo->clearCache();
        return $res;
    }
}