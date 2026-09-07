<?php

namespace App\Services\Finance;

use App\Exceptions\FinancialIntegrityException;
use App\Support\Finance\AcceptedPaymentCalculator;
use App\Support\Finance\Money;
use App\Support\Finance\PaymentStatus;
use App\Support\Presentation\IndonesianPresentation as Indo;
use App\Support\Reporting\HumanReadableResolver;
use Illuminate\Support\Facades\Log;

/** Prepared, read-only financial document values; no repository calls in render loops. */
class FinanceDocumentPresenter
{
    public function __construct(private InvoiceService $invoices)
    {
    }

    public function invoice(array $invoice, iterable $payments, ?array $totals = null): array
    {
        foreach ($payments as $payment) {
            if (($payment['Invoice_ID'] ?? '') === ($invoice['Invoice_ID'] ?? '')
                && strtoupper(trim((string) ($payment['Is_Active'] ?? 'TRUE'))) !== 'FALSE') {
                $this->assertInvoiceOwner($payment, $invoice);
            }
        }
        return $this->invoices->formatInvoiceRecord($invoice, $totals, $payments);
    }

    public function assertInvoiceOwner(array $payment, array $invoice): void
    {
        foreach (['Student_ID', 'Company_ID'] as $field) {
            if (trim((string) ($payment[$field] ?? '')) !== trim((string) ($invoice[$field] ?? ''))) {
                Log::warning('finance.document_invoice_owner_mismatch', [
                    'payment_id' => $payment['Payment_ID'] ?? null, 'invoice_id' => $invoice['Invoice_ID'] ?? null,
                ]);
                throw new FinancialIntegrityException('Relasi pemilik pembayaran dan tagihan tidak sesuai. Dokumen tidak dapat diterbitkan.');
            }
        }
    }

    public function payment(array $payment, array $snapshot): array
    {
        $paymentId = trim((string) ($payment['Payment_ID'] ?? ''));
        if ($paymentId === '' || strtoupper(trim((string) ($payment['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
            throw new FinancialIntegrityException('Data pembayaran tidak tersedia.');
        }
        $status = PaymentStatus::canonical($payment['Status'] ?? null);
        $payments = collect($snapshot['payments']);
        // Do not silently choose one of two conflicting versions of the same payment.
        $seen = [];
        foreach ($payments as $row) {
            $id = trim((string) ($row['Payment_ID'] ?? ''));
            $identity = array_map(fn ($key) => (string) ($row[$key] ?? ''), ['Invoice_ID', 'Student_ID', 'Company_ID', 'Status', 'Amount_Paid', 'Is_Active']);
            if ($id !== '' && isset($seen[$id]) && $seen[$id] !== $identity) {
                throw new FinancialIntegrityException('Terdapat data pembayaran ganda yang bertentangan. Periksa rekonsiliasi keuangan.');
            }
            $seen[$id] = $identity;
        }
        $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
        $invoice = $invoiceId !== '' ? collect($snapshot['invoices_by_id'])->get($invoiceId) : null;
        $invoiceMissing = false;
        if ($invoiceId !== '' && (!$invoice || strtoupper((string) ($invoice['Is_Active'] ?? 'TRUE')) === 'FALSE')) {
            Log::warning('finance.document_invoice_missing', ['payment_id' => $paymentId, 'invoice_id' => $invoiceId]);
            $invoiceMissing = true;
            $warning = trim(($warning ?? '') . ' Tagihan terkait tidak tersedia. Dokumen ditampilkan tanpa data tagihan.');
            $invoice = null;
        }
        if ($invoice) {
            $this->assertInvoiceOwner($payment, $invoice);
            $invoice = $this->invoice($invoice, $payments);
        }
        $customer = HumanReadableResolver::financialParty($payment, $snapshot['students_by_id'], $snapshot['companies_by_id'], $snapshot['classes_by_id'] ?? []);
        $ledger = collect($snapshot['transactions'] ?? [])->filter(fn ($row) =>
            strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), 'Payment') === 0
            && ($row['Reference_ID'] ?? '') === $paymentId
            && strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE');
        $accountIds = $ledger->pluck('Account_ID')->filter()->map(fn ($id) => (string) $id)->unique()->values();
        if ($accountIds->count() > 1) {
            throw new FinancialIntegrityException('Akun ledger pembayaran bertentangan. Dokumen perlu diperiksa.');
        }
        foreach ($ledger as $entry) {
            if (!Money::equal($entry['Amount'] ?? 0, $payment['Amount_Paid'] ?? 0)) {
                throw new FinancialIntegrityException('Nominal ledger dan pembayaran tidak sesuai. Dokumen perlu diperiksa.');
            }
        }
        $accountId = (string) ($accountIds->first() ?? $payment['Account_ID'] ?? '');
        $accounts = collect($snapshot['accounts'] ?? []);
        $account = $accounts->first(fn ($row) => (string) ($row['Account_ID'] ?? '') === $accountId || (string) ($row['Account_Code'] ?? '') === $accountId);
        $accountName = trim((string) ($account['Account_Name'] ?? ''));
        // Account_Code is a ledger reference, never a bank account number.
        $accountNumber = trim((string) ($account['Account_Number'] ?? $account['Bank_Account_Number'] ?? ''));
        $accountLabel = $accountName !== '' ? $accountName . ($accountNumber !== '' ? ' - ' . $accountNumber : '') : 'Akun penerima belum dapat diverifikasi';
        $warning = $customer['integrity_warning'];
        if ($accountName === '') {
            Log::warning('finance.document_account_unresolved', ['payment_id' => $paymentId, 'account_id' => $accountId]);
            $warning = trim(($warning ?? '') . ' Akun penerima belum dapat diverifikasi.');
        }
        $previous = $invoice ? AcceptedPaymentCalculator::beforePayment($payments, $payment) : null;
        if ($invoice && $previous === null) {
            Log::warning('finance.document_payment_chronology_unresolved', ['payment_id' => $paymentId, 'invoice_id' => $invoiceId]);
        }
        $payment['student_name'] = $customer['name'];
        $payment['Status'] = $status;
        return [
            'payment' => $payment, 'invoice' => $invoice, 'customer' => $customer,
            'receivingAccount' => $accountLabel,
            'paymentMethodLabel' => Indo::paymentMethod($payment['Payment_Method'] ?? null),
            'paymentTypeLabel' => Indo::paymentType($payment['Payment_Type'] ?? ($customer['type'] ?: null)),
            'paymentStatusLabel' => Indo::paymentStatus($status),
            'integrityWarning' => $warning,
            'invoiceMissing' => $invoiceMissing,
            'isOverpaying' => in_array($status, ['Waiting Verification', 'Need Revision'], true) && $invoice
                && Money::cents($payment['Amount_Paid'] ?? 0) > Money::cents($invoice['Remaining_Amount']),
            'documentTitle' => $status === 'Verified' && !$warning ? 'Kwitansi Pembayaran' : 'Informasi Pembayaran',
            'balances' => [
                'invoiceAmount' => $invoice['Amount'] ?? null,
                'prevVerified' => $previous,
                'currentPayment' => Money::value($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran'),
                'acceptedPaid' => $invoice['Paid_Amount'] ?? null,
                'remainingBalance' => $invoice['Remaining_Amount'] ?? null,
            ],
        ];
    }
}
