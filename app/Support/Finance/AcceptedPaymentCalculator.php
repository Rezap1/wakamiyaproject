<?php

namespace App\Support\Finance;

final class AcceptedPaymentCalculator
{
    /**
     * Aggregate accepted payments once per persisted Payment_ID.
     */
    public static function totalsByInvoice(iterable $payments, array $invoiceIds, ?string $excludePaymentId = null): array
    {
        $invoiceIdSet = array_fill_keys(array_map('strval', $invoiceIds), true);
        $totals = [];
        $seenPaymentIds = [];

        foreach ($payments as $payment) {
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
}
