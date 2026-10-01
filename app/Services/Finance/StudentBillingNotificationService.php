<?php

namespace App\Services\Finance;

use App\Helpers\DateHelper;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class StudentBillingNotificationService
{
    public const TYPE = 'FINANCE_INVOICE_REMINDER';

    public const ERROR_NOT_STUDENT_INVOICE = 42201;

    public const ERROR_PAID = 40901;

    public const ERROR_INACTIVE = 40902;

    public const ERROR_UNSUPPORTED_STATUS = 40903;

    private const DUPLICATE_COOLDOWN_MINUTES = 2;

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly NotificationService $notificationService,
        private readonly StudentRepositoryInterface $studentRepository,
    ) {}

    public function sendReminder(string $invoiceId): array
    {
        $invoice = $this->invoiceService->getById($invoiceId);
        if (! $invoice) {
            throw new \RuntimeException('Invoice tidak ditemukan.');
        }

        $studentId = trim((string) ($invoice['Student_ID'] ?? ''));
        if (strtoupper(trim((string) ($invoice['Invoice_Type'] ?? 'STUDENT'))) !== 'STUDENT' || $studentId === '') {
            throw new \DomainException(
                'Pengingat pembayaran hanya dapat dikirim untuk invoice siswa yang memiliki Student_ID.',
                self::ERROR_NOT_STUDENT_INVOICE,
            );
        }

        $status = $this->canonicalStatus($invoice);
        if ($status === 'paid' || (float) ($invoice['Remaining_Amount'] ?? 0) <= 0) {
            throw new \DomainException(
                'Tagihan siswa sudah lunas dan tidak memerlukan notifikasi pembayaran.',
                self::ERROR_PAID,
            );
        }
        if (in_array($status, ['cancelled', 'void', 'draft'], true)) {
            throw new \DomainException(
                'Tagihan yang dibatalkan, void, atau masih draft tidak dapat menerima notifikasi pembayaran.',
                self::ERROR_INACTIVE,
            );
        }
        if (! in_array($status, ['waiting payment', 'partial paid', 'overdue'], true)) {
            throw new \DomainException(
                'Status tagihan tidak mendukung notifikasi pembayaran.',
                self::ERROR_UNSUPPORTED_STATUS,
            );
        }

        $student = $this->studentRepository->findById($studentId);
        if (! $student) {
            throw new \RuntimeException("Data siswa {$studentId} tidak ditemukan.");
        }

        $payload = $this->notificationPayload($invoice, (array) $student);
        $lockKey = 'billing_notification_send_'.hash('sha256', $payload['User_ID'].'|'.$payload['Link']);

        return Cache::lock($lockKey, 15)->block(5, function () use ($payload, $invoice, $student) {
            if ($this->hasRecentDuplicate($payload)) {
                return [
                    'created' => false,
                    'duplicate' => true,
                    'invoice' => $invoice,
                    'student' => (array) $student,
                    'notification' => $payload,
                ];
            }

            $this->notificationService->CreateNotification($payload);

            return [
                'created' => true,
                'duplicate' => false,
                'invoice' => $invoice,
                'student' => (array) $student,
                'notification' => $payload,
            ];
        });
    }

    public function contextForNotification(array $notification): ?array
    {
        $invoiceId = $this->invoiceIdFromNotification($notification);
        if ($invoiceId === null) {
            return null;
        }

        $invoice = $this->invoiceService->getById($invoiceId);
        if (! $invoice) {
            return $this->unavailableContext($notification, $invoiceId);
        }

        $user = auth()->user();
        $student = $user
            ? collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID)
            : null;
        if (! $student || trim((string) ($invoice['Student_ID'] ?? '')) !== trim((string) ($student['Student_ID'] ?? ''))) {
            abort(403, 'Akses Ditolak: tagihan pada notifikasi ini bukan milik akun Anda.');
        }

        return $this->presentContext($notification, $invoice, (array) $student);
    }

    /**
     * Enrich one notification page with one student read and one invoice read.
     * The returned map is keyed by Notification_ID to keep Blade lookup O(1).
     */
    public function contextsForNotifications(iterable $notifications): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $role = strtoupper(trim((string) ($user->Role ?? '')));
        if ($role !== '' && ! str_contains($role, 'STUDENT')) {
            return [];
        }

        $student = collect($this->studentRepository->fetchAll())
            ->firstWhere('User_ID', $user->User_ID ?? auth()->id());
        if (! $student || empty($student['Student_ID'])) {
            return [];
        }

        $invoiceMap = collect($this->invoiceService->getAll())
            ->keyBy(fn ($invoice) => trim((string) ($invoice['Invoice_ID'] ?? '')));
        $contexts = [];

        foreach ($notifications as $notification) {
            $notification = (array) $notification;
            $invoiceId = $this->invoiceIdFromNotification($notification);
            $notificationId = trim((string) ($notification['Notification_ID'] ?? ''));
            if ($invoiceId === null || $notificationId === '') {
                continue;
            }

            $invoice = $invoiceMap->get($invoiceId);
            if (! $invoice) {
                $contexts[$notificationId] = $this->unavailableContext($notification, $invoiceId);

                continue;
            }
            $invoice = (array) $invoice;
            if (trim((string) ($invoice['Student_ID'] ?? '')) !== trim((string) $student['Student_ID'])) {
                continue;
            }

            $contexts[$notificationId] = $this->presentContext($notification, $invoice, (array) $student);
        }

        return $contexts;
    }

    public function popupFromSnapshots(iterable $notifications, iterable $invoices, array $student): array
    {
        $invoiceMap = collect($invoices)->keyBy(fn ($invoice) => trim((string) ($invoice['Invoice_ID'] ?? '')));
        $actionable = collect($notifications)
            ->filter(fn ($notification) => $this->notificationService->isForUser($notification, auth()->user()))
            ->filter(fn ($notification) => strtoupper(trim((string) ($notification['Is_Read'] ?? 'FALSE'))) !== 'TRUE')
            ->filter(fn ($notification) => strtolower(trim((string) ($notification['Status'] ?? ''))) !== 'archived')
            ->sortByDesc(fn ($notification) => $this->timestamp($notification['Created_At'] ?? null))
            ->map(function ($notification) use ($invoiceMap, $student) {
                $invoiceId = $this->invoiceIdFromNotification((array) $notification);
                if ($invoiceId === null || ! $invoiceMap->has($invoiceId)) {
                    return null;
                }

                $invoice = (array) $invoiceMap->get($invoiceId);
                if (trim((string) ($invoice['Student_ID'] ?? '')) !== trim((string) ($student['Student_ID'] ?? ''))
                    || ! $this->isActionable($invoice)) {
                    return null;
                }

                return $this->presentContext((array) $notification, $invoice, $student);
            })
            ->filter()
            ->values();

        return [
            'current' => $actionable->first(),
            'other_count' => max(0, $actionable->count() - 1),
        ];
    }

    public function invoiceIdFromNotification(array $notification): ?string
    {
        $title = trim((string) ($notification['Title'] ?? ''));
        $link = trim((string) ($notification['Link'] ?? $notification['Action_URL'] ?? ''));
        if ($title === '' || ! str_starts_with(strtolower($title), 'tagihan ') || $link === '') {
            return null;
        }

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        if (($query['notification_type'] ?? null) !== self::TYPE) {
            return null;
        }

        $path = parse_url($link, PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('#^/student/billing/([^/]+)$#', $path, $matches)) {
            return null;
        }

        $invoiceId = trim(rawurldecode($matches[1]));

        return $invoiceId !== '' ? $invoiceId : null;
    }

    private function notificationPayload(array $invoice, array $student): array
    {
        $invoiceId = trim((string) $invoice['Invoice_ID']);
        $studentName = trim((string) ($student['Full_Name'] ?? $student['Student_Name'] ?? 'Siswa'));
        $purpose = $this->purpose($invoice);
        $total = (float) ($invoice['Grand_Total'] ?? $invoice['Amount'] ?? 0);
        $paid = (float) ($invoice['Paid_Amount'] ?? 0);
        $remaining = (float) ($invoice['Remaining_Amount'] ?? 0);
        $dueDate = DateHelper::format($invoice['Due_Date'] ?? null, 'j F Y');
        $instruction = $paid > 0
            ? "Silakan melakukan pembayaran angsuran {$this->lowerPurpose($purpose)} melalui menu pembayaran."
            : "Silakan melakukan pembayaran {$this->lowerPurpose($purpose)} melalui menu pembayaran.";

        $message = implode("\n", [
            "Halo {$studentName},",
            '',
            "Terdapat tagihan {$purpose} sebesar {$this->rupiah($total)}.",
            "Total Tagihan: {$this->rupiah($total)}",
            "Sudah Dibayar: {$this->rupiah($paid)}",
            "Sisa Pembayaran: {$this->rupiah($remaining)}",
            "Jatuh Tempo: {$dueDate}",
            '',
            $instruction,
            '',
            "Invoice: {$invoiceId}",
        ]);

        return [
            'User_ID' => trim((string) ($student['User_ID'] ?? '')) ?: trim((string) $invoice['Student_ID']),
            'Title' => "Tagihan {$purpose}",
            'Message' => $message,
            'Notification_Type' => self::TYPE,
            'Priority' => 'High',
            'Is_Read' => 'FALSE',
            // MASTER_NOTIFICATION has no type/reference columns. Persist both
            // semantics in its existing action Link without changing schema.
            'Link' => route('student.billing.show', $invoiceId, false)
                .'?notification_type='.self::TYPE,
            'Created_At' => now('Asia/Jakarta')->toDateTimeString(),
        ];
    }

    private function hasRecentDuplicate(array $payload): bool
    {
        $cutoff = now('Asia/Jakarta')->subMinutes(self::DUPLICATE_COOLDOWN_MINUTES);

        $invoiceId = $this->invoiceIdFromNotification($payload);

        return $this->notificationService->getAllFresh()->contains(function ($row) use ($payload, $cutoff, $invoiceId) {
            if (trim((string) ($row['User_ID'] ?? '')) !== $payload['User_ID']
                || $invoiceId === null
                || $this->invoiceIdFromNotification((array) $row) !== $invoiceId) {
                return false;
            }

            try {
                return Carbon::parse($row['Created_At'] ?? null, 'Asia/Jakarta')->greaterThanOrEqualTo($cutoff);
            } catch (\Throwable) {
                return false;
            }
        });
    }

    private function presentContext(array $notification, array $invoice, array $student): array
    {
        $status = $this->canonicalStatus($invoice);
        $paid = (float) ($invoice['Paid_Amount'] ?? 0);
        $purpose = $this->purpose($invoice);
        $due = $invoice['Due_Date'] ?? null;
        $overdue = $this->isActionable($invoice) && $due && Carbon::parse($due, 'Asia/Jakarta')->startOfDay()->isBefore(now('Asia/Jakarta')->startOfDay());

        return [
            'notification' => $notification,
            'type' => self::TYPE,
            'invoice_id' => trim((string) ($invoice['Invoice_ID'] ?? '')),
            'student_name' => trim((string) ($student['Full_Name'] ?? $student['Student_Name'] ?? 'Siswa')),
            'purpose' => $purpose,
            'title' => "Tagihan {$purpose}",
            'total' => (float) ($invoice['Grand_Total'] ?? $invoice['Amount'] ?? 0),
            'paid' => $paid,
            'remaining' => (float) ($invoice['Remaining_Amount'] ?? 0),
            'due_date' => DateHelper::format($due, 'j F Y'),
            'status' => $status,
            'actionable' => $this->isActionable($invoice),
            'overdue' => $overdue,
            'instruction' => $paid > 0
                ? "Silakan melakukan pembayaran angsuran {$this->lowerPurpose($purpose)} melalui menu pembayaran."
                : "Silakan melakukan pembayaran {$this->lowerPurpose($purpose)} melalui menu pembayaran.",
            'payment_url' => route('student.billing.show', $invoice['Invoice_ID']),
            'created_at' => DateHelper::format($notification['Created_At'] ?? null, 'j F Y • H:i').' WIB',
        ];
    }

    private function unavailableContext(array $notification, string $invoiceId): array
    {
        return [
            'notification' => $notification,
            'type' => self::TYPE,
            'invoice_id' => $invoiceId,
            'title' => 'Tagihan tidak tersedia',
            'actionable' => false,
            'status' => 'unavailable',
            'created_at' => DateHelper::format($notification['Created_At'] ?? null, 'j F Y • H:i').' WIB',
        ];
    }

    private function isActionable(array $invoice): bool
    {
        return in_array($this->canonicalStatus($invoice), ['waiting payment', 'partial paid', 'overdue'], true)
            && (float) ($invoice['Remaining_Amount'] ?? 0) > 0;
    }

    private function canonicalStatus(array $invoice): string
    {
        return strtolower(trim((string) ($invoice['Status'] ?? $invoice['Display_Status'] ?? '')));
    }

    private function purpose(array $invoice): string
    {
        $purpose = trim((string) ($invoice['Category'] ?? $invoice['Description'] ?? 'Biaya Pendidikan'));

        return $purpose !== '' ? $purpose : 'Biaya Pendidikan';
    }

    private function lowerPurpose(string $purpose): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($purpose, 'UTF-8') : strtolower($purpose);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private function timestamp(mixed $value): int
    {
        if (trim((string) $value) === '') {
            return 0;
        }
        try {
            return Carbon::parse($value, 'Asia/Jakarta')->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }
}
