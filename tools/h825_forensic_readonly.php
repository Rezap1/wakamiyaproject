<?php
// H8.26 supersedes the earlier auditor. Keep this entry point fail-closed so
// an operator cannot accidentally run the pre-H8.26 503-as-empty logic.
require __DIR__ . '/h826_forensic_readonly.php';
return;

/**
 * H8.25 — Production Read-Only Forensic Inspection
 *
 * STRICTLY READ-ONLY.  NO writes, NO deletes, NO mutations.
 *
 * Usage: php tools/h825_forensic_readonly.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Google_Client;
use Google_Service_Sheets;

$spreadsheetId = config('services.google.spreadsheet_id');
echo "=== H8.25 PRODUCTION FORENSIC READ-ONLY AUDIT ===\n";
echo "Spreadsheet ID: {$spreadsheetId}\n";
echo "Timestamp: " . now()->toDateTimeString() . "\n\n";

$client = new Google_Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
$client->setAccessType('offline');

$credPath = storage_path('app/google-credentials.json');
if (!file_exists($credPath)) {
    echo "ERROR: Google credentials not found at {$credPath}\n";
    exit(1);
}
$client->setAuthConfig($credPath);
$service = new Google_Service_Sheets($client);

$appContract = [
    'FINANCE_PAYMENT' => [
        'critical' => ['Payment_ID', 'Invoice_ID', 'Student_ID', 'Amount_Paid', 'Payment_Date', 'Payment_Method', 'Proof_Image', 'Status'],
        'audit_metadata' => ['Created_By', 'Created_At', 'Updated_By', 'Updated_At', 'Verified_By', 'Verified_At'],
        'hardened_h819' => ['Idempotency_Key', 'Idempotency_Fingerprint', 'Receipt_Number', 'Payment_Type', 'Is_Active'],
        'optional' => ['Notes', 'Reference_Number', 'Proof_File', 'Sender_Name', 'Company_ID', 'Transfer_Date'],
    ],
    'FINANCE_INVOICE' => [
        'critical' => ['Invoice_ID', 'Student_ID', 'Amount', 'Status', 'Due_Date'],
        'audit_metadata' => ['Created_By', 'Created_At', 'Updated_By', 'Updated_At'],
        'hardened_h819' => ['Line_Items'],
        'optional' => ['Period', 'Description', 'Invoice_Type', 'Company_ID', 'Category', 'Is_Active'],
    ],
    'FINANCE_TRANSACTION' => [
        'critical' => ['Transaction_ID', 'Transaction_Date', 'Account_ID', 'Type', 'Category', 'Amount', 'Reference_Type', 'Reference_ID'],
        'audit_metadata' => ['Created_By', 'Created_At', 'Updated_By', 'Updated_At'],
        'hardened_h819' => ['Is_Active'],
        'optional' => ['Description'],
    ],
    'MASTER_ACCOUNT' => [
        'critical' => ['Account_ID', 'Account_Code', 'Account_Name', 'Account_Category'],
        'audit_metadata' => [],
        'hardened_h819' => ['Is_Active'],
        'optional' => ['Description', 'Created_At', 'Updated_At'],
    ],
    'MASTER_NOTIFICATION' => [
        'critical' => ['Notification_ID', 'User_ID', 'Title', 'Message'],
        'audit_metadata' => ['Created_At', 'Updated_At'],
        'hardened_h819' => ['Reference_Type', 'Reference_ID'],
        'optional' => ['Is_Read', 'Link', 'Type', 'Status'],
    ],
];

$productionHeaders = [];
$productionData = [];

foreach (array_keys($appContract) as $sheetName) {
    echo "--- Reading {$sheetName} ---\n";
    try {
        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        $values = $response->getValues();
        if (empty($values)) {
            echo "  WARNING: Sheet is empty\n";
            $productionHeaders[$sheetName] = [];
            $productionData[$sheetName] = [];
            continue;
        }
        $headers = array_map(fn($h) => trim((string) $h), $values[0]);
        $productionHeaders[$sheetName] = $headers;
        echo "  Headers (" . count($headers) . "): " . implode(', ', $headers) . "\n";
        $rows = [];
        foreach (array_slice($values, 1) as $row) {
            $item = [];
            $isEmpty = true;
            foreach ($headers as $i => $header) {
                $val = $row[$i] ?? null;
                $item[$header] = $val;
                if (trim((string)$val) !== '') $isEmpty = false;
            }
            if (!$isEmpty) $rows[] = $item;
        }
        $productionData[$sheetName] = $rows;
        echo "  Data rows: " . count($rows) . "\n";
    } catch (\Throwable $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        $productionHeaders[$sheetName] = null;
        $productionData[$sheetName] = [];
    }
}

echo "\n\n=== SCHEMA COMPATIBILITY MATRIX ===\n";
echo str_pad('Sheet', 24) . str_pad('Field', 30) . str_pad('Category', 18) . str_pad('App Needs?', 12) . str_pad('Prod Exists?', 14) . str_pad('Critical?', 10) . "Behavior if Missing\n";
echo str_repeat('-', 120) . "\n";

$missingCritical = [];
$missingHardened = [];
$missingAudit = [];

foreach ($appContract as $sheet => $categories) {
    $prodHeaders = $productionHeaders[$sheet] ?? null;
    if ($prodHeaders === null) {
        echo str_pad($sheet, 24) . "SHEET NOT FOUND IN PRODUCTION\n";
        $missingCritical[] = "{$sheet} (entire sheet missing)";
        continue;
    }
    foreach ($categories as $category => $fields) {
        foreach ($fields as $field) {
            $exists = in_array($field, $prodHeaders, true);
            $isCritical = $category === 'critical';
            if (!$exists) {
                if ($isCritical) { $behavior = 'BLOCKER - data loss'; $missingCritical[] = "{$sheet}.{$field}"; }
                elseif ($category === 'hardened_h819') { $behavior = 'FAIL-CLOSED on write'; $missingHardened[] = "{$sheet}.{$field}"; }
                elseif ($category === 'audit_metadata') { $behavior = 'Silent field loss'; $missingAudit[] = "{$sheet}.{$field}"; }
                else { $behavior = 'Optional/graceful'; }
            } else { $behavior = 'OK'; }
            echo str_pad($sheet, 24) . str_pad($field, 30) . str_pad($category, 18) . str_pad('YES', 12) . str_pad($exists ? 'YES' : 'NO', 14) . str_pad($isCritical ? 'CRITICAL' : ($category === 'hardened_h819' ? 'HIGH' : 'LOW'), 10) . $behavior . "\n";
        }
    }
    $allAppFields = array_merge(...array_values($categories));
    $unknownProd = array_diff($prodHeaders, $allAppFields);
    if (!empty($unknownProd)) {
        foreach ($unknownProd as $uf) {
            echo str_pad($sheet, 24) . str_pad($uf, 30) . str_pad('unknown', 18) . str_pad('NO', 12) . str_pad('YES', 14) . str_pad('N/A', 10) . "Production-only field\n";
        }
    }
}

echo "\n\n=== DATA ANOMALY DETECTION ===\n";
$anomalies = [];

foreach (['FINANCE_PAYMENT' => 'Payment_ID', 'FINANCE_INVOICE' => 'Invoice_ID', 'FINANCE_TRANSACTION' => 'Transaction_ID'] as $sheet => $pk) {
    $rows = $productionData[$sheet] ?? [];
    $ids = []; $dupes = 0;
    foreach ($rows as $row) {
        $id = trim((string)($row[$pk] ?? ''));
        if ($id === '') continue;
        if (isset($ids[$id])) $dupes++;
        $ids[$id] = true;
    }
    echo "  {$sheet} duplicate {$pk}: {$dupes}\n";
    if ($dupes > 0) $anomalies[] = "{$sheet}: {$dupes} duplicate {$pk}(s)";
}

$receiptNumbers = []; $receiptDupes = 0;
foreach ($productionData['FINANCE_PAYMENT'] ?? [] as $row) {
    $rn = trim((string)($row['Receipt_Number'] ?? ''));
    if ($rn === '') continue;
    if (isset($receiptNumbers[$rn])) $receiptDupes++;
    $receiptNumbers[$rn] = true;
}
echo "  Duplicate Receipt_Number: {$receiptDupes}\n";

$invoiceIds = [];
foreach ($productionData['FINANCE_INVOICE'] ?? [] as $row) {
    $id = trim((string)($row['Invoice_ID'] ?? '')); if ($id !== '') $invoiceIds[$id] = true;
}
$orphanPayments = 0;
foreach ($productionData['FINANCE_PAYMENT'] ?? [] as $row) {
    $invId = trim((string)($row['Invoice_ID'] ?? ''));
    if ($invId !== '' && !isset($invoiceIds[$invId])) $orphanPayments++;
}
echo "  Orphan payments: {$orphanPayments}\n";

$paymentIds = [];
foreach ($productionData['FINANCE_PAYMENT'] ?? [] as $row) {
    $id = trim((string)($row['Payment_ID'] ?? '')); if ($id !== '') $paymentIds[$id] = true;
}
$orphanTx = 0;
foreach ($productionData['FINANCE_TRANSACTION'] ?? [] as $row) {
    $refType = strtolower(trim((string)($row['Reference_Type'] ?? '')));
    $refId = trim((string)($row['Reference_ID'] ?? ''));
    if (in_array($refType, ['payment', 'paymentreversal'], true) && $refId !== '' && !isset($paymentIds[$refId])) $orphanTx++;
}
echo "  Orphan transactions: {$orphanTx}\n";

$accountIds = []; $accountCodes = [];
foreach ($productionData['MASTER_ACCOUNT'] ?? [] as $row) {
    $accountIds[trim((string)($row['Account_ID'] ?? ''))] = true;
    $accountCodes[trim((string)($row['Account_Code'] ?? ''))] = true;
}
$invalidAccounts = 0;
foreach ($productionData['FINANCE_TRANSACTION'] ?? [] as $row) {
    $accId = trim((string)($row['Account_ID'] ?? ''));
    if ($accId !== '' && !isset($accountIds[$accId]) && !isset($accountCodes[$accId])) $invalidAccounts++;
}
echo "  Invalid Account_ID in transactions: {$invalidAccounts}\n";

$paymentLedgerRefs = [];
foreach ($productionData['FINANCE_TRANSACTION'] ?? [] as $tx) {
    if (strcasecmp(trim((string)($tx['Reference_Type'] ?? '')), 'Payment') === 0
        && strtoupper(trim((string)($tx['Is_Active'] ?? 'TRUE'))) !== 'FALSE') {
        $refId = trim((string)($tx['Reference_ID'] ?? ''));
        if ($refId !== '') $paymentLedgerRefs[$refId] = true;
    }
}
$verifiedMissingLedger = 0;
foreach ($productionData['FINANCE_PAYMENT'] ?? [] as $payment) {
    if (strtolower(trim((string)($payment['Status'] ?? ''))) === 'verified') {
        $payId = trim((string)($payment['Payment_ID'] ?? ''));
        if ($payId !== '' && !isset($paymentLedgerRefs[$payId])) $verifiedMissingLedger++;
    }
}
echo "  Verified payments missing income ledger: {$verifiedMissingLedger}\n";

$invalidMoney = 0;
foreach (['FINANCE_PAYMENT' => 'Amount_Paid', 'FINANCE_INVOICE' => 'Amount', 'FINANCE_TRANSACTION' => 'Amount'] as $s => $f) {
    foreach ($productionData[$s] ?? [] as $row) {
        $amt = $row[$f] ?? '';
        if ($amt !== '' && (!is_numeric($amt) || (float)$amt < 0)) $invalidMoney++;
    }
}
echo "  Invalid money values: {$invalidMoney}\n";

$invalidDates = 0;
foreach (['FINANCE_PAYMENT' => 'Payment_Date', 'FINANCE_TRANSACTION' => 'Transaction_Date'] as $s => $f) {
    foreach ($productionData[$s] ?? [] as $row) {
        $date = trim((string)($row[$f] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}/', $date) && !strtotime($date)) $invalidDates++;
    }
}
echo "  Invalid dates: {$invalidDates}\n";

$futureTx = 0; $today = date('Y-m-d');
foreach ($productionData['FINANCE_TRANSACTION'] ?? [] as $row) {
    $date = trim((string)($row['Transaction_Date'] ?? ''));
    if ($date !== '' && $date > $today && preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) $futureTx++;
}
echo "  Future transactions: {$futureTx}\n";

$reversals = []; $originals = [];
foreach ($productionData['FINANCE_TRANSACTION'] ?? [] as $tx) {
    $refType = strtolower(trim((string)($tx['Reference_Type'] ?? '')));
    $refId = trim((string)($tx['Reference_ID'] ?? ''));
    if ($refType === 'payment') $originals[$refId] = $tx;
    if ($refType === 'paymentreversal') $reversals[$refId] = $tx;
}
$reversalMismatch = 0;
foreach ($reversals as $refId => $rev) {
    if (isset($originals[$refId])) {
        if (abs((float)($originals[$refId]['Amount'] ?? 0) - (float)($rev['Amount'] ?? 0)) > 0.01) $reversalMismatch++;
    }
}
echo "  Reversal/original amount mismatch: {$reversalMismatch}\n";

echo "\n\n=== FORENSIC SUMMARY ===\n";
echo "Row Counts:\n";
foreach (array_keys($appContract) as $sheet) {
    echo "  {$sheet}: " . count($productionData[$sheet] ?? []) . " rows\n";
}
echo "\nMissing Critical Fields (BLOCKER): " . count($missingCritical) . "\n";
foreach ($missingCritical as $m) echo "  - {$m}\n";
echo "\nMissing Hardened H8.19+ Fields (FAIL-CLOSED on write): " . count($missingHardened) . "\n";
foreach ($missingHardened as $m) echo "  - {$m}\n";
echo "\nMissing Audit Metadata Fields (silent field loss): " . count($missingAudit) . "\n";
foreach ($missingAudit as $m) echo "  - {$m}\n";
echo "\nData Anomalies: " . count($anomalies) . "\n";
foreach ($anomalies as $a) echo "  - {$a}\n";
echo "\n=== NO WRITES PERFORMED - READ-ONLY AUDIT COMPLETE ===\n";
