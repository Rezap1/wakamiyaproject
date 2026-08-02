<?php
namespace App\Services\Dashboard;

use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\TransactionService;
use App\Services\Finance\FinanceReportService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FinanceDashboardService
{
    protected $invoiceService;
    protected $paymentService;
    protected $transactionService;
    protected $financeReportService;
    protected $activityLogService;
    protected $notificationService;

    public function __construct(
        InvoiceService $invoiceService,
        PaymentService $paymentService,
        TransactionService $transactionService,
        FinanceReportService $financeReportService,
        ActivityLogService $activityLogService,
        NotificationService $notificationService
    ) {
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
        $this->transactionService = $transactionService;
        $this->financeReportService = $financeReportService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
    }

    public function getDashboardData()
    {
        // === Fetch data ONCE from FINANCE_TRANSACTION (Single Source of Truth) ===
        $transactions = collect($this->transactionService->getAll());
        $invoices = collect($this->invoiceService->getAll());
        $payments = collect($this->paymentService->getAll());

        $todayDate = Carbon::today()->format('Y-m-d');
        $thisMonthStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $thisMonthEnd = Carbon::now()->endOfMonth()->format('Y-m-d');

        // === Revenue & Expense THIS MONTH (from FINANCE_TRANSACTION) ===
        $thisMonthTransactions = $transactions->filter(function ($t) use ($thisMonthStart, $thisMonthEnd) {
            $date = $t['Transaction_Date'] ?? '';
            return $date >= $thisMonthStart && $date <= $thisMonthEnd;
        });

        $revenueThisMonth = $thisMonthTransactions->where('Type', 'Income')->sum('Amount');
        $expenseThisMonth = $thisMonthTransactions->where('Type', 'Expense')->sum('Amount');

        // === Cash Balance (all time from FINANCE_TRANSACTION) ===
        $totalRevenue = $transactions->where('Type', 'Income')->sum('Amount');
        $totalExpense = $transactions->where('Type', 'Expense')->sum('Amount');
        $cashBalance = $totalRevenue - $totalExpense;

        // === Outstanding Invoice ===
        $totalInvoice = $invoices->count();
        $unpaidInvoices = $invoices->whereIn('Status', ['Waiting Payment', 'Partial Paid']);
        $outstandingAmount = $unpaidInvoices->sum('Amount') -
            $payments->where('Status', 'Verified')
                ->whereIn('Invoice_ID', $unpaidInvoices->pluck('Invoice_ID'))
                ->sum('Amount_Paid');

        // === Overdue Invoices ===
        $overdueInvoices = $unpaidInvoices->filter(function ($inv) use ($todayDate) {
            return !empty($inv['Due_Date']) && $inv['Due_Date'] < $todayDate;
        })->count();

        // === Pending Verification ===
        $pendingVerification = $payments->where('Status', 'Waiting Verification')->count();

        // === Collection Rate ===
        $collectionRate = $totalInvoice > 0
            ? min(100, round(($invoices->whereIn('Status', ['Paid', 'Partial Paid'])->count() / $totalInvoice) * 100))
            : 0;

        // === KPI ===
        $kpi = [
            'revenue_this_month'    => $revenueThisMonth,
            'expense_this_month'    => $expenseThisMonth,
            'cash_balance'          => $cashBalance,
            'outstanding_amount'    => max(0, $outstandingAmount),
            'overdue_invoices'      => $overdueInvoices,
            'pending_verification'  => $pendingVerification,
            'collection_rate'       => $collectionRate,
        ];

        // === Charts (data riil) ===
        $charts = $this->getChartData($transactions);

        // === Reminders (data riil) ===
        $reminders = [];

        if ($pendingVerification > 0) {
            $reminders[] = [
                'title'       => 'Payment Verification Needed',
                'description' => 'Terdapat ' . $pendingVerification . ' pembayaran menunggu verifikasi.',
                'action_url'  => route('payments.index'),
            ];
        }

        if ($overdueInvoices > 0) {
            $reminders[] = [
                'title'       => 'Invoice Overdue',
                'description' => 'Terdapat ' . $overdueInvoices . ' tagihan melewati jatuh tempo.',
                'action_url'  => route('invoices.index'),
            ];
        }

        // === Notifications data ===
        $notifications = [
            'pendingVerification' => $payments->where('Status', 'Waiting Verification')->values()->take(5)->toArray(),
        ];

        // === Recent Activity (Finance modules, max 10) ===
        $recentActivities = $this->getRecentActivity(['FINANCE', 'FINANCE_TRANSACTION', 'INVOICE', 'PAYMENT', 'ACCOUNT']);

        // === Notification Count ===
        $userId = Auth::id();
        $unreadNotifications = 0;
        if ($userId) {
            try {
                $unreadNotifications = $this->notificationService->UnreadCount($userId, 'FINANCE');
            } catch (\Exception $e) {
                $unreadNotifications = 0;
            }
        }

        return compact(
            'kpi', 'charts', 'notifications', 'reminders', 'recentActivities', 'unreadNotifications'
        );
    }

    private function getChartData($transactions)
    {
        // === Cash Flow Monthly (last 6 months, riil) ===
        $months = [];
        $incomeData = [];
        $expenseData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->translatedFormat('M Y');
            $months[] = $monthLabel;

            $monthTransactions = $transactions->filter(function ($t) use ($monthKey) {
                return str_starts_with($t['Transaction_Date'] ?? '', $monthKey);
            });

            $incomeData[] = (int) $monthTransactions->where('Type', 'Income')->sum('Amount');
            $expenseData[] = (int) $monthTransactions->where('Type', 'Expense')->sum('Amount');
        }

        // === Revenue by Category (riil) ===
        $revenueByCat = $transactions->where('Type', 'Income')->groupBy('Category');
        $revCatLabels = [];
        $revCatData = [];
        foreach ($revenueByCat as $cat => $txns) {
            $revCatLabels[] = $cat ?: 'Lainnya';
            $revCatData[] = (int) $txns->sum('Amount');
        }

        // === Expense by Category (riil) ===
        $expenseByCat = $transactions->where('Type', 'Expense')->groupBy('Category');
        $expCatLabels = [];
        $expCatData = [];
        foreach ($expenseByCat as $cat => $txns) {
            $expCatLabels[] = $cat ?: 'Lainnya';
            $expCatData[] = (int) $txns->sum('Amount');
        }

        return [
            'cashFlow' => [
                'labels' => $months,
                'income' => $incomeData,
                'expense' => $expenseData,
            ],
            'revenueByCategory' => ['labels' => $revCatLabels, 'data' => $revCatData],
            'expenseByCategory' => ['labels' => $expCatLabels, 'data' => $expCatData],
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
