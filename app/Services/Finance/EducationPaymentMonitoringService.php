<?php

namespace App\Services\Finance;

use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EducationPaymentMonitoringService
{
    public const STATUS_LABELS = [
        'no_invoice' => 'Belum Ada Tagihan',
        'unpaid' => 'Belum Bayar',
        'partial' => 'Cicilan',
        'paid' => 'Lunas',
    ];

    public function __construct(
        private StudentRepositoryInterface $studentRepository,
        private ClassRepositoryInterface $classRepository,
        private InvoiceRepositoryInterface $invoiceRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private InvoiceService $invoiceService,
    ) {
    }

    /**
     * Build a read-only projection from one snapshot of each Google Sheets SSOT.
     */
    public function build(array $filters = []): array
    {
        $students = collect($this->studentRepository->fetchAll());
        $classes = collect($this->classRepository->fetchAll());
        $invoices = collect($this->invoiceRepository->getAll());
        $payments = collect($this->paymentRepository->getAll());

        $activeClasses = $classes
            ->filter(fn ($class) => SheetValue::isActive((array) $class))
            ->filter(fn ($class) => trim((string) ($class['Class_ID'] ?? '')) !== '')
            ->unique(fn ($class) => trim((string) $class['Class_ID']))
            ->keyBy(fn ($class) => trim((string) $class['Class_ID']));

        $operationalStudents = $students
            ->filter(fn ($student) => SheetValue::isOperationalStudent((array) $student))
            ->filter(function ($student) use ($activeClasses) {
                $studentId = trim((string) ($student['Student_ID'] ?? ''));
                $classId = trim((string) ($student['Class_ID'] ?? ''));

                return $studentId !== '' && $classId !== '' && $activeClasses->has($classId);
            })
            ->unique(fn ($student) => trim((string) $student['Student_ID']))
            ->values();

        $educationInvoices = $invoices
            ->filter(fn ($invoice) => $this->isPublishedEducationInvoice((array) $invoice))
            ->filter(fn ($invoice) => trim((string) ($invoice['Invoice_ID'] ?? '')) !== '')
            ->unique(fn ($invoice) => trim((string) $invoice['Invoice_ID']))
            ->values();

        $invoiceIds = $educationInvoices
            ->pluck('Invoice_ID')
            ->map(fn ($id) => trim((string) $id))
            ->all();
        $acceptedTotals = $this->invoiceService->acceptedPaymentTotalsByInvoice($payments, $invoiceIds);

        $formattedInvoicesByStudent = $educationInvoices
            ->map(fn ($invoice) => $this->invoiceService->formatInvoiceRecord(
                (array) $invoice,
                $acceptedTotals,
                $payments,
            ))
            ->groupBy(fn ($invoice) => trim((string) ($invoice['Student_ID'] ?? '')));

        $rows = $operationalStudents
            ->map(function ($student) use ($activeClasses, $formattedInvoicesByStudent) {
                $studentId = trim((string) $student['Student_ID']);
                $classId = trim((string) $student['Class_ID']);
                $class = (array) $activeClasses->get($classId);
                $studentInvoices = collect($formattedInvoicesByStudent->get($studentId, collect()));
                $total = (float) $studentInvoices->sum('Amount');
                $paid = (float) $studentInvoices->sum('Paid_Amount');
                $remaining = (float) $studentInvoices->sum('Remaining_Amount');
                $status = $this->summaryStatus($studentInvoices, $paid, $remaining);

                return [
                    'student_id' => $studentId,
                    'student_number' => $student['Student_Number'] ?? $student['NIS'] ?? '-',
                    'student_name' => $student['Full_Name'] ?? $student['Name'] ?? $studentId,
                    'class_id' => $classId,
                    'class_name' => $class['Class_Name'] ?? $class['Class_Code'] ?? $classId,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'status' => $status,
                    'status_label' => self::STATUS_LABELS[$status],
                    'invoice_count' => $studentInvoices->count(),
                    'detail_invoice_id' => $studentInvoices->count() === 1
                        ? ($studentInvoices->first()['Invoice_ID'] ?? null)
                        : null,
                ];
            });

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = Str::lower($search);
            $rows = $rows->filter(function ($row) use ($needle) {
                $haystack = Str::lower(implode(' ', [
                    $row['student_id'],
                    $row['student_number'],
                    $row['student_name'],
                ]));

                return Str::contains($haystack, $needle);
            });
        }

        $classId = trim((string) ($filters['class_id'] ?? ''));
        if ($classId !== '') {
            $rows = $rows->where('class_id', $classId);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $rows = $rows->where('status', $status);
        }

        $rows = $rows
            ->sortBy(fn ($row) => Str::lower((string) $row['student_name']))
            ->values();

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
            'filters' => [
                'search' => $search,
                'class_id' => $classId,
                'status' => $status,
            ],
            'kpi' => [
                'students' => $rows->count(),
                'paid_students' => $rows->where('status', 'paid')->count(),
                'partial_students' => $rows->where('status', 'partial')->count(),
                'unpaid_students' => $rows->where('status', 'unpaid')->count(),
                'no_invoice_students' => $rows->where('status', 'no_invoice')->count(),
                'total' => (float) $rows->sum('total'),
                'paid' => (float) $rows->sum('paid'),
                'remaining' => (float) $rows->sum('remaining'),
            ],
        ];
    }

    private function isPublishedEducationInvoice(array $invoice): bool
    {
        $status = strtolower(trim((string) ($invoice['Status'] ?? '')));
        $invoiceType = strtoupper(trim((string) ($invoice['Invoice_Type'] ?? 'STUDENT')));

        return SheetValue::isActive($invoice)
            && $invoiceType === 'STUDENT'
            && in_array($status, ['waiting payment', 'partial paid', 'paid', 'overdue'], true)
            && $this->invoiceService->isEducationInvoice($invoice);
    }

    private function summaryStatus(Collection $invoices, float $paid, float $remaining): string
    {
        if ($invoices->isEmpty()) {
            return 'no_invoice';
        }

        if ($remaining <= 0) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'unpaid';
    }
}
