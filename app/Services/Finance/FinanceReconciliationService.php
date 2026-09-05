<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Support\Finance\Money;
use App\Support\Finance\PaymentStatus;
use Throwable;

class FinanceReconciliationService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly StudentRepositoryInterface $studentRepository,
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Audit persisted finance relationships without mutating any source row.
     */
    public function audit(
        ?iterable $invoiceSnapshot = null,
        ?iterable $paymentSnapshot = null,
        ?iterable $transactionSnapshot = null,
        ?iterable $studentSnapshot = null
    ): array {
        $invoices = collect($invoiceSnapshot ?? $this->readInvoices())->values();
        $payments = collect($paymentSnapshot ?? $this->readPayments())->values();
        $transactions = collect($transactionSnapshot ?? $this->readTransactions())->values();
        $students = collect($studentSnapshot ?? $this->studentRepository->fetchAll())->values();
        $findings = [];

        $add = static function (string $code, array $context = []) use (&$findings): void {
            $findings[] = array_merge(['code' => $code, 'severity' => 'ERROR'], $context);
        };

        $activeInvoices = $invoices->filter(fn ($row) => strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE')->values();
        $activePayments = $payments->filter(fn ($row) => strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE')->values();
        $activeTransactions = $transactions->filter(fn ($row) => strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE')->values();

        $this->reportDuplicateIds($activeInvoices, 'Invoice_ID', 'duplicate_invoice_id', $add);
        $this->reportDuplicateIds($activePayments, 'Payment_ID', 'duplicate_payment_id', $add);
        $this->reportDuplicateIds($activeTransactions, 'Transaction_ID', 'duplicate_transaction_id', $add);

        $invoiceIndex = $activeInvoices->filter(fn ($row) => trim((string) ($row['Invoice_ID'] ?? '')) !== '')
            ->keyBy(fn ($row) => trim((string) $row['Invoice_ID']));
        $paymentIndex = $activePayments->filter(fn ($row) => trim((string) ($row['Payment_ID'] ?? '')) !== '')
            ->keyBy(fn ($row) => trim((string) $row['Payment_ID']));
        $studentIds = $students->pluck('Student_ID')->filter()->map(fn ($id) => (string) $id)->flip();

        foreach ($activeInvoices as $invoice) {
            $invoiceId = trim((string) ($invoice['Invoice_ID'] ?? ''));
            if ($invoiceId === '') {
                $add('missing_invoice_id');

                continue;
            }

            $studentId = trim((string) ($invoice['Student_ID'] ?? ''));
            if (strcasecmp(trim((string) ($invoice['Invoice_Type'] ?? 'STUDENT')), 'STUDENT') === 0
                && ($studentId === '' || ! $studentIds->has($studentId))) {
                $add('invoice_student_not_found', ['invoice_id' => $invoiceId, 'student_id' => $studentId]);
            }

            try {
                $canonical = $this->invoiceService->formatInvoiceRecord((array) $invoice, null, $activePayments);
            } catch (Throwable $exception) {
                $add('invalid_invoice_financial_state', [
                    'invoice_id' => $invoiceId,
                    'reason' => $exception->getMessage(),
                ]);

                continue;
            }

            $invoiceTotal = (float) $canonical['Amount'];
            $acceptedPaid = (float) $canonical['Paid_Amount'];
            $remaining = (float) $canonical['Remaining_Amount'];

            if (Money::cents($acceptedPaid) > Money::cents($invoiceTotal)) {
                $add('accepted_payment_exceeds_invoice_total', [
                    'invoice_id' => $invoiceId,
                    'invoice_total' => $invoiceTotal,
                    'accepted_paid_amount' => $acceptedPaid,
                ]);
            }
            if (array_key_exists('Paid_Amount', $invoice) && ! Money::equal($invoice['Paid_Amount'], $acceptedPaid)) {
                $add('stored_paid_amount_mismatch', ['invoice_id' => $invoiceId, 'expected' => $acceptedPaid, 'actual' => $invoice['Paid_Amount']]);
            }
            if (array_key_exists('Remaining_Amount', $invoice) && ! Money::equal($invoice['Remaining_Amount'], $remaining)) {
                $add('stored_remaining_amount_mismatch', ['invoice_id' => $invoiceId, 'expected' => $remaining, 'actual' => $invoice['Remaining_Amount']]);
            }

            $storedStatus = trim((string) ($invoice['Status'] ?? ''));
            if (strcasecmp($storedStatus, 'Paid') === 0 && Money::cents($remaining) > 0) {
                $add('paid_invoice_has_remaining_balance', ['invoice_id' => $invoiceId, 'remaining_balance' => $remaining]);
            }
            if ($acceptedPaid > 0
                && array_key_exists('Paid_Amount', $invoice)
                && Money::equal($invoice['Paid_Amount'], 0)) {
                $add('accepted_payment_missing_from_invoice_paid_amount', [
                    'invoice_id' => $invoiceId,
                    'accepted_paid_amount' => $acceptedPaid,
                ]);
            }
            if ($storedStatus !== '' && strcasecmp($storedStatus, (string) $canonical['Status']) !== 0) {
                $add('stored_invoice_status_mismatch', ['invoice_id' => $invoiceId, 'expected' => $canonical['Status'], 'actual' => $storedStatus]);
            }
        }

        foreach ($activePayments as $payment) {
            $paymentId = trim((string) ($payment['Payment_ID'] ?? ''));
            if ($paymentId === '') {
                $add('missing_payment_id');

                continue;
            }

            try {
                $canonicalPaymentStatus = PaymentStatus::canonical($payment['Status'] ?? null);
                $isAccepted = $canonicalPaymentStatus === 'Verified';
            } catch (Throwable $exception) {
                $add('invalid_payment_status', ['payment_id' => $paymentId, 'reason' => $exception->getMessage()]);

                continue;
            }

            $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
            $isSelfService = strcasecmp(trim((string) ($payment['Payment_Type'] ?? '')), 'STUDENT_SELF_SERVICE') === 0;
            if ($invoiceId === '') {
                if (! $isSelfService) {
                    $add('payment_without_invoice', ['payment_id' => $paymentId]);
                } elseif ($isAccepted) {
                    $studentId = trim((string) ($payment['Student_ID'] ?? ''));
                    $hasOpenInvoice = $activeInvoices->contains(function ($invoice) use ($studentId) {
                        $status = strtolower(trim((string) ($invoice['Status'] ?? 'draft')));

                        return $studentId !== ''
                            && ($invoice['Student_ID'] ?? '') === $studentId
                            && ! in_array($status, ['draft', 'cancelled', 'paid'], true);
                    });
                    if ($hasOpenInvoice) {
                        $add('verified_self_service_payment_unallocated', [
                            'payment_id' => $paymentId,
                            'student_id' => $studentId,
                        ]);
                    }
                }
            } elseif (! $invoiceIndex->has($invoiceId)) {
                $add('payment_invoice_not_found', ['payment_id' => $paymentId, 'invoice_id' => $invoiceId]);
            } else {
                $invoice = (array) $invoiceIndex->get($invoiceId);
                $invoiceStudentId = trim((string) ($invoice['Student_ID'] ?? ''));
                $paymentStudentId = trim((string) ($payment['Student_ID'] ?? ''));
                if ($invoiceStudentId !== '' && $paymentStudentId === '') {
                    $add('payment_student_missing', ['payment_id' => $paymentId, 'invoice_id' => $invoiceId]);
                } elseif ($invoiceStudentId !== '' && $paymentStudentId !== $invoiceStudentId) {
                    $add('payment_student_mismatch', [
                        'payment_id' => $paymentId,
                        'invoice_id' => $invoiceId,
                        'expected_student_id' => $invoiceStudentId,
                        'actual_student_id' => $paymentStudentId,
                    ]);
                }
            }

            if ($canonicalPaymentStatus === 'Reversed') {
                $reversals = $activeTransactions->filter(fn ($transaction) => strcasecmp(trim((string) ($transaction['Reference_Type'] ?? '')), 'PaymentReversal') === 0
                    && trim((string) ($transaction['Reference_ID'] ?? '')) === $paymentId
                )->values();
                if ($reversals->count() !== 1) {
                    $add('reversed_payment_ledger_invalid', ['payment_id' => $paymentId, 'reversal_count' => $reversals->count()]);
                } elseif (strcasecmp(trim((string) ($reversals[0]['Type'] ?? '')), 'Expense') !== 0
                    || ! Money::equal($reversals[0]['Amount'] ?? null, $payment['Amount_Paid'] ?? null)) {
                    $add('reversed_payment_ledger_mismatch', [
                        'payment_id' => $paymentId,
                        'transaction_id' => (string) ($reversals[0]['Transaction_ID'] ?? ''),
                    ]);
                }
            }

            if (! $isAccepted) {
                continue;
            }

            $ledgerRows = $activeTransactions->filter(fn ($transaction) => strcasecmp(trim((string) ($transaction['Reference_Type'] ?? '')), 'Payment') === 0
                && trim((string) ($transaction['Reference_ID'] ?? '')) === $paymentId
            )->values();
            if ($ledgerRows->isEmpty()) {
                $add('verified_payment_ledger_missing', ['payment_id' => $paymentId]);

                continue;
            }
            if ($ledgerRows->count() > 1) {
                $add('verified_payment_ledger_duplicate', ['payment_id' => $paymentId, 'count' => $ledgerRows->count()]);
            }
            foreach ($ledgerRows as $transaction) {
                if (strcasecmp(trim((string) ($transaction['Type'] ?? '')), 'Income') !== 0
                    || ! Money::equal($transaction['Amount'] ?? null, $payment['Amount_Paid'] ?? null)) {
                    $add('verified_payment_ledger_mismatch', [
                        'payment_id' => $paymentId,
                        'transaction_id' => (string) ($transaction['Transaction_ID'] ?? ''),
                    ]);
                }
            }
        }

        foreach ($activeTransactions as $transaction) {
            if (strcasecmp(trim((string) ($transaction['Reference_Type'] ?? '')), 'Payment') !== 0) {
                continue;
            }
            $paymentId = trim((string) ($transaction['Reference_ID'] ?? ''));
            if ($paymentId === '' || ! $paymentIndex->has($paymentId)) {
                $add('transaction_payment_not_found', [
                    'transaction_id' => (string) ($transaction['Transaction_ID'] ?? ''),
                    'payment_id' => $paymentId,
                ]);
            }
        }

        return [
            'is_consistent' => $findings === [],
            'counts' => [
                'invoices' => $activeInvoices->count(),
                'payments' => $activePayments->count(),
                'transactions' => $activeTransactions->count(),
                'findings' => count($findings),
            ],
            'findings' => $findings,
        ];
    }

    private function reportDuplicateIds($rows, string $field, string $code, callable $add): void
    {
        $rows->groupBy(fn ($row) => trim((string) ($row[$field] ?? '')))
            ->each(function ($duplicates, string $id) use ($field, $code, $add): void {
                if ($id !== '' && $duplicates->count() > 1) {
                    $add($code, [strtolower($field) => $id, 'count' => $duplicates->count()]);
                }
            });
    }

    private function readInvoices(): iterable
    {
        return method_exists($this->invoiceRepository, 'getAllFresh')
            ? $this->invoiceRepository->getAllFresh()
            : $this->invoiceRepository->getAll();
    }

    private function readPayments(): iterable
    {
        return method_exists($this->paymentRepository, 'getAllFresh')
            ? $this->paymentRepository->getAllFresh()
            : $this->paymentRepository->getAll();
    }

    private function readTransactions(): iterable
    {
        return method_exists($this->transactionRepository, 'fetchAllFresh')
            ? $this->transactionRepository->fetchAllFresh()
            : $this->transactionRepository->fetchAll();
    }
}
