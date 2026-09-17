<?php

namespace App\Support\Finance;

use App\Exceptions\FinancialIntegrityException;
use Carbon\Carbon;

final class AcceptedPaymentCalculator
{
    public const EDUCATION_SOURCE_SELF_SERVICE = 'self_service';

    public const EDUCATION_SOURCE_INVOICE = 'invoice';

    /**
     * Aggregate accepted payments once per persisted Payment_ID.
     */
    public static function totalsByInvoice(iterable $payments, array $invoiceIds, ?string $excludePaymentId = null): array
    {
        $invoiceIdSet = array_fill_keys(array_map('strval', $invoiceIds), true);
        $totals = [];
        $seenPaymentIds = [];

        foreach ($payments as $payment) {
            if (strtoupper(trim((string) ($payment['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                continue;
            }
            $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
            if ($invoiceId === '' || ! isset($invoiceIdSet[$invoiceId])) {
                continue;
            }

            $paymentId = trim((string) ($payment['Payment_ID'] ?? ''));
            if ($excludePaymentId !== null && $paymentId === $excludePaymentId) {
                continue;
            }
            if (! PaymentStatus::verified($payment['Status'] ?? null)) {
                continue;
            }
            if ($paymentId !== '' && isset($seenPaymentIds[$paymentId])) {
                continue;
            }
            if ($paymentId !== '') {
                $seenPaymentIds[$paymentId] = true;
            }

            $totals[$invoiceId] = ($totals[$invoiceId] ?? 0)
                + Money::cents($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran');
        }

        return array_map(fn (int $cents) => $cents / (10 ** Money::SCALE), $totals);
    }

    public static function forInvoice(iterable $payments, string $invoiceId, ?string $excludePaymentId = null): float
    {
        return (float) (self::totalsByInvoice($payments, [$invoiceId], $excludePaymentId)[$invoiceId] ?? 0.0);
    }

    /** Null means chronology cannot be established from the persisted dates. */
    public static function beforePayment(iterable $payments, array $current): ?float
    {
        $invoiceId = trim((string) ($current['Invoice_ID'] ?? ''));
        if ($invoiceId === '') {
            return null;
        }
        $prior = [];
        foreach ($payments as $payment) {
            if (($payment['Invoice_ID'] ?? '') !== $invoiceId
                || ($payment['Payment_ID'] ?? '') === ($current['Payment_ID'] ?? '')
                || strtoupper(trim((string) ($payment['Is_Active'] ?? 'TRUE'))) === 'FALSE'
                || ! PaymentStatus::verified($payment['Status'] ?? null)) {
                continue;
            }
            $comparison = null;
            // Business payment date, then persisted timestamps for same-day payments.
            foreach (['Payment_Date', 'Verified_At', 'Created_At'] as $field) {
                $left = self::timestamp($payment[$field] ?? null);
                $right = self::timestamp($current[$field] ?? null);
                if ($left !== null && $right !== null && $left !== $right) {
                    $comparison = $left <=> $right;
                    break;
                }
            }
            if ($comparison === null) {
                return null;
            }
            if ($comparison < 0) {
                $prior[] = $payment;
            }
        }

        return self::forInvoice($prior, $invoiceId, $current['Payment_ID'] ?? null);
    }

    private static function timestamp(mixed $date): ?int
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }
        try {
            return Carbon::parse($date, 'Asia/Jakarta')->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function invoiceLessSelfServiceForStudent(iterable $payments, string $studentId): float
    {
        $totalCents = 0;
        $seenPaymentIds = [];

        foreach ($payments as $payment) {
            if (($payment['Student_ID'] ?? '') !== $studentId
                || strcasecmp(trim((string) ($payment['Payment_Type'] ?? '')), 'STUDENT_SELF_SERVICE') !== 0
                || trim((string) ($payment['Invoice_ID'] ?? '')) !== '') {
                continue;
            }
            if (! PaymentStatus::verified($payment['Status'] ?? null)) {
                continue;
            }

            $paymentId = trim((string) ($payment['Payment_ID'] ?? ''));
            if ($paymentId !== '' && isset($seenPaymentIds[$paymentId])) {
                continue;
            }
            if ($paymentId !== '') {
                $seenPaymentIds[$paymentId] = true;
            }
            $totalCents += Money::cents($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran mandiri');
        }

        return $totalCents / (10 ** Money::SCALE);
    }

    /**
     * Build canonical education payments from their persisted relationships.
     *
     * @param  array<string, array>  $educationInvoicesById
     * @return array{totals_by_student: array<string, float>, payments: array<int, array>}
     */
    public static function educationSnapshot(iterable $payments, array $educationInvoicesById): array
    {
        $totalsInCents = [];
        $educationPayments = [];
        $seenPaymentIds = [];

        foreach ($payments as $payment) {
            $payment = (array) $payment;
            if (strtoupper(trim((string) ($payment['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                continue;
            }

            $paymentId = trim((string) ($payment['Payment_ID'] ?? ''));
            if ($paymentId !== '' && isset($seenPaymentIds[$paymentId])) {
                continue;
            }

            $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
            $studentId = trim((string) ($payment['Student_ID'] ?? ''));
            $source = null;

            if ($invoiceId === '') {
                // The approved production FINANCE_PAYMENT schema has no
                // Payment_Purpose/Category and older rows have no persisted
                // Payment_Type. The canonical self-service invariant is the
                // server-owned Student_ID plus an empty Invoice_ID: staff
                // payment creation rejects an empty Invoice_ID. When the
                // optional Payment_Type is present, accept only its canonical
                // self-service value.
                $paymentType = strtoupper(str_replace(' ', '_', trim((string) ($payment['Payment_Type'] ?? ''))));
                if ($studentId === '' || ! in_array($paymentType, ['', 'STUDENT_SELF_SERVICE'], true)) {
                    continue;
                }
                $source = self::EDUCATION_SOURCE_SELF_SERVICE;
            } elseif (isset($educationInvoicesById[$invoiceId])) {
                $invoiceStudentId = trim((string) ($educationInvoicesById[$invoiceId]['Student_ID'] ?? ''));
                if ($studentId === '' || $invoiceStudentId === '' || $studentId !== $invoiceStudentId) {
                    throw new FinancialIntegrityException(
                        "Pemilik payment #{$paymentId} tidak sesuai dengan invoice pendidikan #{$invoiceId}."
                    );
                }
                $source = self::EDUCATION_SOURCE_INVOICE;
            }

            if ($source === null) {
                continue;
            }
            if ($paymentId !== '') {
                $seenPaymentIds[$paymentId] = true;
            }

            $payment['Education_Source'] = $source;
            $educationPayments[] = $payment;

            if (PaymentStatus::verified($payment['Status'] ?? null)) {
                $totalsInCents[$studentId] = ($totalsInCents[$studentId] ?? 0)
                    + Money::cents($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran pendidikan');
            }
        }

        return [
            'totals_by_student' => array_map(
                fn (int $cents) => $cents / (10 ** Money::SCALE),
                $totalsInCents,
            ),
            'payments' => $educationPayments,
        ];
    }
}
