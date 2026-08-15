<?php

namespace App\Services\HR;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;
use App\Services\HR\LeaveService;
use App\Services\HR\OvertimeService;
use Carbon\Carbon;

class PayrollCalculationEngine
{
    protected $attendanceRepo;
    protected $employeeRepo;
    protected $salaryComponentRepo;
    protected $leaveService;
    protected $overtimeService;

    public function __construct(
        AttendanceRepositoryInterface $attendanceRepo,
        EmployeeRepositoryInterface $employeeRepo,
        SalaryComponentRepositoryInterface $salaryComponentRepo,
        LeaveService $leaveService,
        OvertimeService $overtimeService
    ) {
        $this->attendanceRepo = $attendanceRepo;
        $this->employeeRepo = $employeeRepo;
        $this->salaryComponentRepo = $salaryComponentRepo;
        $this->leaveService = $leaveService;
        $this->overtimeService = $overtimeService;
    }

    /**
     * Compute deterministic server-side payroll calculation with Leave exemption and Overtime integration.
     */
    public function calculate(string $employeeId, string $period, array $overrides = []): array
    {
        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee || strtoupper(trim($employee['Is_Active'] ?? 'TRUE')) === 'FALSE') {
            throw new \Exception("Pegawai #{$employeeId} tidak ditemukan atau sedang tidak aktif.");
        }

        // 1. Base Salary Resolution
        $baseSalary = (float) ($employee['Base_Salary'] ?? $overrides['base_salary'] ?? 0);
        if ($baseSalary <= 0) {
            $baseSalary = (float) config('finance.default_base_salary', 3500000);
        }

        // 2. Allowances & Approved Overtime Integration (Sub-Phase H5)
        $allowances = (float) ($overrides['allowances'] ?? 0);
        $bonus = (float) ($overrides['bonus'] ?? 0);

        // Read Approved Overtime Pay from OvertimeService
        $approvedOvertimePay = $this->overtimeService->getApprovedOvertimePayForPeriod($employeeId, $period);
        $overtime = (float) ($overrides['overtime'] ?? 0) + $approvedOvertimePay;

        // Fetch master active salary components if available
        $components = collect($this->salaryComponentRepo->getAll())
            ->where('Status', 'Active');
        
        $masterAllowances = (float) $components->where('Type', 'Allowance')->sum('Amount');
        $allowances += $masterAllowances;

        // 3. Read Server-side Phase F Dynamic QR Attendance Data for Period (YYYY-MM)
        $allAttendances = collect($this->attendanceRepo->fetchAll());
        $periodAttendances = $allAttendances->filter(function ($att) use ($employeeId, $period) {
            if (($att['Employee_ID'] ?? '') !== $employeeId) return false;
            if (strtoupper(trim($att['Is_Active'] ?? 'TRUE')) === 'FALSE') return false;
            
            $attDate = $att['Attendance_Date'] ?? null;
            if (!$attDate) return false;

            try {
                return str_starts_with($attDate, $period);
            } catch (\Exception $e) {
                return false;
            }
        });

        $presentCount = $periodAttendances->where('Status', 'PRESENT')->count();
        $lateCount = $periodAttendances->where('Status', 'LATE')->count();
        $totalLateMinutes = (int) $periodAttendances->sum('Late_Minutes');

        // Late Deduction (Calculated separately per minute)
        $lateRatePerMinute = (float) config('finance.late_deduction_per_minute', 1000);
        $lateDeduction = $totalLateMinutes * $lateRatePerMinute;

        // Read Approved Leaves for Period (Sub-Phase H4)
        $approvedLeaveDays = $this->leaveService->getApprovedLeavesForPeriod($employeeId, $period);

        // ABSENT DAYS CORRECTION DIRECTIVE:
        // LATE IS A PRESENT DAY! (Valid Present Days = Present Days + Late Days)
        $validPresentDays = $presentCount + $lateCount;
        $expectedWorkingDays = (int) config('finance.monthly_working_days', 22);

        // Absent Days = max(0, Working Days - Valid Present Days - Approved Leave Days)
        $absentDays = max(0, $expectedWorkingDays - $validPresentDays - $approvedLeaveDays);
        
        $dailyAbsenceRate = (float) config('finance.absence_deduction_per_day', 150000);
        $absenceDeduction = $absentDays * $dailyAbsenceRate;

        // Master Component Deductions
        $masterDeductions = (float) $components->where('Type', 'Deduction')->sum('Amount');

        // 4. Tax & BPJS Calculation (Configurable)
        $taxRate = (float) config('finance.tax_rate_pph21', 0.05);
        $bpjsRate = (float) config('finance.bpjs_rate', 0.02);

        $grossSalary = $baseSalary + $allowances + $bonus + $overtime;
        
        $tax = $grossSalary * $taxRate;
        $bpjs = $grossSalary * $bpjsRate;
        $otherDeduction = (float) ($overrides['other_deduction'] ?? 0) + $masterDeductions;

        $totalDeduction = $lateDeduction + $absenceDeduction + $tax + $bpjs + $otherDeduction;
        $netSalary = max(0.0, $grossSalary - $totalDeduction);

        return [
            'employee_id' => $employeeId,
            'employee_name' => $employee['Full_Name'] ?? $employeeId,
            'period' => $period,
            'base_salary' => $baseSalary,
            'allowances' => $allowances,
            'bonus' => $bonus,
            'overtime' => $overtime,
            'approved_overtime_pay' => $approvedOvertimePay,
            'gross_salary' => $grossSalary,
            'attendance_metrics' => [
                'present_days' => $presentCount,
                'late_days' => $lateCount,
                'valid_present_days' => $validPresentDays,
                'approved_leave_days' => $approvedLeaveDays,
                'absent_days' => $absentDays,
                'total_late_minutes' => $totalLateMinutes,
            ],
            'late_deduction' => $lateDeduction,
            'absence_deduction' => $absenceDeduction,
            'tax' => $tax,
            'bpjs' => $bpjs,
            'other_deduction' => $otherDeduction,
            'total_deduction' => $totalDeduction,
            'net_salary' => $netSalary,
            'calculated_at' => now()->toDateTimeString()
        ];
    }
}
