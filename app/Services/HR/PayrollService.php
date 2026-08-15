<?php

namespace App\Services\HR;

use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Exception;

class PayrollService
{
    protected $payrollRepo;
    protected $salaryRepo;
    protected $employeeRepo;
    protected $enterpriseEvent;
    protected $calculationEngine;

    public function __construct(
        PayrollRepositoryInterface $payrollRepo,
        SalaryComponentRepositoryInterface $salaryRepo,
        EmployeeRepositoryInterface $employeeRepo,
        EnterpriseEventService $enterpriseEvent,
        PayrollCalculationEngine $calculationEngine
    ) {
        $this->payrollRepo = $payrollRepo;
        $this->salaryRepo = $salaryRepo;
        $this->employeeRepo = $employeeRepo;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->calculationEngine = $calculationEngine;
    }

    public function getAll() { return $this->payrollRepo->getAll(); }
    public function getById($id) { return $this->payrollRepo->getById($id); }

    public function GeneratePayrollNumber()
    {
        $year = date('Y');
        $counterKey = 'payroll_counter_' . $year;
        
        return Cache::lock('payroll_write_lock', 10)->block(5, function () use ($year, $counterKey) {
            if (!Cache::has($counterKey)) {
                $count = $this->payrollRepo->getAll()->filter(function($item) use ($year) {
                    return str_starts_with($item['Payroll_Number'] ?? '', "PAY-{$year}-");
                })->count();
                Cache::forever($counterKey, $count);
            }
            $nextNumber = Cache::increment($counterKey);
            return sprintf("PAY-%s-%06d", $year, $nextNumber);
        });
    }

    public function GenerateDocumentNumber()
    {
        $year = date('Y');
        $counterKey = 'doc_payroll_counter_' . $year;
        
        return Cache::lock('doc_payroll_write_lock', 10)->block(5, function () use ($year, $counterKey) {
            if (!Cache::has($counterKey)) {
                $count = $this->payrollRepo->getAll()->filter(function($item) use ($year) {
                    return str_starts_with($item['Document_Number'] ?? '', "DOC-PAY-{$year}-");
                })->count();
                Cache::forever($counterKey, $count);
            }
            $nextNumber = Cache::increment($counterKey);
            return sprintf("DOC-PAY-%s-%06d", $year, $nextNumber);
        });
    }

    public function processPayroll(array $data)
    {
        $employeeId = $data['Employee_ID'] ?? null;
        if (!$employeeId) {
            throw new Exception("Employee ID wajib diisi.");
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee || strtoupper(trim($employee['Is_Active'] ?? 'TRUE')) === 'FALSE') {
            throw new Exception("Pegawai tidak valid atau sedang tidak aktif.");
        }

        $period = $data['Payroll_Period'] ?? date('Y-m');

        // Atomic Concurrency Lock & Idempotency Check
        $lockKey = "payroll_{$employeeId}_{$period}";
        return Cache::lock($lockKey, 10)->block(3, function () use ($data, $employeeId, $period) {
            $existing = $this->payrollRepo->findByEmployeeAndPeriod($employeeId, $period);
            if ($existing) {
                throw new Exception("Penggajian (Payroll) untuk pegawai ini pada periode {$period} sudah terdaftar.");
            }

            // 100% Server-side Deterministic Calculation Engine
            $calc = $this->calculationEngine->calculate($employeeId, $period, $data);

            $payrollId = uniqid('PRL_');
            $payrollNumber = $this->GeneratePayrollNumber();
            $docNumber = $this->GenerateDocumentNumber();

            $record = [
                'Payroll_ID' => $payrollId,
                'Payroll_Number' => $payrollNumber,
                'Document_Number' => $docNumber,
                'Employee_ID' => $employeeId,
                'Payroll_Period' => $period,
                'Base_Salary' => $calc['base_salary'],
                'Total_Allowances' => $calc['allowances'] + $calc['bonus'] + $calc['overtime'],
                'Total_Deductions' => $calc['total_deduction'],
                'Tax' => $calc['tax'],
                'BPJS' => $calc['bpjs'],
                'Net_Salary' => $calc['net_salary'],
                'Status' => 'Draft',
                'Generated_Document' => 'TRUE',
                'Created_At' => now()->toDateTimeString(),
                'Payroll_Details' => json_encode($calc)
            ];

            $res = $this->payrollRepo->create($record);
            $this->payrollRepo->clearCache();

            Cache::forget("employee_payroll_{$employeeId}_{$period}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR', 
                'CREATE', 
                'PAYROLL', 
                $payrollId, 
                auth()->id() ?? 'SYSTEM', 
                ['HR', 'FINANCE'], 
                [$employeeId], 
                ['Period' => $period, 'Net_Salary' => $calc['net_salary']]
            );

            return $res;
        });
    }

    public function generateBatchPayroll(string $period): array
    {
        $lockKey = "payroll_batch_{$period}";
        return Cache::lock($lockKey, 15)->block(5, function () use ($period) {
            $allEmployees = collect($this->employeeRepo->fetchAll())
                ->filter(function ($emp) {
                    return strtoupper(trim($emp['Is_Active'] ?? 'TRUE')) !== 'FALSE';
                });

            $generated = [];
            foreach ($allEmployees as $emp) {
                $empId = $emp['Employee_ID'];
                $existing = $this->payrollRepo->findByEmployeeAndPeriod($empId, $period);
                if (!$existing) {
                    try {
                        $res = $this->processPayroll([
                            'Employee_ID' => $empId,
                            'Payroll_Period' => $period
                        ]);
                        $generated[] = $res;
                    } catch (\Exception $e) {
                        // Skip individual errors in batch
                    }
                }
            }

            return $generated;
        });
    }

    public function updateStatus($id, $status, $user, $paymentProof = null, $notes = null)
    {
        $payroll = $this->getById($id);
        if (!$payroll) {
            throw new Exception("Data Payroll #{$id} tidak ditemukan.");
        }

        $currentStatus = trim($payroll['Status'] ?? 'Draft');

        // State Machine Validations
        if (in_array(strtolower($currentStatus), ['closed', 'paid']) && strtolower($status) !== 'closed') {
            throw new Exception("Payroll yang sudah lunas (Paid) atau ditutup (Closed) tidak dapat diubah statusnya.");
        }

        $data = [
            'Status' => $status,
            'Updated_At' => now()->toDateTimeString()
        ];

        if ($status === 'Approved') {
            $data['Approved_By'] = $user;
            $data['Approved_Date'] = now()->toDateTimeString();
            
            $this->enterpriseEvent->dispatch('HR', 'UPDATE', 'PAYROLL', $id, auth()->id() ?? 'SYSTEM', ['HR', 'FINANCE'], [], ['Status' => 'Approved']);
        } elseif ($status === 'Paid') {
            $data['Paid_Date'] = now()->toDateTimeString();
            if ($paymentProof) $data['Payment_Proof'] = $paymentProof;
            if ($notes) $data['Notes'] = $notes;

            // Idempotent Finance Ledger Integration (Phase D Integration)
            try {
                $transRepo = app(\App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class);
                $allTrans = collect($transRepo->fetchAll());
                
                $existingTrans = $allTrans->first(function ($t) use ($id) {
                    return ($t['Reference_Type'] ?? '') === 'Payroll' && 
                           ($t['Reference_ID'] ?? '') === $id &&
                           strtoupper(trim($t['Is_Active'] ?? 'TRUE')) !== 'FALSE';
                });

                if (!$existingTrans) {
                    // Resolve payment COA account
                    $paymentSvc = app(\App\Services\Finance\PaymentService::class);
                    $paymentAccount = method_exists($paymentSvc, 'resolvePaymentAccount') 
                        ? $paymentSvc->resolvePaymentAccount('Bank Transfer')
                        : '102';

                    $transSvc = app(\App\Services\Finance\TransactionService::class);
                    $transSvc->create([
                        'Transaction_Date' => now()->format('Y-m-d'),
                        'Account_ID' => $paymentAccount,
                        'Type' => 'Expense',
                        'Category' => 'Gaji & Inisiatif',
                        'Amount' => (float)($payroll['Net_Salary'] ?? 0),
                        'Reference_Type' => 'Payroll',
                        'Reference_ID' => $id,
                        'Description' => "Pembayaran Gaji Pegawai #{$payroll['Employee_ID']} Periode " . ($payroll['Payroll_Period'] ?? '-')
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Phase D Ledger Integration Error for Payroll {$id}: " . $e->getMessage());
            }

            // Generate Official PDF Payslip
            try {
                $docAutomation = app(\App\Services\Core\DocumentAutomationService::class);
                $employee = $this->employeeRepo->findById($payroll['Employee_ID'] ?? '') ?? [];
                
                $docAutomation->generateDocument(
                    'Payroll',
                    'Payroll',
                    $id,
                    ['payroll' => $payroll, 'employee' => $employee],
                    'pdf.official_payslip',
                    auth()->user()->email ?? 'System'
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to generate PDF Payslip for Payroll {$id}: " . $e->getMessage());
            }

            $this->enterpriseEvent->dispatch('HR', 'UPDATE', 'PAYROLL', $id, auth()->id() ?? 'SYSTEM', ['HR', 'FINANCE', 'EMPLOYEE'], [$payroll['Employee_ID'] ?? ''], ['Status' => 'Paid']);
        } elseif ($status === 'Rejected') {
            $this->enterpriseEvent->dispatch('HR', 'UPDATE', 'PAYROLL', $id, auth()->id() ?? 'SYSTEM', ['HR'], [], ['Status' => 'Rejected']);
        } else {
            $this->enterpriseEvent->dispatch('HR', 'UPDATE', 'PAYROLL', $id, auth()->id() ?? 'SYSTEM', ['HR'], [], ['Status' => $status]);
        }

        $res = $this->payrollRepo->update($id, $data);
        $this->payrollRepo->clearCache();
        Cache::forget('hr_dashboard');
        Cache::forget('finance_dashboard');

        return $res;
    }

    public function getPayslipDocumentData(string $payrollId): array
    {
        $payroll = $this->getById($payrollId);
        if (!$payroll) {
            throw new Exception("Dokumen Slip Gaji #{$payrollId} tidak ditemukan.");
        }

        // Server-Side IDOR Security Validation for Employee Users
        $user = auth()->user();
        if ($user && ($user->Role ?? '') === 'STUDENT') {
            throw new Exception("Akses Ditolak: Siswa tidak diizinkan mengakses data penggajian.");
        }

        if ($user && in_array(strtoupper($user->Role ?? ''), ['TEACHER', 'EMPLOYEE'])) {
            $employee = collect($this->employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$employee || ($payroll['Employee_ID'] ?? '') !== $employee['Employee_ID']) {
                throw new Exception("Akses Ditolak: Slip gaji #{$payrollId} bukan milik akun Anda.");
            }
        }

        $employee = $this->employeeRepo->findById($payroll['Employee_ID'] ?? '') ?? [];

        // Parse Details
        $details = [];
        if (!empty($payroll['Payroll_Details'])) {
            if (is_array($payroll['Payroll_Details'])) {
                $details = $payroll['Payroll_Details'];
            } else {
                try {
                    $details = json_decode($payroll['Payroll_Details'], true) ?? [];
                } catch (\Exception $e) {
                    $details = [];
                }
            }
        }

        $verificationUrl = route('payrolls.verify-public', $payrollId);

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
                'tagline' => 'Enterprise ERP & Human Resource Engine',
                'address' => 'Jl. Raya Wakamiya No. 88, Jakarta Selatan 12930',
                'contact' => 'Telp: (021) 8000-9999 | Email: hr@wakamiya.ac.id'
            ],
            'payroll' => $payroll,
            'details' => $details,
            'employee' => $employee,
            'verificationUrl' => $verificationUrl,
            'qrCodeSvg' => $qrCodeSvg
        ];
    }

    public function delete($id)
    {
        $payroll = $this->getById($id);
        if (!$payroll) {
            throw new Exception("Payroll tidak ditemukan.");
        }

        if (in_array(strtolower($payroll['Status'] ?? ''), ['paid', 'closed'])) {
            throw new Exception("Payroll yang sudah lunas (Paid) atau ditutup (Closed) tidak dapat dihapus.");
        }

        $res = $this->payrollRepo->delete($id);
        $this->payrollRepo->clearCache();
        
        $this->enterpriseEvent->dispatch('HR', 'DELETE', 'PAYROLL', $id, auth()->id() ?? 'SYSTEM', ['HR'], [], []);
        return $res;
    }
}