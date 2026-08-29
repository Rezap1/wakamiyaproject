<?php
namespace App\Services\Dashboard;

use App\Services\Core\EmployeeService;
use App\Services\Core\DepartmentService;
use App\Services\Core\AttendanceService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use App\Services\HR\PayrollService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class HrDashboardService
{
    protected $employeeService;
    protected $departmentService;
    protected $attendanceService;
    protected $activityLogService;
    protected $notificationService;
    protected $payrollService;

    public function __construct(
        EmployeeService $employeeService,
        DepartmentService $departmentService,
        AttendanceService $attendanceService,
        ActivityLogService $activityLogService,
        NotificationService $notificationService,
        PayrollService $payrollService
    ) {
        $this->employeeService = $employeeService;
        $this->departmentService = $departmentService;
        $this->attendanceService = $attendanceService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
        $this->payrollService = $payrollService;
    }

    public function getDashboardData()
    {
        // === Fetch data ONCE ===
        $employees = collect($this->employeeService->getAllEmployees());
        $departments = collect($this->departmentService->getAllDepartments())->where('Is_Active', '!=', 'FALSE');
        $attendances = collect($this->attendanceService->getAll());
        $payrolls = collect($this->payrollService->getAll());

        $todayDate = Carbon::today()->format('Y-m-d');
        $thisMonth = date('Y-m');

        // === Employee Derived KPIs ===
        $activeEmployees = $employees->filter(function ($emp) {
            return strcasecmp(trim($emp['Status'] ?? ''), 'Active') === 0 || strtoupper(trim($emp['Is_Active'] ?? '')) === 'TRUE';
        });
        $totalEmployees = $employees->count();
        $activeCount = $activeEmployees->count();

        // === Employee On Leave (today) ===
        $onLeaveToday = $attendances->filter(function ($a) use ($todayDate) {
            return ($a['Attendance_Date'] ?? '') === $todayDate &&
                   in_array(strtoupper(trim($a['Status'] ?? '')), ['LEAVE', 'SICK', 'PERMISSION', 'CUTI', 'SAKIT', 'IZIN']);
        })->pluck('Employee_ID')->unique()->count();

        // === Contract Expired ===
        $contractExpired = $employees->filter(function ($emp) use ($todayDate) {
            $contractEnd = $emp['Contract_End_Date'] ?? $emp['End_Date'] ?? '';
            $isActive = strcasecmp(trim($emp['Status'] ?? ''), 'Active') === 0 || strtoupper(trim($emp['Is_Active'] ?? '')) === 'TRUE';
            return !empty($contractEnd) && $contractEnd <= $todayDate && $isActive;
        });
        $contractExpiredCount = $contractExpired->count();

        // === Payroll This Month ===
        $payrollsThisMonth = $payrolls->filter(function ($p) use ($thisMonth) {
            return str_starts_with($p['Payroll_Period'] ?? '', $thisMonth);
        });
        $pendingPayroll = $payrollsThisMonth->whereIn('Status', ['Draft', 'Waiting Approval', 'Calculated', 'Generated'])->count();
        $approvedPayroll = $payrollsThisMonth->where('Status', 'Approved')->count();
        $paidPayroll = $payrollsThisMonth->where('Status', 'Paid')->count();
        $salaryExpense = $payrollsThisMonth->sum('Net_Salary');

        // === KPI ===
        $kpi = [
            'total_employees'   => $totalEmployees,
            'active_employees'  => $activeCount,
            'on_leave'          => $onLeaveToday,
            'total_departments' => $departments->count(),
            'payroll_draft'     => $pendingPayroll,
            'approved_payroll'  => $approvedPayroll,
            'paid_payroll'      => $paidPayroll,
            'salary_expense'    => $salaryExpense,
            'contract_expired'  => $contractExpiredCount,
        ];

        // === Charts (data riil) ===
        $charts = $this->getChartData($activeEmployees, $departments);

        // === Reminders (data riil) ===
        $reminders = [];

        // Payroll Waiting Approval
        $payrollPending = $payrollsThisMonth->whereIn('Status', ['Draft', 'Waiting Approval'])->take(5)->values()->toArray();
        foreach ($payrollPending as $pay) {
            $reminders[] = [
                'title'       => 'Payroll Waiting Approval',
                'description' => 'Payroll ' . ($pay['Payroll_Number'] ?? 'Unknown') . ' menunggu diproses.',
                'action_url'  => route('payrolls.index'),
            ];
        }

        // Contract Expired
        foreach ($contractExpired->take(3) as $emp) {
            $reminders[] = [
                'title'       => 'Contract Expired',
                'description' => ($emp['Full_Name'] ?? 'Employee') . ' — kontrak berakhir ' . ($emp['Contract_End_Date'] ?? $emp['End_Date'] ?? '—'),
                'action_url'  => route('employees.index'),
            ];
        }

        // === Notifications data ===
        $notifications = [
            'payrollPending'    => $payrollPending,
            'contractExpiring'  => $contractExpired->take(5)->values()->toArray(),
        ];

        // === Recent Activity (HR modules, max 10) ===
        $recentActivities = $this->getRecentActivity(['HR', 'EMPLOYEE', 'PAYROLL', 'DEPARTMENT', 'ATTENDANCE']);

        // === Notification Count ===
        $userId = Auth::id();
        $unreadNotifications = 0;
        if ($userId) {
            try {
                $unreadNotifications = $this->notificationService->UnreadCount($userId, 'HR');
            } catch (\Exception $e) {
                $unreadNotifications = 0;
            }
        }

        return compact(
            'kpi', 'charts', 'notifications', 'reminders', 'recentActivities', 'unreadNotifications'
        );
    }

    private function getChartData($employees, $departments)
    {
        // Department Distribution (riil)
        $deptMap = $departments->keyBy('Department_ID');
        $deptGroups = $employees->where('Status', 'Active')->groupBy('Department_ID');
        $deptLabels = [];
        $deptData = [];
        foreach ($deptGroups as $deptId => $emps) {
            $deptName = isset($deptMap[$deptId]) ? $deptMap[$deptId]['Department_Name'] : ($deptId ?: 'Lainnya');
            $deptLabels[] = $deptName;
            $deptData[] = $emps->count();
        }

        // Employment Status Distribution (riil)
        $statusGroups = $employees->groupBy('Employment_Status');
        $statusLabels = [];
        $statusData = [];
        foreach ($statusGroups as $status => $emps) {
            $statusLabels[] = $status ?: 'Unknown';
            $statusData[] = $emps->count();
        }

        return [
            'departmentDist'   => ['labels' => $deptLabels, 'data' => $deptData],
            'employmentStatus' => ['labels' => $statusLabels, 'data' => $statusData],
        ];
    }

    private function getRecentActivity(array $modules)
    {
        try {
            $logs = collect($this->activityLogService->getAllLogs());
            return $logs->filter(function ($log) use ($modules) {
                return in_array(strtoupper($log['Module'] ?? ''), $modules);
            })->sortByDesc('Created_At')->take(10)->map(function ($log) {
                $desc = $log['Description'] ?? ($log['New_Value'] ?? '');
                if (is_string($desc) && str_starts_with($desc, '{')) {
                    $decoded = json_decode($desc, true);
                    if (is_array($decoded) && isset($decoded['description'])) {
                        $desc = $decoded['description'];
                    } elseif (is_array($decoded) && isset($decoded['title'])) {
                        $desc = $decoded['title'];
                    } else {
                        $action = str_replace('_', ' ', $log['Action'] ?? '');
                        $refId = $log['Reference_ID'] ?? '';
                        $desc = "Aktivitas " . ucwords(strtolower($action)) . ($refId ? " pada {$refId}" : '');
                    }
                }
                return [
                    'title'       => $log['Action'] ?? 'Aktivitas',
                    'description' => ($log['Module'] ?? '') . ' — ' . $desc,
                    'time'        => isset($log['Created_At']) ? Carbon::parse($log['Created_At'])->diffForHumans() : 'Baru saja',
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
