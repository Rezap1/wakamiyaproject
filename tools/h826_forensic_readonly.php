<?php

/**
 * H8.26 production forensic auditor.
 *
 * This file is intentionally read-only: it requests SPREADSHEETS_READONLY,
 * performs no cache writes, and never calls append/update/delete/batchUpdate.
 * A failed read is reported as NOT AUTHORITATIVE; it is never converted into
 * an empty sheet or a zero anomaly count.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$spreadsheetId = config('services.google.spreadsheet_id');
$credentialPath = storage_path('app/google-credentials.json');

$contracts = [
    'FINANCE_PAYMENT' => [
        'critical' => ['Payment_ID', 'Invoice_ID', 'Student_ID', 'Amount_Paid', 'Payment_Date', 'Payment_Method', 'Proof_Image', 'Status'],
        'high' => ['Idempotency_Key', 'Idempotency_Fingerprint', 'Receipt_Number', 'Payment_Type', 'Is_Active'],
        'audit' => ['Created_By', 'Created_At', 'Updated_By', 'Updated_At', 'Verified_By', 'Verified_At'],
        'optional' => ['Notes', 'Reference_Number', 'Proof_File', 'Sender_Name', 'Company_ID', 'Transfer_Date'],
        'pk' => 'Payment_ID',
    ],
    'FINANCE_INVOICE' => [
        'critical' => ['Invoice_ID', 'Student_ID', 'Amount', 'Status', 'Due_Date', 'Line_Items'],
        'high' => ['Is_Active'],
        'audit' => ['Created_By', 'Created_At', 'Updated_By', 'Updated_At'],
        'optional' => ['Period', 'Description', 'Invoice_Type', 'Company_ID', 'Category'],
        'pk' => 'Invoice_ID',
    ],
    'FINANCE_TRANSACTION' => [
        'critical' => ['Transaction_ID', 'Transaction_Date', 'Account_ID', 'Type', 'Category', 'Amount', 'Reference_Type', 'Reference_ID'],
        'high' => ['Is_Active'],
        'audit' => ['Created_By', 'Created_At', 'Updated_By', 'Updated_At'],
        'optional' => ['Description'],
        'pk' => 'Transaction_ID',
    ],
    'MASTER_ACCOUNT' => [
        'critical' => ['Account_ID', 'Account_Code', 'Account_Name', 'Account_Category'],
        'high' => ['Is_Active'],
        'audit' => [],
        'optional' => ['Description', 'Created_At', 'Updated_At'],
        'pk' => 'Account_ID',
    ],
    'MASTER_NOTIFICATION' => [
        'critical' => ['Notification_ID', 'User_ID', 'Title', 'Message'],
        'high' => ['Reference_Type', 'Reference_ID'],
        'audit' => ['Created_At', 'Updated_At'],
        'optional' => ['Is_Read', 'Link', 'Type', 'Status'],
        'pk' => 'Notification_ID',
    ],
];

function h826ReadSheet($service, string $spreadsheetId, string $sheet, int $attempts = 4): array
{
    $last = null;
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        try {
            $values = $service->spreadsheets_values->get($spreadsheetId, $sheet)->getValues();
            $headers = array_map(fn ($h) => trim((string) $h), $values[0] ?? []);
            $rows = [];
            foreach (array_slice($values, 1) as $raw) {
                $row = [];
                $nonEmpty = false;
                foreach ($headers as $index => $header) {
                    $row[$header] = $raw[$index] ?? null;
                    $nonEmpty = $nonEmpty || trim((string) ($raw[$index] ?? '')) !== '';
                }
                if ($nonEmpty) {
                    $rows[] = $row;
                }
            }
            return ['ok' => true, 'headers' => $headers, 'rows' => $rows, 'attempts' => $attempt];
        } catch (Throwable $e) {
            $last = $e;
            $status = (int) $e->getCode();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $status = (int) $e->getResponse()->getStatusCode();
            }
            $retryable = in_array($status, [429, 500, 502, 503, 504], true)
                || str_contains(strtolower($e->getMessage()), 'timeout')
                || str_contains(strtolower($e->getMessage()), 'temporarily unavailable');
            if (!$retryable || $attempt === $attempts) {
                break;
            }
            usleep((250 * (2 ** ($attempt - 1))) * 1000);
        }
    }
    return ['ok' => false, 'headers' => null, 'rows' => [], 'attempts' => $attempts,
        'error' => $last ? get_class($last) . ': ' . $last->getMessage() : 'unknown read failure'];
}

function h826Has(array $headers, string $field): bool
{
    return in_array($field, $headers, true);
}

function h826Status(string $status): string
{
    return strtoupper(trim($status)) === 'FALSE' ? 'FALSE' : strtoupper(trim($status));
}

echo "=== H8.26 PRODUCTION FORENSIC READ-ONLY AUDIT ===\n";
echo "Timestamp: " . now()->toDateTimeString() . "\n";
echo "Production writes: NONE (SPREADSHEETS_READONLY)\n\n";

if (!is_string($credentialPath) || !is_file($credentialPath)) {
    echo "READ AUTHORITY: NOT PROVEN — credentials unavailable at {$credentialPath}\n";
    exit(2);
}

$client = new Google_Client();
$client->setApplicationName('Wakamiya H8.26 Forensic Auditor');
$client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
$client->setAccessType('offline');
$client->setAuthConfig($credentialPath);
$service = new Google_Service_Sheets($client);

$observed = [];
foreach ($contracts as $sheet => $contract) {
    $result = h826ReadSheet($service, $spreadsheetId, $sheet);
    $observed[$sheet] = $result;
    if ($result['ok']) {
        echo "READ {$sheet}: AUTHORITATIVE after {$result['attempts']} attempt(s); headers="
            . count($result['headers']) . ", rows=" . count($result['rows']) . "\n";
    } else {
        echo "READ {$sheet}: NOT AUTHORITATIVE after {$result['attempts']} attempt(s); {$result['error']}\n";
    }
}

echo "\n=== SCHEMA MATRIX ===\n";
echo "FIELD | EXPECTED | ACTUAL | USED BY | CRITICALITY | SAFE WITHOUT IT? | ACTION | PROVEN?\n";
$schemaBlockers = [];
foreach ($contracts as $sheet => $contract) {
    $read = $observed[$sheet];
    foreach (['critical' => 'CRITICAL', 'high' => 'HIGH', 'audit' => 'AUDIT'] as $group => $criticality) {
        foreach ($contract[$group] as $field) {
            $actual = $read['ok'] && h826Has($read['headers'], $field);
            $safe = $group === 'optional' ? 'YES' : 'NO';
            $action = $actual ? 'NONE' : 'APPROVED SCHEMA ALIGNMENT REQUIRED';
            $proven = !$read['ok'] ? 'NOT PROVEN' : ($actual ? 'YES' : 'NO');
            $usedBy = match ($field) {
                'Idempotency_Key', 'Idempotency_Fingerprint' => 'payment submit duplicate prevention',
                'Payment_Type' => 'self-service recognition after fresh read',
                'Receipt_Number' => 'receipt identity',
                'Line_Items' => 'invoice canonical audit/reconciliation',
                'Reference_Type', 'Reference_ID' => $sheet === 'MASTER_NOTIFICATION' ? 'reminder durable dedupe' : 'ledger reference identity',
                default => 'finance persistence/read path',
            };
            $actualText = !$read['ok'] ? 'READ FAILURE' : ($actual ? 'PRESENT' : 'MISSING');
            echo "{$sheet}.{$field} | YES | {$actualText} | {$usedBy} | {$criticality} | {$safe} | {$action} | {$proven}\n";
            if (!$actual && $read['ok'] && $group !== 'optional') {
                $schemaBlockers[] = "{$sheet}.{$field}";
            }
        }
    }
}

echo "\n=== DATA RECONCILIATION ===\n";
$checks = [];
$allAuthoritative = fn (array $sheets): bool => collect($sheets)->every(fn ($s) => ($observed[$s]['ok'] ?? false) === true);
$add = function (string $name, ?int $count, bool $authoritative = true) use (&$checks): void {
    $checks[$name] = $authoritative ? ['status' => 'PROVEN', 'count' => $count] : ['status' => 'NOT AUTHORITATIVE', 'count' => null];
    echo "{$name}: " . ($authoritative ? (string) $count : 'NOT AUTHORITATIVE') . "\n";
};

if ($allAuthoritative(['FINANCE_PAYMENT', 'FINANCE_INVOICE', 'FINANCE_TRANSACTION', 'MASTER_ACCOUNT'])) {
    $payments = $observed['FINANCE_PAYMENT']['rows'];
    $invoices = $observed['FINANCE_INVOICE']['rows'];
    $transactions = $observed['FINANCE_TRANSACTION']['rows'];
    $accounts = $observed['MASTER_ACCOUNT']['rows'];
    foreach ($contracts as $sheet => $contract) {
        if (!isset($observed[$sheet]['rows'])) continue;
        $ids = [];
        foreach ($observed[$sheet]['rows'] as $row) {
            $id = trim((string) ($row[$contract['pk']] ?? ''));
            if ($id !== '') $ids[] = $id;
        }
        $add("duplicate {$sheet}.{$contract['pk']}", count($ids) - count(array_unique($ids)));
    }
    $invoiceIds = array_fill_keys(array_filter(array_map(fn ($r) => trim((string) ($r['Invoice_ID'] ?? '')), $invoices)), true);
    $paymentIds = array_fill_keys(array_filter(array_map(fn ($r) => trim((string) ($r['Payment_ID'] ?? '')), $payments)), true);
    $accountIds = [];
    foreach ($accounts as $row) {
        $accountIds[trim((string) ($row['Account_ID'] ?? ''))] = true;
        $accountIds[trim((string) ($row['Account_Code'] ?? ''))] = true;
    }
    $orphanPayments = count(array_filter($payments, fn ($r) => trim((string) ($r['Invoice_ID'] ?? '')) !== '' && !isset($invoiceIds[trim((string) $r['Invoice_ID'])])));
    $orphanTx = count(array_filter($transactions, function ($r) use ($paymentIds) {
        $type = strtolower(trim((string) ($r['Reference_Type'] ?? '')));
        $ref = trim((string) ($r['Reference_ID'] ?? ''));
        return in_array($type, ['payment', 'paymentreversal'], true) && $ref !== '' && !isset($paymentIds[$ref]);
    }));
    $invalidAccounts = count(array_filter($transactions, fn ($r) => trim((string) ($r['Account_ID'] ?? '')) !== '' && !isset($accountIds[trim((string) $r['Account_ID'])])));
    $activePaymentLedger = [];
    foreach ($transactions as $tx) {
        if (strcasecmp(trim((string) ($tx['Reference_Type'] ?? '')), 'Payment') === 0 && h826Status((string) ($tx['Is_Active'] ?? 'TRUE')) !== 'FALSE') {
            $activePaymentLedger[trim((string) ($tx['Reference_ID'] ?? ''))] = $tx;
        }
    }
    $missingLedger = count(array_filter($payments, fn ($p) => strtolower(trim((string) ($p['Status'] ?? ''))) === 'verified' && !isset($activePaymentLedger[trim((string) ($p['Payment_ID'] ?? ''))])));
    $unverifiedIncome = count(array_filter($transactions, function ($tx) use ($payments) {
        if (strcasecmp(trim((string) ($tx['Reference_Type'] ?? '')), 'Payment') !== 0
            || h826Status((string) ($tx['Is_Active'] ?? 'TRUE')) === 'FALSE') return false;
        $payment = collect($payments)->firstWhere('Payment_ID', trim((string) ($tx['Reference_ID'] ?? '')));
        return $payment && strtolower(trim((string) ($payment['Status'] ?? ''))) !== 'verified';
    }));
    $ledgerMismatch = 0;
    foreach ($activePaymentLedger as $paymentId => $tx) {
        $payment = collect($payments)->firstWhere('Payment_ID', $paymentId);
        if ($payment && (!\App\Support\Finance\Money::equal($tx['Amount'] ?? null, $payment['Amount_Paid'] ?? null)
            || strcasecmp(trim((string) ($tx['Type'] ?? '')), 'Income') !== 0)) $ledgerMismatch++;
    }
    $receiptAuthoritative = h826Has($observed['FINANCE_PAYMENT']['headers'], 'Receipt_Number');
    $receiptValues = array_values(array_filter(array_map(fn ($r) => trim((string) ($r['Receipt_Number'] ?? '')), $payments)));
    $receiptDupes = count($receiptValues) - count(array_unique($receiptValues));
    $paymentOverInvoice = 0;
    foreach ($payments as $payment) {
        $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
        if ($invoiceId === '' || !isset($invoiceIds[$invoiceId])) continue;
        $invoice = collect($invoices)->firstWhere('Invoice_ID', $invoiceId);
        if ($invoice && is_numeric($payment['Amount_Paid'] ?? null) && is_numeric($invoice['Amount'] ?? null)
            && (float) $payment['Amount_Paid'] > (float) $invoice['Amount']) $paymentOverInvoice++;
    }
    $invalidReferences = count(array_filter($transactions, function ($tx) {
        $type = strtolower(trim((string) ($tx['Reference_Type'] ?? '')));
        return in_array($type, ['payment', 'paymentreversal'], true) && trim((string) ($tx['Reference_ID'] ?? '')) === '';
    }));
    $originals = [];
    $reversals = [];
    foreach ($transactions as $tx) {
        $type = strtolower(trim((string) ($tx['Reference_Type'] ?? '')));
        $ref = trim((string) ($tx['Reference_ID'] ?? ''));
        if ($ref === '') continue;
        if ($type === 'payment') $originals[$ref] = $tx;
        if ($type === 'paymentreversal') $reversals[$ref] = $tx;
    }
    $reversalWithoutOriginal = count(array_diff_key($reversals, $originals));
    $reversedPaymentWithoutReversal = count(array_filter($payments, fn ($p) => strtolower(trim((string) ($p['Status'] ?? ''))) === 'reversed' && !isset($reversals[trim((string) ($p['Payment_ID'] ?? ''))])));
    $invalidMoney = 0;
    foreach ([['FINANCE_PAYMENT', 'Amount_Paid'], ['FINANCE_INVOICE', 'Amount'], ['FINANCE_TRANSACTION', 'Amount']] as [$sheet, $field]) {
        foreach ($observed[$sheet]['rows'] as $row) {
            $value = $row[$field] ?? '';
            if ($value !== '' && (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0)) $invalidMoney++;
        }
    }
    $invalidDates = 0;
    foreach ([['FINANCE_PAYMENT', 'Payment_Date'], ['FINANCE_TRANSACTION', 'Transaction_Date']] as [$sheet, $field]) {
        foreach ($observed[$sheet]['rows'] as $row) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}(?:$|\s)/', $value) && !strtotime($value)) $invalidDates++;
        }
    }
    $today = now()->toDateString();
    $future = count(array_filter($transactions, fn ($r) => preg_match('/^\d{4}-\d{2}-\d{2}/', trim((string) ($r['Transaction_Date'] ?? ''))) && trim((string) $r['Transaction_Date']) > $today));
    $add('orphan payments', $orphanPayments);
    $add('orphan transactions', $orphanTx);
    $add('invalid accounts', $invalidAccounts);
    $add('verified payment missing income ledger', $missingLedger);
    $add('unverified payment with income ledger', $unverifiedIncome);
    $add('payment/ledger mismatch', $ledgerMismatch);
    $add('duplicate receipt numbers', $receiptDupes, $receiptAuthoritative);
    $add('payment greater than invoice', $paymentOverInvoice);
    $add('invalid references', $invalidReferences);
    $add('reversal without original', $reversalWithoutOriginal);
    $add('reversed payment without reversal', $reversedPaymentWithoutReversal);
    $add('invoice line-item reconciliation', null, h826Has($observed['FINANCE_INVOICE']['headers'], 'Line_Items'));
    $add('invalid money', $invalidMoney);
    $add('invalid dates', $invalidDates);
    $add('future transactions', $future);
} else {
    foreach (['duplicate IDs', 'orphan payments', 'orphan transactions', 'invalid accounts', 'verified payment missing income ledger', 'invalid money', 'invalid dates', 'future transactions'] as $name) {
        $add($name, null, false);
    }
}

if (($observed['MASTER_NOTIFICATION']['ok'] ?? false) && h826Has($observed['MASTER_NOTIFICATION']['headers'], 'Reference_Type') && h826Has($observed['MASTER_NOTIFICATION']['headers'], 'Reference_ID')) {
    $notifications = $observed['MASTER_NOTIFICATION']['rows'];
    $keys = [];
    foreach ($notifications as $n) {
        $key = trim((string) ($n['Reference_Type'] ?? '')) . '|' . trim((string) ($n['Reference_ID'] ?? '')) . '|' . trim((string) ($n['Title'] ?? '')) . '|' . substr((string) ($n['Created_At'] ?? ''), 0, 10);
        if ($key !== '|||') $keys[] = $key;
    }
    $add('notification durable reminder duplicate key', count($keys) - count(array_unique($keys)));
} else {
    $add('notification durable reminder dedupe', null, false);
}

echo "\n=== SUMMARY ===\n";
echo 'Schema blockers: ' . count($schemaBlockers) . "\n";
foreach ($schemaBlockers as $blocker) echo "- {$blocker}\n";
$readFailures = array_keys(array_filter($observed, fn ($r) => !$r['ok']));
echo 'Read failures (NOT PROVEN): ' . count($readFailures) . "\n";
foreach ($readFailures as $sheet) echo "- {$sheet}\n";
echo "NO WRITES PERFORMED.\n";

exit(($schemaBlockers !== [] || $readFailures !== []) ? 1 : 0);
