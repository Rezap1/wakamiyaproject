<?php
$content = <<<'EOT'
<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;

class FinanceDashboardController extends Controller
{
    protected $activityLogRepo, $invoiceService, $paymentService;

    public function __construct(
        ActivityLogRepositoryInterface $activityLogRepo,
        InvoiceService $invoiceService,
        PaymentService $paymentService
    ) {
        $this->activityLogRepo = $activityLogRepo;
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        $dashboardData = Cache::remember('finance_dashboard', 60, function () {
            $invoices = $this->invoiceService->getAll();
            $payments = $this->paymentService->getAll();

            $totalInvoice = $invoices->count();
            $outstandingAmount = $invoices->where('Status', 'Waiting Payment')->sum('Amount');
            $paidToday = $payments->where('Status', 'Verified')->filter(function($p) {
                return str_starts_with($p['Payment_Date'] ?? '', date('Y-m-d'));
            })->sum('Amount_Paid');
            $pendingVerification = $payments->where('Status', 'Waiting Verification')->count();
            
            $kpi = [
                'total_invoice' => $totalInvoice,
                'outstanding_amount' => $outstandingAmount,
                'paid_today' => $paidToday,
                'pending_verification' => $pendingVerification,
                'cash_in' => $payments->where('Status', 'Verified')->sum('Amount_Paid'),
                'cash_out' => 0, // Placeholder for Phase 9.3 Accounting
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

    private function getChartData()
    {
        // Future Placeholder: Chart datasets will be populated here.
        return [
            'cashFlow' => ['labels' => [], 'data' => []],
            'revenueByProgram' => ['labels' => [], 'data' => []],
            'payrollTrend' => ['labels' => [], 'data' => []],
            'paymentStatus' => ['labels' => [], 'data' => []],
        ];
    }

    private function getNotificationData($invoices, $payments)
    {
        return [
            'payrollPending' => [],
            'studentPaymentPending' => $payments->where('Status', 'Waiting Verification')->take(5)->values()->toArray(),
            'outstandingInvoice' => $invoices->where('Status', 'Overdue')->take(5)->values()->toArray(),
        ];
    }

    private function getRecentActivity()
    {
        try {
            $activities = collect($this->activityLogRepo->fetchAll())
                ->filter(function($log) {
                    $module = strtoupper(trim($log['Module'] ?? ''));
                    return in_array($module, ['FINANCE', 'PAYROLL', 'PAYMENT', 'ACCOUNTING']);
                })
                ->sortByDesc('Created_At')
                ->take(10)
                ->values()
                ->toArray();
            return $activities;
        } catch (\Exception $e) {
            return [];
        }
    }
}
EOT;
file_put_contents('app/Http/Controllers/Core/FinanceDashboardController.php', $content);

$bladeContent = file_get_contents('resources/views/dashboard/finance.blade.php');
$bladeContent = str_replace(
    [
        "title=\"Student Payment (Soon)\" :value=\"\$kpi['student_payment']\"",
        "title=\"Cash In (Soon)\" :value=\"\$kpi['cash_in']\"",
        "title=\"Cash Out (Soon)\" :value=\"\$kpi['cash_out']\"",
        "title=\"Outstanding (Soon)\" :value=\"\$kpi['outstanding_payment']\""
    ],
    [
        "title=\"Total Invoices\" :value=\"\$kpi['total_invoice']\"",
        "title=\"Cash In\" :value=\"'Rp '.number_format(\$kpi['cash_in'], 0, ',', '.')\"",
        "title=\"Pending Verification\" :value=\"\$kpi['pending_verification']\"",
        "title=\"Outstanding\" :value=\"'Rp '.number_format(\$kpi['outstanding_amount'], 0, ',', '.')\""
    ],
    $bladeContent
);
file_put_contents('resources/views/dashboard/finance.blade.php', $bladeContent);

echo "FinanceDashboardController updated.\n";
?>
