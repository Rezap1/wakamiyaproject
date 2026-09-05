<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Support\Finance\Money;
use App\Support\Reporting\HumanReadableResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class TransactionPresentationService
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private InvoiceRepositoryInterface $invoiceRepository,
        private StudentRepositoryInterface $studentRepository,
        private CompanyRepositoryInterface $companyRepository,
        private AccountRepositoryInterface $accountRepository,
        private PayrollRepositoryInterface $payrollRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private UserRepositoryInterface $userRepository,
        private EmployeeRepositoryInterface $employeeRepository
    ) {
    }

    public function presentCollection(iterable $transactions): Collection
    {
        $snapshot = $this->snapshot();

        return collect($transactions)
            ->map(fn ($transaction) => $this->present((array) $transaction, $snapshot))
            ->values();
    }

    public function presentDetail(array $transaction): array
    {
        $snapshot = $this->snapshot();

        return $this->present($transaction, $snapshot);
    }

    public function paymentEvidence(array $payment): array
    {
        $paymentId = trim((string) ($payment['Payment_ID'] ?? ''));
        $storedPath = $payment['Proof_File'] ?? $payment['Proof_Image'] ?? '';
        $safePath = $this->safeStoredPath($storedPath);

        if ($paymentId === '' || $safePath === null) {
            return [
                'available' => false,
                'message' => 'Bukti pembayaran tidak tersedia.',
                'is_pdf' => false,
                'inline_url' => null,
                'download_url' => null,
            ];
        }

        $isPdf = str_ends_with(strtolower($safePath), '.pdf');

        return [
            'available' => true,
            'message' => null,
            'is_pdf' => $isPdf,
            'inline_url' => Route::has('payments.proof') ? route('payments.proof', ['id' => $paymentId, 'inline' => 1]) : null,
            'download_url' => Route::has('payments.proof') ? route('payments.proof', $paymentId) : null,
            'label' => 'Lihat Bukti Pembayaran',
        ];
    }

    private function snapshot(): array
    {
        $transactions = collect($this->readRows($this->transactionRepository, 'fetchAll'));
        $payments = collect($this->readRows($this->paymentRepository, 'getAll'));
        $invoices = collect($this->readRows($this->invoiceRepository, 'getAll'));
        $students = collect($this->readRows($this->studentRepository, 'fetchAll'));
        $companies = collect($this->readRows($this->companyRepository, 'fetchAll'));
        $accounts = collect($this->readRows($this->accountRepository, 'fetchAll'));
        $payrolls = collect($this->readRows($this->payrollRepository, 'getAll'));
        $users = collect($this->readRows($this->userRepository, 'fetchAll'));
        $employees = collect($this->readRows($this->employeeRepository, 'fetchAll'));

        return [
            'transactions' => $transactions,
            'transactions_by_id' => $transactions->keyBy('Transaction_ID'),
            'payments_by_id' => $payments->keyBy('Payment_ID'),
            'invoices_by_id' => $invoices->keyBy('Invoice_ID'),
            'students_by_id' => $students->keyBy('Student_ID'),
            'companies_by_id' => $companies->keyBy('Company_ID'),
            'accounts_by_id' => $this->accountIndex($accounts),
            'payrolls_by_id' => $payrolls->keyBy('Payroll_ID'),
            'users_by_id' => $users->keyBy('User_ID'),
            'employees_by_id' => $employees->keyBy('Employee_ID'),
        ];
    }

    private function present(array $transaction, array $snapshot): array
    {
        $transactionId = trim((string) ($transaction['Transaction_ID'] ?? ''));
        $referenceType = trim((string) ($transaction['Reference_Type'] ?? ''));
        $referenceId = trim((string) ($transaction['Reference_ID'] ?? ''));
        $type = $this->canonicalTransactionType($transaction['Type'] ?? '');
        $payment = null;
        $invoice = null;
        $payroll = null;

        if (in_array(strtolower($referenceType), ['payment', 'paymentreversal'], true) && $referenceId !== '') {
            $payment = $this->row($snapshot['payments_by_id']->get($referenceId));
            if (!$payment) {
                Log::warning('finance.transaction_missing_payment_reference', [
                    'transaction_id' => $transactionId,
                    'payment_id' => $referenceId,
                ]);
            }
        }

        $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
        if ($invoiceId === '' && strcasecmp($referenceType, 'Invoice') === 0) {
            $invoiceId = $referenceId;
        }
        if ($invoiceId !== '') {
            $invoice = $this->row($snapshot['invoices_by_id']->get($invoiceId));
            if (!$invoice) {
                Log::warning('finance.transaction_missing_invoice_reference', [
                    'transaction_id' => $transactionId,
                    'invoice_id' => $invoiceId,
                ]);
            }
        }

        if (strcasecmp($referenceType, 'Payroll') === 0 && $referenceId !== '') {
            $payroll = $this->row($snapshot['payrolls_by_id']->get($referenceId));
        }

        $party = $this->party($payment, $invoice, $payroll, $transaction, $snapshot);
        $source = $this->source($referenceType, $referenceId, $payment, $invoice, $payroll);
        $accountId = trim((string) ($transaction['Account_ID'] ?? $transaction['Account_Code'] ?? ''));
        $accountLabel = $accountId !== ''
            ? HumanReadableResolver::accountName($accountId, $snapshot['accounts_by_id'])
            : 'Akun tidak ditemukan';
        $paymentData = $payment ? $this->payment($payment, $invoice, $snapshot) : null;
        $invoiceData = $invoice ? $this->invoice($invoice) : null;
        $reversal = $this->reversal($transaction, $snapshot);

        return [
            'raw' => $transaction,
            'transaction_id' => $transactionId,
            'title' => $source['label'],
            'description' => $this->value($transaction['Description'] ?? '', $source['description']),
            'date_label' => $this->dateLabel($transaction['Transaction_Date'] ?? ''),
            'created_label' => $this->dateTimeLabel($transaction['Created_At'] ?? ''),
            'updated_label' => $this->dateTimeLabel($transaction['Updated_At'] ?? ''),
            'amount' => $this->amount($transaction['Amount'] ?? 0),
            'amount_label' => $this->money($transaction['Amount'] ?? 0),
            'type' => $type,
            'type_label' => $this->transactionTypeLabel($type, $referenceType),
            'type_color' => strcasecmp($type, 'Income') === 0 ? 'green' : 'red',
            'category_label' => $this->categoryLabel($transaction['Category'] ?? '', $referenceType, $payment),
            'account' => [
                'id' => $accountId,
                'label' => $accountLabel,
                'missing' => $accountLabel === 'Akun tidak ditemukan',
            ],
            'source' => $source,
            'party' => $party,
            'payment' => $paymentData,
            'invoice' => $invoiceData,
            'evidence' => $payment ? $this->paymentEvidence($payment) : [
                'available' => false,
                'message' => 'Bukti pembayaran tidak tersedia.',
                'is_pdf' => false,
                'inline_url' => null,
                'download_url' => null,
            ],
            'audit' => [
                'created_by' => HumanReadableResolver::userName($transaction['Created_By'] ?? '', $snapshot['users_by_id']),
                'updated_by' => HumanReadableResolver::userName($transaction['Updated_By'] ?? '', $snapshot['users_by_id']),
                'created_at' => $this->dateTimeLabel($transaction['Created_At'] ?? ''),
                'updated_at' => $this->dateTimeLabel($transaction['Updated_At'] ?? ''),
            ],
            'reversal' => $reversal,
            'legacy_warning' => $this->legacyWarning($referenceType, $referenceId, $payment, $invoice, $payroll),
            'reference_label' => $this->referenceLabel($referenceType, $referenceId),
        ];
    }

    private function payment(array $payment, ?array $invoice, array $snapshot): array
    {
        $verifiedBy = trim((string) ($payment['Verified_By'] ?? $payment['Approved_By'] ?? ''));

        return [
            'id' => trim((string) ($payment['Payment_ID'] ?? '')),
            'amount_label' => $this->money($payment['Amount_Paid'] ?? $payment['Amount'] ?? 0),
            'method_label' => $this->paymentMethodLabel($payment['Payment_Method'] ?? ''),
            'date_label' => $this->dateLabel($payment['Payment_Date'] ?? $payment['Transfer_Date'] ?? ''),
            'sender_name' => $this->value($payment['Sender_Name'] ?? '', 'Nama pengirim tidak tersedia'),
            'receipt_number' => $this->value($payment['Receipt_Number'] ?? '', 'Nomor kwitansi tidak tersedia'),
            'reference_number' => $this->value($payment['Reference_Number'] ?? '', '-'),
            'status_label' => $this->statusLabel($payment['Status'] ?? 'Waiting Verification'),
            'verified_by' => $verifiedBy !== ''
                ? HumanReadableResolver::userName($verifiedBy, $snapshot['users_by_id'])
                : 'Belum diverifikasi',
            'verified_at' => $this->dateTimeLabel($payment['Verified_At'] ?? $payment['Approved_At'] ?? ''),
            'is_self_service' => $this->isSelfServicePayment($payment),
            'invoice_missing' => trim((string) ($payment['Invoice_ID'] ?? '')) !== '' && !$invoice,
            'invoice_optional' => trim((string) ($payment['Invoice_ID'] ?? '')) === '' && $this->isSelfServicePayment($payment),
        ];
    }

    private function invoice(array $invoice): array
    {
        return [
            'id' => trim((string) ($invoice['Invoice_ID'] ?? '')),
            'number' => $this->value($invoice['Invoice_Number'] ?? $invoice['Invoice_ID'] ?? '', 'Nomor invoice tidak tersedia'),
            'amount_label' => $this->money($invoice['Grand_Total'] ?? $invoice['Amount'] ?? 0),
            'remaining_label' => $this->money($invoice['Remaining_Amount'] ?? 0),
            'status_label' => $this->statusLabel($invoice['Display_Status'] ?? $invoice['Status'] ?? ''),
            'due_date_label' => $this->dateLabel($invoice['Due_Date'] ?? ''),
            'category' => $this->value($invoice['Category'] ?? '', 'Tagihan'),
        ];
    }

    private function source(string $referenceType, string $referenceId, ?array $payment, ?array $invoice, ?array $payroll): array
    {
        $lower = strtolower($referenceType);
        if ($payment) {
            $selfService = $this->isSelfServicePayment($payment);
            return [
                'type' => $referenceType,
                'type_label' => $lower === 'paymentreversal' ? 'Pembalikan Pembayaran' : ($selfService ? 'Pembayaran Mandiri Siswa' : 'Pembayaran'),
                'id' => $referenceId,
                'label' => $selfService ? 'Pembayaran Mandiri Siswa' : $this->value($payment['Receipt_Number'] ?? '', 'Pembayaran'),
                'official_reference' => $this->value($payment['Receipt_Number'] ?? $payment['Payment_ID'] ?? '', '-'),
                'status_label' => $this->statusLabel($payment['Status'] ?? ''),
                'description' => $selfService ? 'Pembayaran mandiri tanpa invoice' : 'Transaksi dari pembayaran terverifikasi',
                'url' => Route::has('payments.show') ? route('payments.show', $payment['Payment_ID']) : null,
            ];
        }

        if ($invoice) {
            return [
                'type' => $referenceType,
                'type_label' => 'Invoice',
                'id' => $referenceId,
                'label' => $this->value($invoice['Invoice_Number'] ?? $invoice['Invoice_ID'] ?? '', 'Invoice'),
                'official_reference' => $this->value($invoice['Invoice_Number'] ?? $invoice['Invoice_ID'] ?? '', '-'),
                'status_label' => $this->statusLabel($invoice['Display_Status'] ?? $invoice['Status'] ?? ''),
                'description' => 'Transaksi dari invoice',
                'url' => Route::has('invoices.show') ? route('invoices.show', $invoice['Invoice_ID']) : null,
            ];
        }

        if ($payroll) {
            return [
                'type' => $referenceType,
                'type_label' => 'Payroll',
                'id' => $referenceId,
                'label' => $this->value($payroll['Document_Number'] ?? $payroll['Payroll_ID'] ?? '', 'Payroll'),
                'official_reference' => $this->value($payroll['Document_Number'] ?? $payroll['Payroll_ID'] ?? '', '-'),
                'status_label' => $this->statusLabel($payroll['Status'] ?? ''),
                'description' => 'Transaksi payroll',
                'url' => Route::has('payrolls.show') ? route('payrolls.show', $payroll['Payroll_ID']) : null,
            ];
        }

        return [
            'type' => $referenceType,
            'type_label' => $this->referenceTypeLabel($referenceType),
            'id' => $referenceId,
            'label' => $this->referenceTypeLabel($referenceType),
            'official_reference' => $this->value($referenceId, '-'),
            'status_label' => '-',
            'description' => 'Sumber transaksi tidak ditemukan',
            'url' => null,
        ];
    }

    private function party(?array $payment, ?array $invoice, ?array $payroll, array $transaction, array $snapshot): array
    {
        $companyId = trim((string) ($payment['Company_ID'] ?? $invoice['Company_ID'] ?? ''));
        if ($companyId !== '') {
            return [
                'type_label' => 'Perusahaan',
                'name' => HumanReadableResolver::companyName($companyId, $snapshot['companies_by_id']),
                'context' => $this->value($companyId, '-'),
            ];
        }

        $studentId = trim((string) ($payment['Student_ID'] ?? $invoice['Student_ID'] ?? ''));
        if ($studentId !== '') {
            $student = $this->row($snapshot['students_by_id']->get($studentId));
            $context = array_filter([
                $student['Class_Name'] ?? null,
                $student['Program_Name'] ?? null,
                HumanReadableResolver::studentNumber($studentId, $snapshot['students_by_id']),
            ], fn ($value) => trim((string) $value) !== '' && $value !== '-');

            return [
                'type_label' => 'Siswa',
                'name' => HumanReadableResolver::studentName($studentId, $snapshot['students_by_id']),
                'context' => implode(' | ', $context) ?: 'Konteks siswa tidak tersedia',
            ];
        }

        $employeeId = trim((string) ($payroll['Employee_ID'] ?? $transaction['Employee_ID'] ?? ''));
        if ($employeeId !== '') {
            return [
                'type_label' => 'Pegawai',
                'name' => HumanReadableResolver::employeeName($employeeId, $snapshot['employees_by_id']),
                'context' => $this->value($payroll['Payroll_Period'] ?? '', 'Konteks payroll tidak tersedia'),
            ];
        }

        $sender = trim((string) ($payment['Sender_Name'] ?? $transaction['Sender_Name'] ?? ''));
        return [
            'type_label' => 'Pihak terkait',
            'name' => $sender !== '' ? $sender : 'Pihak terkait tidak ditemukan',
            'context' => 'Tidak ada relasi siswa/perusahaan/pegawai yang dapat dibuktikan',
        ];
    }

    private function reversal(array $transaction, array $snapshot): array
    {
        $referenceId = trim((string) ($transaction['Reference_ID'] ?? ''));
        $referenceType = strtolower(trim((string) ($transaction['Reference_Type'] ?? '')));
        if ($referenceId === '' || !in_array($referenceType, ['payment', 'paymentreversal'], true)) {
            return [
                'has_reversal' => false,
                'is_reversal' => false,
                'message' => 'Tidak ada pembalikan/koreksi yang tercatat.',
                'transaction' => null,
            ];
        }

        $targetType = $referenceType === 'payment' ? 'PaymentReversal' : 'Payment';
        $related = collect($snapshot['transactions'])
            ->first(fn ($row) => strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), $targetType) === 0
                && trim((string) ($row['Reference_ID'] ?? '')) === $referenceId
                && strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE');

        if (!$related) {
            return [
                'has_reversal' => false,
                'is_reversal' => $referenceType === 'paymentreversal',
                'message' => $referenceType === 'paymentreversal'
                    ? 'Transaksi asli tidak ditemukan.'
                    : 'Belum ada pembalikan/koreksi untuk transaksi ini.',
                'transaction' => null,
            ];
        }

        $relatedId = trim((string) ($related['Transaction_ID'] ?? ''));

        return [
            'has_reversal' => $referenceType === 'payment',
            'is_reversal' => $referenceType === 'paymentreversal',
            'message' => $referenceType === 'payment'
                ? 'Transaksi ini memiliki pembalikan/koreksi.'
                : 'Transaksi ini adalah pembalikan/koreksi dari transaksi asli.',
            'transaction' => [
                'id' => $relatedId,
                'label' => $referenceType === 'payment' ? 'Transaksi koreksi' : 'Transaksi asli',
                'amount_label' => $this->money($related['Amount'] ?? 0),
                'url' => $relatedId !== '' && Route::has('transactions.show') ? route('transactions.show', $relatedId) : null,
            ],
        ];
    }

    private function legacyWarning(string $referenceType, string $referenceId, ?array $payment, ?array $invoice, ?array $payroll): ?string
    {
        if (trim($referenceId) === '') {
            return 'Transaksi legacy/manual tanpa nomor referensi sumber.';
        }

        $lower = strtolower($referenceType);
        if (in_array($lower, ['payment', 'paymentreversal'], true) && !$payment) {
            return 'Sumber pembayaran tidak ditemukan. Transaksi inti tetap ditampilkan sebagai data ledger.';
        }
        if ($lower === 'invoice' && !$invoice) {
            return 'Sumber invoice tidak ditemukan. Transaksi inti tetap ditampilkan sebagai data ledger.';
        }
        if ($lower === 'payroll' && !$payroll) {
            return 'Sumber payroll tidak ditemukan. Transaksi inti tetap ditampilkan sebagai data ledger.';
        }

        return null;
    }

    private function accountIndex(Collection $accounts): Collection
    {
        return $accounts->flatMap(function ($account) {
            $keys = [];
            foreach (['Account_ID', 'Account_Code'] as $field) {
                $key = trim((string) ($account[$field] ?? ''));
                if ($key !== '') {
                    $keys[$key] = $account;
                }
            }

            return $keys;
        });
    }

    private function readRows(object $repository, string $method): iterable
    {
        try {
            if (method_exists($repository, $method)) {
                return $repository->{$method}();
            }
        } catch (\Throwable $e) {
            Log::warning('finance.transaction_presenter_snapshot_read_failed', [
                'repository' => get_class($repository),
                'method' => $method,
                'exception' => get_class($e),
            ]);
        }

        return [];
    }

    private function safeStoredPath(mixed $storedPath): ?string
    {
        if (!is_string($storedPath)) {
            return null;
        }

        $relativePath = trim(str_replace('\\', '/', $storedPath));
        if ($relativePath === '' || str_contains($relativePath, "\0") || preg_match('#(^|/)\.\.(/|$)#', $relativePath)) {
            return null;
        }
        if (preg_match('/^[A-Za-z]:/', $relativePath) || str_starts_with(strtolower($relativePath), 'http://') || str_starts_with(strtolower($relativePath), 'https://')) {
            return null;
        }

        return $relativePath;
    }

    private function canonicalTransactionType(mixed $type): string
    {
        $value = strtolower(trim((string) $type));
        if (in_array($value, ['income', 'pemasukan', 'masuk', 'revenue', 'pendapatan'], true)) {
            return 'Income';
        }
        if (in_array($value, ['expense', 'pengeluaran', 'keluar', 'cost', 'biaya', 'beban'], true)) {
            return 'Expense';
        }

        return trim((string) $type) ?: 'Other';
    }

    private function transactionTypeLabel(string $type, string $referenceType): string
    {
        if (strcasecmp($referenceType, 'PaymentReversal') === 0) {
            return 'Pembalikan/Koreksi';
        }

        return strcasecmp($type, 'Income') === 0 ? 'Pemasukan' : (strcasecmp($type, 'Expense') === 0 ? 'Pengeluaran' : 'Transaksi');
    }

    private function referenceTypeLabel(string $referenceType): string
    {
        return match (strtolower(trim($referenceType))) {
            'payment' => 'Pembayaran',
            'paymentreversal' => 'Pembalikan Pembayaran',
            'invoice' => 'Invoice',
            'payroll' => 'Payroll',
            'adjustment' => 'Penyesuaian',
            'other', '' => 'Transaksi Manual',
            default => 'Sumber tidak dikenal',
        };
    }

    private function categoryLabel(mixed $category, string $referenceType, ?array $payment): string
    {
        $category = trim((string) $category);
        if ($this->isSelfServicePayment($payment ?? [])) {
            return 'Pembayaran Mandiri';
        }

        return $category !== '' ? $category : $this->referenceTypeLabel($referenceType);
    }

    private function referenceLabel(string $referenceType, string $referenceId): string
    {
        return trim($referenceId) === ''
            ? $this->referenceTypeLabel($referenceType)
            : $this->referenceTypeLabel($referenceType) . ' #' . $referenceId;
    }

    private function statusLabel(mixed $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'verified' => 'Terverifikasi',
            'waiting verification' => 'Menunggu Verifikasi',
            'need revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'reversed' => 'Dikoreksi',
            'paid' => 'Lunas',
            'partial paid' => 'Dibayar Sebagian',
            'waiting payment' => 'Menunggu Pembayaran',
            'overdue' => 'Jatuh Tempo',
            'draft' => 'Draft',
            default => $this->value($status, '-'),
        };
    }

    private function paymentMethodLabel(mixed $method): string
    {
        return match (strtolower(trim((string) $method))) {
            'cash', 'tunai' => 'Tunai',
            'transfer', 'bank transfer', 'bank_transfer' => 'Transfer Bank',
            default => $this->value($method, 'Metode tidak tersedia'),
        };
    }

    private function isSelfServicePayment(array $payment): bool
    {
        return strcasecmp(trim((string) ($payment['Payment_Type'] ?? '')), 'STUDENT_SELF_SERVICE') === 0
            || trim((string) ($payment['Invoice_ID'] ?? '')) === '';
    }

    private function money(mixed $value): string
    {
        return 'Rp ' . number_format($this->amount($value), 0, ',', '.');
    }

    private function amount(mixed $value): float
    {
        try {
            return Money::value($value ?? 0, 'Nominal transaksi');
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function dateLabel(mixed $date): string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '-';
        }

        try {
            return Carbon::parse($date, config('app.timezone', 'Asia/Jakarta'))->locale('id')->translatedFormat('j F Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function dateTimeLabel(mixed $date): string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '-';
        }

        try {
            return Carbon::parse($date, config('app.timezone', 'Asia/Jakarta'))->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i') . ' WIB';
        } catch (\Throwable) {
            return $date;
        }
    }

    private function value(mixed $value, string $fallback): string
    {
        return HumanReadableResolver::value($value, $fallback);
    }

    private function row(mixed $row): ?array
    {
        if (!$row) {
            return null;
        }

        return is_array($row) ? $row : (array) $row;
    }
}
