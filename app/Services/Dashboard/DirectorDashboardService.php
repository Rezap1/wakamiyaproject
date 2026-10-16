<?php

namespace App\Services\Dashboard;

use App\Helpers\AttendanceStatusHelper;
use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface;
use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Support\Finance\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DirectorDashboardService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employees,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly StudentRepositoryInterface $students,
        private readonly CompanyRepositoryInterface $companies,
        private readonly ActivityLogRepositoryInterface $activityLogs,
        private readonly ProgramRepositoryInterface $programs,
        private readonly BatchRepositoryInterface $batches,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly PaymentRepositoryInterface $payments,
        private readonly ApprovalRepositoryInterface $approvals,
        private readonly ApprovalHistoryRepositoryInterface $approvalHistory,
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly AttendanceRepositoryInterface $attendances,
    ) {}

    public function getDashboardData(array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        // Each source is fetched once. A source failure bubbles to the
        // controller so the UI renders an explicit error, never a false zero.
        $students = collect($this->students->fetchAll())
            ->filter(fn ($row) => SheetValue::isOperationalStudent((array) $row))
            ->values();
        $teachers = $this->activeRows($this->teachers->fetchAll());
        $employees = $this->activeEmployees($this->employees->fetchAll());
        $companies = $this->activeRows($this->companies->fetchAll());
        $programs = $this->activeRows($this->programs->fetchAll())->keyBy('Program_ID');
        $batches = $this->activeRows($this->batches->fetchAll())->keyBy('Batch_ID');
        $transactions = $this->activeRows($this->transactions->fetchAll());
        $invoices = $this->activeRows($this->invoices->getAll());
        $payments = $this->activeRows($this->payments->getAll());
        $approvals = collect($this->approvals->getAll());
        $attendances = $this->activeRows($this->attendances->fetchAll());

        $periodTransactions = $this->betweenDates($transactions, 'Transaction_Date', $startDate, $endDate);
        $income = $this->sumTransactions($periodTransactions, 'Income');
        $expense = $this->sumTransactions($periodTransactions, 'Expense');

        $newStudents = $students->filter(fn ($row) => $this->dateInPeriod(
            $row['Registration_Date'] ?? $row['Created_At'] ?? null,
            $startDate,
            $endDate
        ))->count();

        $employeeIds = $employees->pluck('Employee_ID')->filter()->map(fn ($id) => (string) $id)->flip();
        $periodAttendance = $this->betweenDates($attendances, 'Attendance_Date', $startDate, $endDate)
            ->filter(fn ($row) => $employeeIds->has(trim((string) ($row['Employee_ID'] ?? ''))));
        $attendanceSummary = $this->attendanceSummary($periodAttendance);

        $pendingApprovals = $approvals->filter(fn ($row) => $this->isDirectorPendingApproval($row))
            ->sortByDesc(fn ($row) => $this->approvalDate($row))
            ->values();

        $outstandingInvoices = $invoices->filter(function ($invoice) {
            if (($invoice['Is_Education_Collectible'] ?? true) === false) {
                return false;
            }

            $status = strtoupper(trim((string) ($invoice['Status'] ?? '')));

            return ! in_array($status, ['DRAFT', 'CANCELLED', 'PAID'], true)
                && $this->invoiceRemaining($invoice) > 0;
        })->values();
        $overdueInvoices = $outstandingInvoices->filter(fn ($invoice) => $this->isPastDate($invoice['Due_Date'] ?? null));
        $pendingPayments = $this->betweenDates($payments, 'Payment_Date', $startDate, $endDate)
            ->filter(fn ($payment) => strtoupper(trim((string) ($payment['Status'] ?? ''))) === 'WAITING VERIFICATION');

        $kpi = [
            'active_students' => $students->count(),
            'new_students' => $newStudents,
            'active_employees' => $employees->count(),
            'active_teachers' => $teachers->count(),
            'active_companies' => $companies->count(),
            'income' => $income,
            'expense' => $expense,
            'net_cash_flow' => $income - $expense,
            'outstanding_amount' => $outstandingInvoices->sum(fn ($invoice) => $this->invoiceRemaining($invoice)),
            'overdue_invoices' => $overdueInvoices->count(),
            'pending_payments' => $pendingPayments->count(),
            'pending_approvals' => $pendingApprovals->count(),
        ];

        return [
            'filters' => $filters,
            'kpi' => $kpi,
            'attendance' => $attendanceSummary,
            'approvals' => $pendingApprovals->take(5)->map(fn ($row) => [
                'id' => $row['Approval_ID'] ?? '',
                'reference' => $row['Reference_ID'] ?? '-',
                'type' => $row['Reference_Type'] ?? $row['Module'] ?? 'Pengajuan',
                'requester' => $row['Requester_ID'] ?? '-',
                'priority' => $row['Priority'] ?? 'Normal',
                'submitted_at' => $this->approvalDate($row),
                'url' => ! empty($row['Approval_ID']) ? route('approvals.show', $row['Approval_ID']) : route('approvals.index'),
            ])->all(),
            'alerts' => $this->buildAlerts($overdueInvoices, $pendingPayments, $pendingApprovals),
            'distributions' => [
                'programs' => $this->distribution($students, 'Program_ID', $programs, 'Program_Name'),
                'batches' => $this->distribution($students, 'Batch_ID', $batches, 'Batch_Name'),
            ],
            'cash_flow_trend' => $this->cashFlowTrend($periodTransactions, $startDate, $endDate),
            'recent_activities' => $this->recentActivities(),
            'data_updated_at' => now(config('app.timezone', 'Asia/Jakarta')),
            'sources' => [
                'student' => 'MASTER_STUDENT',
                'finance' => 'FINANCE_TRANSACTION / FINANCE_INVOICE / FINANCE_PAYMENT',
                'hr' => 'MASTER_EMPLOYEE / MASTER_TEACHER / MASTER_ATTENDANCE',
                'marketing' => 'MASTER_COMPANY',
                'approval' => 'MASTER_APPROVAL',
            ],
        ];
    }

    public function report(string $division, array $filters): array
    {
        return match ($division) {
            'academic' => $this->academicReport($filters),
            'hr' => $this->hrReport($filters),
            'marketing' => $this->marketingReport($filters),
            'approvals' => $this->approvalReport($filters),
            default => $this->financeReport($filters),
        };
    }

    private function financeReport(array $filters): array
    {
        $rows = $this->betweenDates(
            $this->activeRows($this->transactions->fetchAll()),
            'Transaction_Date',
            $filters['start_date'],
            $filters['end_date']
        );
        $rows = $this->filterStatus($rows, $filters['status'], 'Type');
        $rows = $this->search($rows, $filters['search'], ['Transaction_ID', 'Account_ID', 'Type', 'Category', 'Description', 'Reference_ID']);

        return $this->reportPayload('Finance', $rows->sortByDesc('Transaction_Date')->values(), [
            'Transaction_Date' => 'Tanggal',
            'Transaction_ID' => 'ID Transaksi',
            'Type' => 'Tipe',
            'Category' => 'Kategori',
            'Description' => 'Keterangan',
            'Amount' => 'Nominal',
        ], 'FINANCE_TRANSACTION');
    }

    private function academicReport(array $filters): array
    {
        $programs = $this->activeRows($this->programs->fetchAll())->keyBy('Program_ID');
        $batches = $this->activeRows($this->batches->fetchAll())->keyBy('Batch_ID');
        $rows = collect($this->students->fetchAll())
            ->filter(fn ($row) => SheetValue::isOperationalStudent((array) $row))
            ->filter(fn ($row) => $this->dateInPeriod(
                $row['Registration_Date'] ?? $row['Created_At'] ?? null,
                $filters['start_date'],
                $filters['end_date']
            ));

        if ($filters['program_id'] !== '') {
            $rows = $rows->where('Program_ID', $filters['program_id']);
        }
        if ($filters['batch_id'] !== '') {
            $rows = $rows->where('Batch_ID', $filters['batch_id']);
        }
        $rows = $this->filterStatus($rows, $filters['status'], 'Enrollment_Status');
        $rows = $this->search($rows, $filters['search'], ['Student_ID', 'Student_Number', 'Full_Name', 'Enrollment_Status', 'Program_ID', 'Batch_ID', 'Class_ID']);
        $rows = $rows->map(function ($row) use ($programs, $batches) {
            $row['Program_Name'] = $programs->get($row['Program_ID'] ?? '')['Program_Name'] ?? ($row['Program_ID'] ?? '-');
            $row['Batch_Name'] = $batches->get($row['Batch_ID'] ?? '')['Batch_Name'] ?? ($row['Batch_ID'] ?? '-');

            return $row;
        });

        return $this->reportPayload('Academic', $rows->sortByDesc('Registration_Date')->values(), [
            'Student_ID' => 'ID Siswa',
            'Full_Name' => 'Nama',
            'Registration_Date' => 'Tanggal Daftar',
            'Enrollment_Status' => 'Status',
            'Program_Name' => 'Program',
            'Batch_Name' => 'Batch',
            'Class_ID' => 'Kelas',
        ], 'MASTER_STUDENT', [
            'programs' => $programs->values(),
            'batches' => $batches->values(),
        ]);
    }

    private function hrReport(array $filters): array
    {
        $employees = $this->activeEmployees($this->employees->fetchAll());
        $employeeMap = $employees->keyBy('Employee_ID');
        $departments = $this->activeRows($this->departments->fetchAll())->keyBy('Department_ID');
        $rows = $this->betweenDates(
            $this->activeRows($this->attendances->fetchAll()),
            'Attendance_Date',
            $filters['start_date'],
            $filters['end_date']
        )->filter(fn ($row) => $employeeMap->has(trim((string) ($row['Employee_ID'] ?? ''))));

        if ($filters['department_id'] !== '') {
            $rows = $rows->filter(fn ($row) => ($employeeMap->get($row['Employee_ID'] ?? '')['Department_ID'] ?? '') === $filters['department_id']);
        }
        if ($filters['status'] !== '') {
            $status = AttendanceStatusHelper::normalize($filters['status'], '');
            $rows = $rows->filter(fn ($row) => AttendanceStatusHelper::normalize($row['Status'] ?? '', '') === $status);
        }
        $rows = $rows->map(function ($row) use ($employeeMap, $departments) {
            $employee = $employeeMap->get($row['Employee_ID'] ?? '', []);
            $row['Employee_Name'] = $employee['Full_Name'] ?? ($row['Employee_ID'] ?? '-');
            $departmentId = $employee['Department_ID'] ?? '';
            $row['Department_Name'] = $departments->get($departmentId)['Department_Name'] ?? ($departmentId ?: '-');
            $row['Status_Label'] = AttendanceStatusHelper::label($row['Status'] ?? '');

            return $row;
        });
        $rows = $this->search($rows, $filters['search'], ['Employee_ID', 'Employee_Name', 'Department_Name', 'Status_Label']);

        return $this->reportPayload('HR & Kehadiran', $rows->sortByDesc(fn ($row) => ($row['Attendance_Date'] ?? '').' '.($row['Check_In_Time'] ?? ''))->values(), [
            'Attendance_Date' => 'Tanggal',
            'Employee_ID' => 'ID Pegawai',
            'Employee_Name' => 'Nama',
            'Department_Name' => 'Divisi',
            'Check_In_Time' => 'Masuk',
            'Check_Out_Time' => 'Pulang',
            'Status_Label' => 'Status',
        ], 'MASTER_ATTENDANCE + MASTER_EMPLOYEE', ['departments' => $departments->values()]);
    }

    private function marketingReport(array $filters): array
    {
        $rows = $this->activeRows($this->companies->fetchAll())
            ->map(function ($row) {
                $row['Status_Label'] = 'Aktif';

                return $row;
            });
        $rows = $this->search($rows, $filters['search'], ['Company_ID', 'Company_Name', 'City', 'Province', 'Country', 'Director_Name']);

        return $this->reportPayload('Marketing & Mitra', $rows->sortBy('Company_Name')->values(), [
            'Company_ID' => 'ID Perusahaan',
            'Company_Name' => 'Perusahaan',
            'City' => 'Kota',
            'Province' => 'Provinsi',
            'Country' => 'Negara',
            'Director_Name' => 'Direktur Perusahaan',
            'Status_Label' => 'Status',
        ], 'MASTER_COMPANY');
    }

    private function approvalReport(array $filters): array
    {
        $roleMap = $this->activeRows($this->roles->fetchAll())->keyBy('Role_ID');
        $directorActorIds = $this->activeRows($this->users->fetchAll())
            ->filter(function ($user) use ($roleMap) {
                $roleName = $user['Role'] ?? ($roleMap->get($user['Role_ID'] ?? '')['Role_Name'] ?? '');

                return strtoupper(trim((string) $roleName)) === 'DIRECTOR';
            })->flatMap(fn ($user) => array_filter([
                $user['User_ID'] ?? null,
                $user['Username'] ?? null,
                $user['Email'] ?? null,
            ]))->map(fn ($actor) => strtoupper(trim((string) $actor)))->flip();

        $directorDecisions = collect($this->approvalHistory->getAll())
            ->filter(fn ($history) => in_array(strtoupper(trim((string) ($history['Action'] ?? ''))), ['APPROVE', 'REJECT'], true))
            ->filter(fn ($history) => $directorActorIds->has(strtoupper(trim((string) ($history['Acted_By'] ?? $history['Performed_By'] ?? '')))))
            ->sortByDesc('Created_At')
            ->unique('Approval_ID')
            ->keyBy('Approval_ID');

        $rows = collect($this->approvals->getAll())
            ->filter(function ($row) use ($directorDecisions) {
                $currentApprover = strtoupper(trim((string) ($row['Current_Approver'] ?? $row['Current_Role'] ?? '')));

                return $currentApprover === 'DIRECTOR' || $directorDecisions->has($row['Approval_ID'] ?? '');
            })->map(function ($row) use ($directorDecisions) {
                $decision = $directorDecisions->get($row['Approval_ID'] ?? '');
                $row['Report_Date'] = $decision['Created_At'] ?? $this->approvalDate($row);
                $row['Decision_By'] = $decision['Acted_By'] ?? $decision['Performed_By'] ?? '-';

                return $row;
            })->filter(fn ($row) => $this->dateInPeriod($row['Report_Date'] ?? null, $filters['start_date'], $filters['end_date']));
        if ($filters['status'] !== '') {
            $rows = $this->filterStatus($rows, $filters['status'], 'Status');
        }
        $rows = $this->search($rows, $filters['search'], ['Approval_ID', 'Module', 'Reference_Type', 'Reference_ID', 'Requester_ID', 'Status', 'Priority']);

        return $this->reportPayload('Persetujuan', $rows->sortByDesc('Report_Date')->values(), [
            'Approval_ID' => 'ID Persetujuan',
            'Reference_Type' => 'Jenis',
            'Reference_ID' => 'Referensi',
            'Requester_ID' => 'Pengaju',
            'Status' => 'Status',
            'Priority' => 'Prioritas',
            'Decision_By' => 'Aktor Keputusan',
            'Report_Date' => 'Waktu Pengajuan/Keputusan',
        ], 'MASTER_APPROVAL + MASTER_APPROVAL_HISTORY + MASTER_USER + MASTER_ROLE');
    }

    private function reportPayload(string $title, Collection $rows, array $columns, string $source, array $options = []): array
    {
        return compact('title', 'rows', 'columns', 'source', 'options');
    }

    private function activeRows(iterable $rows): Collection
    {
        return collect($rows)->filter(fn ($row) => strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE')->values();
    }

    private function activeEmployees(iterable $rows): Collection
    {
        return $this->activeRows($rows)->filter(function ($row) {
            return ! in_array(strtoupper(trim((string) ($row['Employment_Status'] ?? 'ACTIVE'))), ['INACTIVE', 'RESIGNED', 'TERMINATED'], true);
        })->values();
    }

    private function betweenDates(Collection $rows, string $field, string $startDate, string $endDate): Collection
    {
        return $rows->filter(fn ($row) => $this->dateInPeriod($row[$field] ?? null, $startDate, $endDate))->values();
    }

    private function dateInPeriod(mixed $value, string $startDate, string $endDate): bool
    {
        $date = $this->validDate($value);

        return $date !== null && $date >= $startDate && $date <= $endDate;
    }

    private function validDate(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', substr($raw, 0, 10), config('app.timezone', 'Asia/Jakarta'));
            $errors = Carbon::getLastErrors();
            if (! $date || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
                return null;
            }

            return $date->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function sumTransactions(Collection $transactions, string $type): float
    {
        return (float) $transactions
            ->filter(fn ($row) => $this->transactionType($row['Type'] ?? null) === $type)
            ->sum(fn ($row) => Money::value($row['Amount'] ?? null, 'Nominal transaksi'));
    }

    private function transactionType(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['income', 'pemasukan', 'masuk', 'revenue', 'pendapatan'], true)) {
            return 'Income';
        }
        if (in_array($value, ['expense', 'pengeluaran', 'keluar', 'cost', 'biaya', 'beban'], true)) {
            return 'Expense';
        }

        return null;
    }

    private function invoiceRemaining(array $invoice): float
    {
        if (isset($invoice['Remaining_Amount']) && is_numeric($invoice['Remaining_Amount'])) {
            return max(0, Money::value($invoice['Remaining_Amount'], 'Sisa invoice'));
        }

        $amount = Money::value($invoice['Amount'] ?? 0, 'Total invoice');
        $paid = Money::value($invoice['Paid_Amount'] ?? 0, 'Pembayaran invoice');

        return max(0, round($amount - $paid, Money::SCALE));
    }

    private function isPastDate(mixed $value): bool
    {
        $date = $this->validDate($value);

        return $date !== null && $date < now(config('app.timezone', 'Asia/Jakarta'))->toDateString();
    }

    private function isDirectorPendingApproval(array $row): bool
    {
        $approver = strtoupper(trim((string) ($row['Current_Approver'] ?? $row['Current_Role'] ?? '')));

        return $approver === 'DIRECTOR' && trim((string) ($row['Status'] ?? '')) === 'Waiting Approval';
    }

    private function approvalDate(array $row): string
    {
        return (string) ($row['Submitted_At'] ?? $row['Created_At'] ?? '');
    }

    private function attendanceSummary(Collection $rows): array
    {
        $counts = ['present' => 0, 'late' => 0, 'sick' => 0, 'permitted' => 0, 'absent' => 0];
        foreach ($rows as $row) {
            $key = strtolower(AttendanceStatusHelper::normalize($row['Status'] ?? ''));
            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return ['total' => $rows->count()] + $counts;
    }

    private function distribution(Collection $rows, string $foreignKey, Collection $lookup, string $nameKey): array
    {
        return $rows->groupBy(fn ($row) => trim((string) ($row[$foreignKey] ?? '')) ?: '__unknown')
            ->map(fn ($group, $id) => [
                'label' => $id === '__unknown' ? 'Belum ditetapkan' : ($lookup->get($id)[$nameKey] ?? $id),
                'value' => $group->count(),
            ])->sortByDesc('value')->values()->all();
    }

    private function cashFlowTrend(Collection $transactions, string $startDate, string $endDate): array
    {
        $months = collect();
        $cursor = Carbon::parse($startDate)->startOfMonth();
        $last = Carbon::parse($endDate)->startOfMonth();
        while ($cursor <= $last && $months->count() < 12) {
            $months->push($cursor->copy());
            $cursor->addMonth();
        }

        return $months->map(function (Carbon $month) use ($transactions) {
            $key = $month->format('Y-m');
            $monthRows = $transactions->filter(fn ($row) => str_starts_with((string) ($row['Transaction_Date'] ?? ''), $key));

            return [
                'label' => $month->translatedFormat('M Y'),
                'income' => $this->sumTransactions($monthRows, 'Income'),
                'expense' => $this->sumTransactions($monthRows, 'Expense'),
            ];
        })->all();
    }

    private function buildAlerts(Collection $overdueInvoices, Collection $pendingPayments, Collection $pendingApprovals): array
    {
        $alerts = [];
        if ($pendingApprovals->isNotEmpty()) {
            $alerts[] = ['level' => 'high', 'title' => 'Keputusan menunggu Direktur', 'description' => $pendingApprovals->count().' pengajuan masih menunggu keputusan.', 'url' => route('approvals.index')];
        }
        if ($overdueInvoices->isNotEmpty()) {
            $alerts[] = ['level' => 'high', 'title' => 'Piutang melewati jatuh tempo', 'description' => $overdueInvoices->count().' invoice aktif telah melewati Due_Date.', 'url' => route('reports.finance.outstanding')];
        }
        if ($pendingPayments->isNotEmpty()) {
            $alerts[] = ['level' => 'normal', 'title' => 'Verifikasi pembayaran', 'description' => $pendingPayments->count().' pembayaran pada periode ini menunggu verifikasi Finance.', 'url' => route('director.reports', ['division' => 'finance'])];
        }

        return $alerts;
    }

    private function recentActivities(): array
    {
        try {
            return collect($this->activityLogs->fetchAll())
                ->sortByDesc('Created_At')->take(8)->map(function ($row) {
                    $description = trim((string) ($row['Description'] ?? ''));
                    if ($description === '') {
                        $description = trim((string) ($row['Action'] ?? 'Aktivitas'))
                            .(! empty($row['Reference_ID']) ? ' pada '.$row['Reference_ID'] : '');
                    }

                    return [
                        'module' => $row['Module'] ?? 'SYSTEM',
                        'title' => $row['Action'] ?? 'Aktivitas',
                        'description' => mb_strimwidth(strip_tags($description), 0, 180, '…'),
                        'occurred_at' => $row['Created_At'] ?? null,
                    ];
                })->values()->all();
        } catch (\Throwable $e) {
            Log::warning('director.dashboard_activity_unavailable', ['exception' => get_class($e)]);

            return [];
        }
    }

    private function filterStatus(Collection $rows, string $status, string $field): Collection
    {
        if ($status === '') {
            return $rows;
        }

        return $rows->filter(fn ($row) => strcasecmp(trim((string) ($row[$field] ?? '')), $status) === 0)->values();
    }

    private function search(Collection $rows, string $search, array $fields): Collection
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return $rows;
        }

        return $rows->filter(function ($row) use ($needle, $fields) {
            foreach ($fields as $field) {
                if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $needle)) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
