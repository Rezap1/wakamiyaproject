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

        // Fetch System Settings for Payroll
        $settingService = app(\App\Services\Core\SystemSettingService::class);
        $payrollSettings = $settingService->category('Payroll')->pluck('Setting_Value', 'Setting_Key');

        $defaultBasicSalary = (float) ($payrollSettings['DEFAULT_BASIC_SALARY'] ?? 3500000);
        $salaryFinance = (float) ($payrollSettings['SALARY_FINANCE'] ?? 3800000);
        $salaryTeacher = (float) ($payrollSettings['SALARY_TEACHER'] ?? 4000000);
        $salaryAcademic = (float) ($payrollSettings['SALARY_ACADEMIC'] ?? 3700000);
        $salaryMarketing = (float) ($payrollSettings['SALARY_MARKETING'] ?? 3500000);
        $salaryHr = (float) ($payrollSettings['SALARY_HR'] ?? 4000000);

        $taxRatePercentage = (float) ($payrollSettings['TAX_PERCENTAGE'] ?? 0.0);
        $bpjsRatePercentage = (float) ($payrollSettings['BPJS_PERCENTAGE'] ?? 0.0);
        $absenceDeductionPerDay = (float) ($payrollSettings['ABSENCE_DEDUCTION_PER_DAY'] ?? 0);
        $enableAutoAttendanceDeduction = filter_var($payrollSettings['ENABLE_AUTO_ATTENDANCE_DEDUCTION'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // 1. Base Salary Resolution by Role / Department / Profile
        $employeeBaseSalary = (float) ($employee['Base_Salary'] ?? 0);
        
        if ($employeeBaseSalary > 0) {
            $baseSalary = $employeeBaseSalary;
        } else {
            // Determine by Department or Position or Role
            $dept = strtoupper(trim($employee['Department_Name'] ?? ($employee['Department_ID'] ?? '')));
            $pos = strtoupper(trim($employee['Position_Name'] ?? ($employee['Position_ID'] ?? '')));
            $role = strtoupper(trim($employee['Role'] ?? ($employee['Role_ID'] ?? '')));
            $fullName = strtoupper(trim($employee['Full_Name'] ?? ''));

            if (str_contains($dept, 'FINANCE') || str_contains($pos, 'FINANCE') || str_contains($role, 'FINANCE') || str_contains($fullName, 'FINANCE')) {
                $baseSalary = $salaryFinance;
            } elseif (str_contains($dept, 'TEACHER') || str_contains($pos, 'GURU') || str_contains($pos, 'SENSEI') || str_contains($role, 'TEACHER') || str_contains($fullName, 'TEACHER') || str_contains($fullName, 'SENSEI')) {
                $baseSalary = $salaryTeacher;
            } elseif (str_contains($dept, 'ACADEMIC') || str_contains($pos, 'AKADEMIK') || str_contains($role, 'ACADEMIC') || str_contains($fullName, 'ACADEMIC')) {
                $baseSalary = $salaryAcademic;
            } elseif (str_contains($dept, 'MARKETING') || str_contains($pos, 'MARKETING') || str_contains($role, 'MARKETING') || str_contains($fullName, 'MARKETING')) {
                $baseSalary = $salaryMarketing;
            } elseif (str_contains($dept, 'HR') || str_contains($pos, 'HR') || str_contains($role, 'HR') || str_contains($fullName, 'HR')) {
                $baseSalary = $salaryHr;
            } else {
                $baseSalary = $defaultBasicSalary;
            }
        }

        // If user submitted an explicit Net Salary input on form (and Net_Salary > 0)
        $inputNetSalary = (float) ($overrides['Net_Salary'] ?? 0);
        if ($inputNetSalary > 0) {
            $baseSalary = $inputNetSalary;
        }

        // 2. Allowances & Approved Overtime Integration
        $allowances = (float) ($overrides['allowances'] ?? 0);
        $bonus = (float) ($overrides['bonus'] ?? 0);

        $approvedOvertimePay = $this->overtimeService->getApprovedOvertimePayForPeriod($employeeId, $period);
        $overtime = (float) ($overrides['overtime'] ?? 0) + $approvedOvertimePay;

        $components = collect($this->salaryComponentRepo->getAll())->where('Status', 'Active');
        $masterAllowances = (float) $components->where('Type', 'Allowance')->sum('Amount');
        $allowances += $masterAllowances;

        // 3. Attendance Data
        $allAttendances = collect($this->attendanceRepo->fetchAll());
        $periodAttendances = $allAttendances->filter(function ($att) use ($employeeId, $period) {
            if (($att['Employee_ID'] ?? '') !== $employeeId) return false;
            if (strtoupper(trim($att['Is_Active'] ?? 'TRUE')) === 'FALSE') return false;
            $attDate = $att['Attendance_Date'] ?? null;
            return $attDate && str_starts_with($attDate, $period);
        });

        $presentCount = $periodAttendances->where('Status', 'PRESENT')->count();
        $lateCount = $periodAttendances->where('Status', 'LATE')->count();
        $totalLateMinutes = (int) $periodAttendances->sum('Late_Minutes');

        $lateRatePerMinute = (float) config('finance.late_deduction_per_minute', 1000);
        $lateDeduction = $enableAutoAttendanceDeduction ? ($totalLateMinutes * $lateRatePerMinute) : 0;

        $approvedLeaveDays = $this->leaveService->getApprovedLeavesForPeriod($employeeId, $period);
        $validPresentDays = $presentCount + $lateCount;
        $expectedWorkingDays = (int) config('finance.monthly_working_days', 22);

        $absentDays = max(0, $expectedWorkingDays - $validPresentDays - $approvedLeaveDays);
        
        // Only apply absence deduction if auto attendance deduction is explicitly enabled AND attendances exist
        if ($enableAutoAttendanceDeduction && $periodAttendances->count() > 0) {
            $absenceDeduction = $absentDays * $absenceDeductionPerDay;
        } else {
            $absenceDeduction = 0;
        }

        $masterDeductions = (float) $components->where('Type', 'Deduction')->sum('Amount');

        // 4. Tax & BPJS Calculation
        $grossSalary = $baseSalary + $allowances + $bonus + $overtime;
        
        $tax = $grossSalary * ($taxRatePercentage / 100);
        $bpjs = $grossSalary * ($bpjsRatePercentage / 100);
        $otherDeduction = (float) ($overrides['other_deduction'] ?? 0) + $masterDeductions;

        $totalDeduction = $lateDeduction + $absenceDeduction + $tax + $bpjs + $otherDeduction;

        // If user submitted an explicit Net_Salary > 0, honor inputNetSalary as netSalary
        if ($inputNetSalary > 0) {
            $netSalary = $inputNetSalary;
            $totalDeduction = max(0.0, $grossSalary - $netSalary);
        } else {
            $netSalary = max(0.0, $grossSalary - $totalDeduction);
        }

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
