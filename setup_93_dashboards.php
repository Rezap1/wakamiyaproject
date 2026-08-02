<?php
// 1. Update HR Dashboard Controller
$hrCtrlPath = 'app/Http/Controllers/Core/HrDashboardController.php';
$hrCtrlContent = <<<'EOT'
<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Services\HR\PayrollService;

class HrDashboardController extends Controller
{
    protected $employeeRepo, $attendanceRepo, $activityLogRepo, $payrollService;

    public function __construct(
        EmployeeRepositoryInterface $employeeRepo,
        AttendanceRepositoryInterface $attendanceRepo,
        ActivityLogRepositoryInterface $activityLogRepo,
        PayrollService $payrollService
    ) {
        $this->employeeRepo = $employeeRepo;
        $this->attendanceRepo = $attendanceRepo;
        $this->activityLogRepo = $activityLogRepo;
        $this->payrollService = $payrollService;
    }

    public function index()
    {
        $dashboardData = Cache::remember('hr_dashboard_data', 60, function () {
            
            // Employee & Attendance data
            $employees = collect($this->employeeRepo->fetchAll());
            
            // Payroll KPI Calculation
            $payrolls = collect($this->payrollService->getAll());
            $thisMonth = date('Y-m');
            $payrollsThisMonth = $payrolls->filter(function($p) use ($thisMonth) {
                return str_starts_with($p['Payroll_Period'] ?? '', $thisMonth);
            });

            $totalPayroll = $payrolls->count();
            $pendingPayroll = $payrollsThisMonth->whereIn('Status', ['Draft', 'Waiting Approval', 'Calculated', 'Generated'])->count();
            $approvedPayroll = $payrollsThisMonth->where('Status', 'Approved')->count();
            $paidPayroll = $payrollsThisMonth->where('Status', 'Paid')->count();
            $salaryExpense = $payrollsThisMonth->sum('Net_Salary');
            $averageSalary = $payrollsThisMonth->count() > 0 ? $salaryExpense / $payrollsThisMonth->count() : 0;

            $kpi = [
                'total_employees' => $employees->count(),
                'active_employees' => $employees->where('Status', 'Active')->count(),
                'on_leave' => 0,
                'total_departments' => $employees->pluck('Department')->unique()->count(),
                'total_payroll' => $totalPayroll,
                'payroll_this_month' => $payrollsThisMonth->count(),
                'pending_payroll' => $pendingPayroll,
                'approved_payroll' => $approvedPayroll,
                'paid_payroll' => $paidPayroll,
                'salary_expense' => $salaryExpense,
                'average_salary' => $averageSalary,
            ];

            return [
                'kpi' => $kpi,
                'charts' => $this->getChartData($employees),
                'notifications' => $this->getNotificationData($payrollsThisMonth),
                'recentActivities' => $this->getRecentActivity()
            ];
        });

        return view('dashboard.hr', $dashboardData);
    }

    private function getChartData($employees) {
        return [
            'departmentDist' => ['labels' => [], 'data' => []],
            'attendanceTrend' => ['labels' => [], 'data' => []],
            'employeeGrowth' => ['labels' => [], 'data' => []],
            'leaveStatus' => ['labels' => [], 'data' => []],
        ];
    }

    private function getNotificationData($payrollsThisMonth) {
        return [
            'leaveRequests' => [],
            'contractExpiring' => [],
            'payrollPending' => $payrollsThisMonth->whereIn('Status', ['Draft', 'Waiting Approval'])->take(5)->toArray(),
        ];
    }

    private function getRecentActivity() { return []; }
}
EOT;
file_put_contents($hrCtrlPath, $hrCtrlContent);

// 2. Update Finance Dashboard Controller (Adding Monthly Payroll Expense)
$finCtrlPath = 'app/Http/Controllers/Core/FinanceDashboardController.php';
$finCtrlContent = <<<'EOT'
<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\HR\PayrollService;

class FinanceDashboardController extends Controller
{
    protected $activityLogRepo, $invoiceService, $paymentService, $payrollService;

    public function __construct(
        ActivityLogRepositoryInterface $activityLogRepo,
        InvoiceService $invoiceService,
        PaymentService $paymentService,
        PayrollService $payrollService
    ) {
        $this->activityLogRepo = $activityLogRepo;
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
        $this->payrollService = $payrollService;
    }

    public function index()
    {
        $dashboardData = Cache::remember('finance_dashboard', 60, function () {
            $invoices = $this->invoiceService->getAll();
            $payments = $this->paymentService->getAll();
            $payrolls = $this->payrollService->getAll();

            $totalInvoice = $invoices->count();
            $outstandingAmount = $invoices->where('Status', 'Waiting Payment')->sum('Amount');
            $paidToday = $payments->where('Status', 'Verified')->filter(function($p) {
                return str_starts_with($p['Payment_Date'] ?? '', date('Y-m-d'));
            })->sum('Amount_Paid');
            $pendingVerification = $payments->where('Status', 'Waiting Verification')->count();
            
            // Payroll calculations for Finance
            $thisMonth = date('Y-m');
            $payrollsThisMonth = $payrolls->filter(function($p) use ($thisMonth) {
                return str_starts_with($p['Payroll_Period'] ?? '', $thisMonth);
            });
            $payrollExpense = $payrollsThisMonth->sum('Net_Salary');
            $payrollPending = $payrollsThisMonth->whereIn('Status', ['Draft', 'Waiting Approval', 'Approved'])->count(); // Finance considers Approved as Pending Payment
            $payrollPaid = $payrollsThisMonth->where('Status', 'Paid')->count();

            $kpi = [
                'total_invoice' => $totalInvoice,
                'outstanding_amount' => $outstandingAmount,
                'paid_today' => $paidToday,
                'pending_verification' => $pendingVerification,
                'cash_in' => $payments->where('Status', 'Verified')->sum('Amount_Paid'),
                'cash_out' => $payrolls->where('Status', 'Paid')->sum('Net_Salary'),
                'monthly_payroll_expense' => $payrollExpense,
                'payroll_pending' => $payrollPending,
                'payroll_paid' => $payrollPaid,
            ];

            return [
                'kpi' => $kpi,
                'charts' => $this->getChartData(),
                'notifications' => $this->getNotificationData($invoices, $payments),
                'recentActivities' => $this->getRecentActivity()
            ];
        });

        return view('dashboard.finance', $dashboardData);
    }

    private function getChartData() { return ['cashFlow'=>[],'revenueByProgram'=>[],'payrollTrend'=>[],'paymentStatus'=>[]]; }
    private function getNotificationData($invoices, $payments) { return ['payrollPending'=>[],'studentPaymentPending'=>[],'outstandingInvoice'=>[]]; }
    private function getRecentActivity() { return []; }
}
EOT;
file_put_contents($finCtrlPath, $finCtrlContent);

echo "Dashboard Controllers Updated.\n";
?>
