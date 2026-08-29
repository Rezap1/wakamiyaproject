<?php

namespace App\Services\Dashboard;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminDashboardService
{
    protected $userRepo;
    protected $employeeRepo;
    protected $teacherRepo;
    protected $studentRepo;
    protected $companyRepo;
    protected $documentRepo;
    protected $activityLogRepo;
    protected $programRepo;
    protected $batchRepo;
    protected $notificationService;

    public function __construct(
        UserRepositoryInterface $userRepo,
        EmployeeRepositoryInterface $employeeRepo,
        TeacherRepositoryInterface $teacherRepo,
        StudentRepositoryInterface $studentRepo,
        CompanyRepositoryInterface $companyRepo,
        DocumentRepositoryInterface $documentRepo,
        ActivityLogRepositoryInterface $activityLogRepo,
        ProgramRepositoryInterface $programRepo,
        BatchRepositoryInterface $batchRepo,
        NotificationService $notificationService
    ) {
        $this->userRepo = $userRepo;
        $this->employeeRepo = $employeeRepo;
        $this->teacherRepo = $teacherRepo;
        $this->studentRepo = $studentRepo;
        $this->companyRepo = $companyRepo;
        $this->documentRepo = $documentRepo;
        $this->activityLogRepo = $activityLogRepo;
        $this->programRepo = $programRepo;
        $this->batchRepo = $batchRepo;
        $this->notificationService = $notificationService;
    }

    public function getDashboardData()
    {
        $userId = Auth::id() ?? 'anonymous';
        // === Fetch all data ONCE ===
        $users = collect($this->userRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
        $students = collect($this->studentRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
        $employees = collect($this->employeeRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
        $programs = collect($this->programRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
        $batches = collect($this->batchRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');

        $countActive = function ($repo) {
            return collect($repo->fetchAll())->where('Is_Active', '!=', 'FALSE')->count();
        };

        $roleService = app(\App\Services\Core\RoleService::class);
        $roles = collect($roleService->getAllRoles())->keyBy('Role_ID');

        $countByRole = function ($roleName) use ($users, $roles) {
            return $users->filter(function ($user) use ($roleName, $roles) {
                $roleId = $user['Role_ID'] ?? '';
                if (isset($roles[$roleId])) {
                    return stripos($roles[$roleId]['Role_Name'] ?? '', $roleName) !== false;
                }
                return false;
            })->count();
        };

        // 1. KPI
        $kpi = [
            'users'     => $users->count(),
            'employees' => $employees->count(),
            'teachers'  => $countByRole('TEACHER'),
            'students'  => $students->count(),
            'companies' => $countActive($this->companyRepo),
            'documents' => $countActive($this->documentRepo),
            'programs'  => $programs->count(),
            'batches'   => $batches->count(),
            'hr'        => $countByRole('HR'),
            'finance'   => $countByRole('FINANCE'),
            'academic'  => $countByRole('ACADEMIC'),
            'marketing' => $countByRole('MARKETING'),
        ];

        // 2. Chart Data — Student by Program (riil)
        $programsMap = $programs->keyBy('Program_ID');
        $studentProgramCount = $students->groupBy('Program_ID')->map->count();
        $studentProgramLabels = [];
        $studentProgramData = [];
        foreach ($studentProgramCount as $progId => $count) {
            $progName = isset($programsMap[$progId]) ? $programsMap[$progId]['Program_Name'] : 'Tidak Diketahui';
            $studentProgramLabels[] = $progName;
            $studentProgramData[] = $count;
        }

        // Student by Batch (riil)
        $batchesMap = $batches->keyBy('Batch_ID');
        $studentBatchCount = $students->groupBy('Batch_ID')->map->count();
        $studentBatchLabels = [];
        $studentBatchData = [];
        foreach ($studentBatchCount as $batchId => $count) {
            $batchName = isset($batchesMap[$batchId]) ? $batchesMap[$batchId]['Batch_Name'] : 'Tidak Diketahui';
            $studentBatchLabels[] = $batchName;
            $studentBatchData[] = $count;
        }

        // Student Growth Trend (6 months, riil from Created_At)
        $growthLabels = [];
        $growthRegistered = [];
        $growthActive = [];
        $allStudentsRaw = collect($this->studentRepo->fetchAll());
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $growthLabels[] = $date->translatedFormat('M Y');

            $registered = $allStudentsRaw->filter(function ($s) use ($monthKey) {
                return str_starts_with($s['Created_At'] ?? $s['Registration_Date'] ?? '', $monthKey);
            })->count();
            $growthRegistered[] = $registered;

            $activeInMonth = $allStudentsRaw->filter(function ($s) use ($monthKey) {
                $createdAt = $s['Created_At'] ?? $s['Registration_Date'] ?? '';
                return $createdAt <= $monthKey . '-31' && ($s['Is_Active'] ?? 'TRUE') !== 'FALSE';
            })->count();
            $growthActive[] = $activeInMonth;
        }

        // 3. Recent Activity (Admin modules, max 10)
        $recentActivities = [];
        try {
            $allowedModules = ['SYSTEM', 'USER', 'ROLE', 'ACTIVITY_LOG', 'DOCUMENT', 'COMPANY'];
            $recentActivities = collect($this->activityLogRepo->fetchAll())
                ->filter(function ($log) use ($allowedModules) {
                    return in_array(strtoupper($log['Module'] ?? ''), $allowedModules);
                })
                ->sortByDesc('Created_At')
                ->take(10)
                ->map(function ($log) {
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
                })
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            $recentActivities = [];
        }

        // 4. Finance Summary (riil from FINANCE_TRANSACTION via TransactionService)
        $financeSummary = [
            'pendapatan'      => 0,
            'pengeluaran'     => 0,
            'saldo'           => 0,
            'revenue_change'  => 0,
            'expense_change'  => 0,
            'balance_change'  => 0,
        ];
        try {
            $transactionService = app(\App\Services\Finance\TransactionService::class);
            $allTransactions = collect($transactionService->getAll());

            $thisMonth = Carbon::now()->format('Y-m');
            $lastMonth = Carbon::now()->subMonth()->format('Y-m');

            $pendapatan = $this->sumTransactionsByType($allTransactions, 'Income');
            $pengeluaran = $this->sumTransactionsByType($allTransactions, 'Expense');

            $financeSummary['pendapatan'] = $pendapatan;
            $financeSummary['pengeluaran'] = $pengeluaran;
            $financeSummary['saldo'] = $pendapatan - $pengeluaran;

            // Month-over-Month change (riil, not hardcoded)
            $thisMonthTxns = $allTransactions->filter(function ($t) use ($thisMonth) {
                return str_starts_with($t['Transaction_Date'] ?? '', $thisMonth);
            });
            
            $lastMonthTxns = $allTransactions->filter(function ($t) use ($lastMonth) {
                return str_starts_with($t['Transaction_Date'] ?? '', $lastMonth);
            });

            $thisRevenue = $this->sumTransactionsByType($thisMonthTxns, 'Income');
            $lastRevenue = $this->sumTransactionsByType($lastMonthTxns, 'Income');
            $thisExpense = $this->sumTransactionsByType($thisMonthTxns, 'Expense');
            $lastExpense = $this->sumTransactionsByType($lastMonthTxns, 'Expense');

            $financeSummary['revenue_change'] = $lastRevenue > 0 ? round((($thisRevenue - $lastRevenue) / $lastRevenue) * 100, 1) : 0;
            $financeSummary['expense_change'] = $lastExpense > 0 ? round((($thisExpense - $lastExpense) / $lastExpense) * 100, 1) : 0;
            $lastBalance = $lastRevenue - $lastExpense;
            $thisBalance = $thisRevenue - $thisExpense;
            $financeSummary['balance_change'] = $lastBalance != 0 ? round((($thisBalance - $lastBalance) / abs($lastBalance)) * 100, 1) : 0;

        } catch (\Exception $e) {
            // Ignore if not setup yet
        }

        // 5. Notifications
        $unreadNotifications = 0;
        $pengumuman = [];
        if ($userId && $userId !== 'anonymous') {
            try {
                $unreadNotifications = $this->notificationService->UnreadCount($userId, 'ADMINISTRATOR');
                
                // Fetch real notifications for pengumuman
                $recentNotifs = $this->notificationService->RecentNotification($userId, 'ADMINISTRATOR', 5);
                $pengumuman = collect($recentNotifs)->map(function($n) {
                    return [
                        'title' => $n['Title'] ?? 'Pengumuman',
                        'description' => $n['Message'] ?? ''
                    ];
                })->values()->toArray();
            } catch (\Exception $e) {
                $unreadNotifications = 0;
            }
        }

        // 6. Calendar / Upcoming Schedules
        $calendar = [];
        try {
            $scheduleService = app(\App\Services\Academic\ScheduleService::class);
            $allSchedules = collect($scheduleService->getAll());
            $calendar = $allSchedules->sortBy('Date')->take(5)->map(function($s) {
                return [
                    'date' => $s['Date'] ?? date('Y-m-d'),
                    'title' => ($s['Subject_ID'] ?? 'Kegiatan') . ' - ' . ($s['Topic'] ?? ''),
                    'type' => $s['Type'] ?? 'Acara'
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            // Ignore if not setup
        }

        return [
            'kpi'               => $kpi,
            'charts'            => [
                'studentProgram' => ['labels' => $studentProgramLabels, 'data' => $studentProgramData],
                'studentBatch'   => ['labels' => $studentBatchLabels, 'data' => $studentBatchData],
                'studentGrowth'  => ['labels' => $growthLabels, 'registered' => $growthRegistered, 'active' => $growthActive],
            ],
            'notifications'     => ['pengumuman' => $pengumuman],
            'recentActivities'  => $recentActivities,
            'financeSummary'    => $financeSummary,
            'unreadNotifications' => $unreadNotifications,
            'calendar'          => $calendar,
        ];
    }

    private function sumTransactionsByType($transactions, string $expectedType): float
    {
        return (float) collect($transactions)
            ->filter(function ($transaction) use ($expectedType) {
                return $this->normalizeTransactionType($transaction['Type'] ?? '') === $expectedType;
            })
            ->sum(function ($transaction) {
                return (float) ($transaction['Amount'] ?? 0);
            });
    }

    private function normalizeTransactionType($type): ?string
    {
        $value = strtolower(trim((string) $type));

        if (in_array($value, ['income', 'pemasukan', 'masuk', 'revenue', 'pendapatan'], true)) {
            return 'Income';
        }

        if (in_array($value, ['expense', 'pengeluaran', 'keluar', 'cost', 'biaya', 'beban'], true)) {
            return 'Expense';
        }

        return null;
    }
}
