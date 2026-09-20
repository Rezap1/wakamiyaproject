<?php

namespace App\Services\Finance;

use App\Exceptions\FinancialIntegrityException;
use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Support\Finance\AcceptedPaymentCalculator;
use App\Support\Finance\Money;
use App\Support\Finance\PaymentStatus;
use App\Support\Presentation\IndonesianPresentation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EducationPaymentMonitoringService
{
    public const STATUS_LABELS = [
        'fee_unset' => 'Biaya Belum Ditetapkan',
        'unpaid' => 'Belum Bayar',
        'partial' => 'Cicilan',
        'paid' => 'Lunas',
    ];

    public function __construct(
        private StudentRepositoryInterface $studentRepository,
        private ClassRepositoryInterface $classRepository,
        private ProgramRepositoryInterface $programRepository,
        private BatchRepositoryInterface $batchRepository,
        private InvoiceRepositoryInterface $invoiceRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private InvoiceService $invoiceService,
    ) {}

    /** Build a filtered, read-only projection from one snapshot of each SSOT. */
    public function build(array $filters = []): array
    {
        return $this->buildFromSnapshots($this->snapshots(), $filters);
    }

    /** Build the administrator detail projection; null is a fail-closed lookup. */
    public function detail(string $studentId): ?array
    {
        $studentId = trim($studentId);
        if ($studentId === '') {
            return null;
        }

        $snapshots = $this->snapshots();
        // Historical ownership survives placement, enrolment and login changes.
        $projection = $this->buildFromSnapshots($snapshots, [], true);
        $student = $projection['groups']
            ->flatMap(fn ($group) => $group['students'])
            ->firstWhere('student_id', $studentId);
        if (! $student) {
            return null;
        }

        $educationInvoicesById = $this->educationInvoicesById($snapshots['invoices']);
        $educationSnapshot = AcceptedPaymentCalculator::educationSnapshot(
            $snapshots['payments'],
            $educationInvoicesById->all(),
            $snapshots['invoices']->pluck('Invoice_ID')->all(),
        );
        $acceptedByInvoice = $this->invoiceService->acceptedPaymentTotalsByInvoice(
            $snapshots['payments'],
            $educationInvoicesById->keys()->all(),
        );
        $formattedInvoices = $educationInvoicesById->map(fn ($invoice) => $this->invoiceService->formatInvoiceRecord(
            (array) $invoice,
            $acceptedByInvoice,
            $snapshots['payments'],
        )
        );

        $history = collect($educationSnapshot['payments'])
            ->where('Student_ID', $studentId)
            ->map(function ($payment) use ($formattedInvoices) {
                $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
                $invoice = $invoiceId !== '' ? $formattedInvoices->get($invoiceId) : null;
                $method = trim((string) ($payment['Payment_Method'] ?? ''));
                $status = PaymentStatus::canonical($payment['Status'] ?? null);

                return [
                    'payment_id' => $payment['Payment_ID'] ?? '-',
                    'reference' => trim((string) ($payment['Reference_Number'] ?? '')) ?: '-',
                    'payment_date' => $payment['Payment_Date'] ?? null,
                    'amount' => Money::value($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran pendidikan'),
                    'source' => $payment['Education_Source'],
                    'source_label' => $payment['Education_Source'] === AcceptedPaymentCalculator::EDUCATION_SOURCE_SELF_SERVICE
                        ? 'Bayar Mandiri'
                        : 'Tagihan dari Master',
                    'method_label' => $method === '' ? '-' : IndonesianPresentation::paymentMethod($method),
                    'status' => $status,
                    'status_label' => IndonesianPresentation::paymentStatus($status),
                    'verified_at' => $payment['Verified_At'] ?? null,
                    'created_at' => $payment['Created_At'] ?? null,
                    'notes' => trim((string) ($payment['Notes'] ?? '')) ?: '-',
                    'invoice_id' => $invoiceId !== '' ? $invoiceId : null,
                    'invoice_amount' => $invoice['Amount'] ?? null,
                    'invoice_status' => $invoice['Status'] ?? null,
                    'invoice_status_label' => $invoice
                        ? IndonesianPresentation::status($invoice['Status'] ?? null)
                        : '-',
                ];
            })
            ->sortByDesc(fn ($payment) => implode('|', [
                $payment['payment_date'] ?? '',
                $payment['verified_at'] ?? '',
                $payment['created_at'] ?? '',
                $payment['payment_id'],
            ]))
            ->values();

        $student['verified_payment_count'] = $history
            ->filter(fn ($payment) => PaymentStatus::verified($payment['status'] ?? null))
            ->count();

        return ['student' => $student, 'history' => $history];
    }

    private function snapshots(): array
    {
        return [
            'students' => collect($this->studentRepository->fetchAll()),
            'classes' => collect($this->classRepository->fetchAll()),
            'programs' => collect($this->programRepository->fetchAll()),
            'batches' => collect($this->batchRepository->fetchAll()),
            'invoices' => collect($this->invoiceRepository->getAll()),
            'payments' => collect($this->paymentRepository->getAll()),
        ];
    }

    private function buildFromSnapshots(array $snapshots, array $filters = [], bool $includeHistorical = false): array
    {
        $allClasses = $snapshots['classes']->keyBy(fn ($class) => trim((string) ($class['Class_ID'] ?? '')));
        $activeClasses = $snapshots['classes']
            ->filter(fn ($class) => SheetValue::isActive((array) $class))
            ->filter(fn ($class) => trim((string) ($class['Class_ID'] ?? '')) !== '')
            ->unique(fn ($class) => trim((string) $class['Class_ID']))
            ->keyBy(fn ($class) => trim((string) $class['Class_ID']));
        $programsById = $snapshots['programs']->keyBy('Program_ID');
        $batchesById = $snapshots['batches']->keyBy('Batch_ID');

        $operationalStudents = $snapshots['students']
            ->filter(fn ($student) => $includeHistorical || SheetValue::isOperationalStudent((array) $student))
            ->filter(function ($student) use ($activeClasses, $includeHistorical) {
                $studentId = trim((string) ($student['Student_ID'] ?? ''));
                $classId = trim((string) ($student['Class_ID'] ?? ''));

                return $studentId !== '' && ($includeHistorical || ($classId !== '' && $activeClasses->has($classId)));
            })
            ->unique(fn ($student) => trim((string) $student['Student_ID']))
            ->values();

        $educationInvoicesById = $this->educationInvoicesById($snapshots['invoices']);
        $educationSnapshot = AcceptedPaymentCalculator::educationSnapshot(
            $snapshots['payments'],
            $educationInvoicesById->all(),
            $snapshots['invoices']->pluck('Invoice_ID')->all(),
        );

        $studentIds = $snapshots['students']->map(fn ($student) => trim((string) ($student['Student_ID'] ?? '')))->all();
        foreach (array_keys($educationSnapshot['totals_by_student']) as $ownerId) {
            if (! in_array((string) $ownerId, $studentIds, true)) {
                throw new FinancialIntegrityException("Student_ID #{$ownerId} pada pembayaran pendidikan tidak ditemukan.");
            }
        }

        $rows = $operationalStudents->map(function ($student) use (

            $allClasses,
            $programsById,
            $batchesById,
            $snapshots,
            $educationSnapshot,
        ) {
            $studentId = trim((string) $student['Student_ID']);
            $classId = trim((string) ($student['Class_ID'] ?? ''));
            $class = (array) $allClasses->get($classId);
            $program = (array) $programsById->get($student['Program_ID'] ?? '');
            $batch = (array) $batchesById->get($student['Batch_ID'] ?? '');
            $educationFee = $this->invoiceService->getStudentTuitionFee(
                $studentId,
                (array) $student,
                $snapshots['programs'],
                $snapshots['batches'],
            );
            $paid = (float) ($educationSnapshot['totals_by_student'][$studentId] ?? 0.0);
            $remaining = max(0.0, round($educationFee - $paid, Money::SCALE));
            $status = $this->summaryStatus($educationFee, $paid);

            return [
                'student_id' => $studentId,
                'student_number' => $student['Student_Number'] ?? $student['NIS'] ?? '-',
                'student_name' => $student['Full_Name'] ?? $student['Name'] ?? $studentId,
                'class_id' => $classId,
                'class_name' => $class['Class_Name'] ?? $class['Class_Code'] ?? ($classId ?: '-'),
                'program_name' => $program['Program_Name'] ?? '-',
                'batch_name' => $batch['Batch_Name'] ?? '-',
                'education_fee' => (float) $educationFee,
                'paid' => $paid,
                'remaining' => $remaining,
                'excess' => max(0.0, round($paid - $educationFee, Money::SCALE)),
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status],
            ];
        });

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = Str::lower($search);
            $rows = $rows->filter(fn ($row) => Str::contains(
                Str::lower(implode(' ', [$row['student_id'], $row['student_number'], $row['student_name']])),
                $needle,
            ));
        }

        $classId = trim((string) ($filters['class_id'] ?? ''));
        if ($classId !== '') {
            $rows = $rows->where('class_id', $classId);
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $rows = $rows->where('status', $status);
        }

        $rows = $rows->sortBy(fn ($row) => Str::lower((string) $row['student_name']))->values();
        $groups = $rows
            ->groupBy('class_id')
            ->map(function (Collection $classRows) {
                $first = $classRows->first();

                return [
                    'class_id' => $first['class_id'],
                    'class_name' => $first['class_name'],
                    'students' => $classRows->values(),
                ];
            })
            ->sortBy(fn ($group) => Str::lower((string) $group['class_name']))
            ->values();

        return [
            'groups' => $groups,
            'classOptions' => $activeClasses
                ->map(fn ($class, $id) => [
                    'id' => $id,
                    'name' => $class['Class_Name'] ?? $class['Class_Code'] ?? $id,
                ])
                ->sortBy(fn ($class) => Str::lower((string) $class['name']))
                ->values(),
            'statusOptions' => self::STATUS_LABELS,
            'filters' => ['search' => $search, 'class_id' => $classId, 'status' => $status],
            'kpi' => [
                'students' => $rows->count(),
                'paid_students' => $rows->where('status', 'paid')->count(),
                'partial_students' => $rows->where('status', 'partial')->count(),
                'unpaid_students' => $rows->where('status', 'unpaid')->count(),
                'fee_unset_students' => $rows->where('status', 'fee_unset')->count(),
                'education_fee' => (float) $rows->sum('education_fee'),
                'paid' => (float) $rows->sum('paid'),
                'remaining' => (float) $rows->sum('remaining'),
            ],
        ];
    }

    private function educationInvoicesById(Collection $invoices): Collection
    {
        // Lifecycle controls collection of new money, never erases verified
        // cash history. Keep cancelled/inactive/replaced invoice evidence.
        return collect($this->invoiceService->educationInvoicesById($invoices));
    }

    private function summaryStatus(float $educationFee, float $paid): string
    {
        if ($educationFee <= 0) {
            return 'fee_unset';
        }
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $educationFee ? 'paid' : 'partial';
    }
}
