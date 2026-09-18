<?php

use App\Http\Controllers\Finance\EducationPaymentMonitoringController;
use App\Services\Finance\EducationPaymentMonitoringService;
use App\Services\Finance\InvoiceService;
use App\Support\Finance\Money;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$client = new Google_Client;
$client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig(storage_path('app/google-credentials.json'));
$client->setHttpClient(new Client(['connect_timeout' => 10, 'timeout' => 25]));
$service = new Google_Service_Sheets($client);
// All live traffic uses a read-only OAuth scope. Replay below permits GET only.
$names = ['MASTER_STUDENT', 'MASTER_CLASS', 'MASTER_PROGRAM', 'MASTER_BATCH', 'MASTER_SYSTEM_SETTING', 'FINANCE_INVOICE', 'FINANCE_PAYMENT', 'FINANCE_TRANSACTION'];
try {
    $result = $service->spreadsheets_values->batchGet(config('services.google.spreadsheet_id'), ['ranges' => $names]);
    $out = [];
    foreach ($result->getValueRanges() as $i => $range) {
        $values = $range->getValues() ?? [];
        $headers = array_shift($values) ?? [];
        $rows = [];
        foreach ($values as $raw) {
            $r = [];
            foreach ($headers as $j => $h) {
                $r[trim($h)] = $raw[$j] ?? null;
            }
            if ($names[$i] === 'MASTER_STUDENT') {
                $r = array_intersect_key($r, array_flip(['Student_ID', 'Student_Number', 'Full_Name', 'User_ID', 'Class_ID', 'Program_ID', 'Batch_ID', 'Enrollment_Status', 'Is_Active']));
            }
            if ($names[$i] === 'MASTER_SYSTEM_SETTING' && ($r['Setting_Key'] ?? '') !== 'DEFAULT_TUITION_FEE') {
                continue;
            }
            if (isset($r['Proof_Image'])) {
                $r['Proof_Hash'] = $r['Proof_Image'] !== '' ? hash('sha256', $r['Proof_Image']) : null;
                unset($r['Proof_Image']);
            }
            $rows[] = $r;
        }
        $out[$names[$i]] = ['headers' => $headers, 'rows' => $rows];
    }

    config(['cache.default' => 'array']);
    $trace = [];
    $wire = function (array $data) use (&$trace) {
        Cache::store('array')->flush();
        $map = ['Student' => 'MASTER_STUDENT', 'Class' => 'MASTER_CLASS', 'Program' => 'MASTER_PROGRAM', 'Batch' => 'MASTER_BATCH', 'SystemSetting' => 'MASTER_SYSTEM_SETTING', 'Invoice' => 'FINANCE_INVOICE', 'Payment' => 'FINANCE_PAYMENT', 'Transaction' => 'FINANCE_TRANSACTION'];
        foreach ($map as $type => $sheet) {
            $fqcn = 'App\\Repositories\\GoogleSheets\\'.$type.'Repository';
            $iface = 'App\\Interfaces\\GoogleSheets\\'.$type.'RepositoryInterface';
            $repo = new $fqcn;
            $headers = $data[$sheet]['headers'];
            $values = [$headers];
            foreach ($data[$sheet]['rows'] as $row) {
                $values[] = array_map(fn ($h) => $row[$h] ?? null, $headers);
            }
            $handler = function ($request, $options) use ($sheet, $values, &$trace) {
                if ($request->getMethod() !== 'GET') {
                    throw new RuntimeException('WRITE DENIED');
                }
                $trace[] = $sheet;

                return Create::promiseFor(new Response(200, [], json_encode(['range' => $sheet, 'majorDimension' => 'ROWS', 'values' => $values])));
            };
            $localClient = new Google_Client;
            $localClient->setHttpClient(new Client(['handler' => $handler]));
            (new ReflectionProperty($repo, 'service'))->setValue($repo, new Google_Service_Sheets($localClient));
            app()->instance($iface, $repo);
        }
    };
    $wire($out);
    $controller = app(EducationPaymentMonitoringController::class);
    $view = $controller->index(Request::create('/finance/education-payments'));
    $rows = $view->getData()['groups']->flatMap(fn ($g) => $g['students']);
    echo 'RUNTIME_ROWS '.json_encode($rows->map(fn ($r) => array_intersect_key($r, array_flip(['student_id', 'education_fee', 'paid', 'remaining', 'excess', 'status'])))->values()).PHP_EOL;
    echo 'REPOSITORY_READ_TRACE '.json_encode($trace).PHP_EOL;
    $detail = app(EducationPaymentMonitoringService::class)->detail('STD000013');
    echo 'AZKA_RUNTIME_DETAIL '.json_encode($detail).PHP_EOL;

    $payments = collect($out['FINANCE_PAYMENT']['rows']);
    $invoices = collect($out['FINANCE_INVOICE']['rows']);
    $students = collect($out['MASTER_STUDENT']['rows']);
    $transactions = collect($out['FINANCE_TRANSACTION']['rows']);
    $ids = fn ($items, $key) => $items->pluck($key)->values()->all();
    $verified = $payments->filter(fn ($p) => strtolower(trim($p['Status'] ?? '')) === 'verified');
    $checks = [];
    $checks['duplicate_payment_ids'] = $payments->groupBy('Payment_ID')->filter(fn ($g) => $g->count() > 1)->keys()->all();
    $checks['duplicate_invoice_ids'] = $invoices->groupBy('Invoice_ID')->filter(fn ($g) => $g->count() > 1)->keys()->all();
    $checks['semantic_duplicate_candidates'] = $payments->groupBy(fn ($p) => implode('|', [
        trim($p['Student_ID'] ?? ''), $p['Amount_Paid'] ?? '', trim($p['Payment_Date'] ?? ''),
        strtoupper(trim($p['Payment_Method'] ?? '')), strtolower(trim($p['Reference_Number'] ?? '')),
    ]))->filter(fn ($g) => $g->count() > 1)->map(fn ($g) => $g->pluck('Payment_ID')->all())->values()->all();
    $checks['missing_student_id'] = $ids($payments->filter(fn ($p) => trim($p['Student_ID'] ?? '') === ''), 'Payment_ID');
    $checks['invalid_student_id'] = $ids($payments->filter(fn ($p) => ! $students->contains('Student_ID', $p['Student_ID'] ?? '')), 'Payment_ID');
    $checks['wrong_invoice_owner'] = $ids($payments->filter(function ($p) use ($invoices) {
        $i = $invoices->firstWhere('Invoice_ID', $p['Invoice_ID'] ?? '');

        return $i && ($i['Student_ID'] ?? '') !== ($p['Student_ID'] ?? '');
    }), 'Payment_ID');
    $checks['invoice_invalid_student'] = $ids($invoices->filter(fn ($i) => ! $students->contains('Student_ID', $i['Student_ID'] ?? '')), 'Invoice_ID');
    $checks['verified_cancelled_invoice'] = $ids($verified->filter(fn ($p) => strtolower($invoices->firstWhere('Invoice_ID', $p['Invoice_ID'] ?? '')['Status'] ?? '') === 'cancelled'), 'Payment_ID');
    $checks['verified_missing_invoice'] = $ids($verified->filter(fn ($p) => trim($p['Invoice_ID'] ?? '') !== '' && ! $invoices->contains('Invoice_ID', $p['Invoice_ID'])), 'Payment_ID');
    $checks['negative_amount'] = $ids($payments->filter(fn ($p) => is_numeric($p['Amount_Paid'] ?? null) && (float) $p['Amount_Paid'] < 0), 'Payment_ID');
    $checks['malformed_amount'] = $ids($payments->filter(fn ($p) => ! is_numeric($p['Amount_Paid'] ?? null) || ! is_finite((float) $p['Amount_Paid'])), 'Payment_ID');
    $checks['zero_amount'] = $ids($payments->filter(fn ($p) => is_numeric($p['Amount_Paid'] ?? null) && (float) $p['Amount_Paid'] === 0.0), 'Payment_ID');
    $linkedLedger = $transactions->filter(fn ($t) => strcasecmp(trim($t['Reference_Type'] ?? ''), 'Payment') === 0 && trim($t['Reference_ID'] ?? '') !== '' && strtoupper($t['Is_Active'] ?? 'TRUE') !== 'FALSE');
    $checks['duplicate_payment_ledger'] = $linkedLedger->groupBy('Reference_ID')->filter(fn ($g) => $g->count() > 1)->map(fn ($g) => $g->pluck('Transaction_ID')->all())->all();
    $checks['verified_missing_ledger'] = $verified
        ->filter(fn ($p) => ! $linkedLedger->contains('Reference_ID', $p['Payment_ID']))
        ->map(fn ($p) => array_intersect_key($p, array_flip([
            'Payment_ID', 'Student_ID', 'Invoice_ID', 'Amount_Paid', 'Payment_Method',
            'Payment_Date', 'Status', 'Verified_At', 'Receipt_Number', 'Payment_Type',
        ])))
        ->values()
        ->all();
    $checks['ledger_payment_mismatch'] = $ids($linkedLedger->filter(function ($t) use ($payments) {
        $p = $payments->firstWhere('Payment_ID', $t['Reference_ID']);

        return ! $p || strcasecmp($t['Type'] ?? '', 'Income') !== 0 || ! Money::equal($t['Amount'] ?? null, $p['Amount_Paid'] ?? null);
    }), 'Transaction_ID');
    $checks['empty_ledger_relation'] = $ids($transactions->filter(fn ($t) => in_array($t['Reference_Type'] ?? '', ['Payment', 'Invoice'], true) && trim($t['Reference_ID'] ?? '') === ''), 'Transaction_ID');
    $checks['overpayment_students'] = $rows->where('excess', '>', 0)->map(fn ($r) => array_intersect_key($r, array_flip(['student_id', 'paid', 'education_fee', 'excess'])))->values()->all();
    $checks['verified_education_missing_from_monitoring'] = [];
    $checks['payment_total_mismatches'] = [];
    $checks['zero_despite_verified_education'] = [];
    $checks['payment_counted_more_than_once'] = [];
    $checks['unverified_history_marked_verified'] = [];

    $invoiceService = app(InvoiceService::class);
    foreach ($students as $student) {
        $studentId = $student['Student_ID'];
        $row = $rows->firstWhere('student_id', $studentId);
        $expected = 0;
        $seen = [];
        foreach ($payments->where('Student_ID', $studentId) as $p) {
            if (strtolower(trim($p['Status'] ?? '')) !== 'verified' || strtoupper($p['Is_Active'] ?? 'TRUE') === 'FALSE') {
                continue;
            }
            $i = $invoices->firstWhere('Invoice_ID', $p['Invoice_ID'] ?? '');
            $education = trim($p['Invoice_ID'] ?? '') === '' ? in_array(strtoupper(trim($p['Payment_Type'] ?? '')), ['', 'STUDENT_SELF_SERVICE'], true) : ($i && $invoiceService->isEducationInvoice($i));
            if (! $education || isset($seen[$p['Payment_ID']])) {
                continue;
            }
            $seen[$p['Payment_ID']] = true;
            $expected += Money::cents($p['Amount_Paid']);
        }
        if ($expected > 0 && (! $row || Money::cents($row['paid']) !== $expected)) {
            $checks['verified_education_missing_from_monitoring'][] = ['student_id' => $studentId, 'expected' => $expected / 100, 'actual' => $row['paid'] ?? null, 'payment_ids' => array_keys($seen)];
        }
        if ($row && Money::cents($row['paid']) !== $expected) {
            $checks['payment_total_mismatches'][] = ['student_id' => $studentId, 'expected' => $expected / 100, 'actual' => $row['paid']];
        }
        if ($expected > 0 && $row && (float) $row['paid'] === 0.0) {
            $checks['zero_despite_verified_education'][] = $studentId;
        }
        $history = app(EducationPaymentMonitoringService::class)->detail($studentId)['history'] ?? collect();
        foreach ($history->groupBy('payment_id') as $paymentId => $entries) {
            if ($entries->count() > 1) {
                $checks['payment_counted_more_than_once'][] = $paymentId;
            }
            $raw = $payments->firstWhere('Payment_ID', $paymentId);
            if ($entries->first()['status'] === 'Verified' && strtolower(trim($raw['Status'] ?? '')) !== 'verified') {
                $checks['unverified_history_marked_verified'][] = $paymentId;
            }
        }
    }
    $checks['replacement_invoice_link'] = 'No replacement relation column; no inferred relation from matching amounts.';
    $checks['historical_zero_event'] = 'Not proven from one current snapshot; in-memory lifecycle counterfactual is reported separately.';
    echo 'AUDIT_UTC '.gmdate('c').PHP_EOL;
    echo 'COUNTS '.json_encode(array_map(fn ($s) => count($s['rows']), $out)).PHP_EOL;
    echo 'CHECKS '.json_encode($checks, JSON_UNESCAPED_SLASHES).PHP_EOL;
    $azkaInvoices = $invoices->where('Student_ID', 'STD000013');
    $azkaPayments = $payments->filter(fn ($p) => ($p['Student_ID'] ?? '') === 'STD000013' || $azkaInvoices->contains('Invoice_ID', $p['Invoice_ID'] ?? ''));
    echo 'AZKA_INVOICES '.json_encode($azkaInvoices->values()).PHP_EOL;
    echo 'AZKA_PAYMENTS '.json_encode($azkaPayments->map(fn ($p) => array_intersect_key($p, array_flip(['Payment_ID', 'Student_ID', 'Invoice_ID', 'Amount_Paid', 'Payment_Method', 'Payment_Date', 'Status', 'Verified_At', 'Created_At', 'Updated_At', 'Reference_Number', 'Receipt_Number', 'Payment_Type'])))->values()).PHP_EOL;
    echo 'AZKA_LEDGER '.json_encode($transactions->filter(fn ($t) => $azkaPayments->contains('Payment_ID', $t['Reference_ID'] ?? '') || $azkaInvoices->contains('Invoice_ID', $t['Reference_ID'] ?? ''))->values()).PHP_EOL;

    $counterfactual = $out;
    foreach ($counterfactual['FINANCE_INVOICE']['rows'] as &$i) {
        if ($i['Student_ID'] === 'STD000003') {
            $i['Status'] = 'Cancelled';
            $i['Is_Active'] = 'FALSE';
        }
    }
    unset($i);
    $wire($counterfactual);
    $after = app(EducationPaymentMonitoringService::class)->build();
    echo 'IN_MEMORY_CANCELLED_COUNTERFACTUAL '.json_encode($after['groups']->flatMap(fn ($g) => $g['students'])->firstWhere('student_id', 'STD000003')).PHP_EOL;
    echo 'PRODUCTION_MUTATIONS=0'.PHP_EOL;
    echo 'SCHEMA_MUTATIONS=0'.PHP_EOL;
} catch (Throwable $e) {
    echo get_class($e).': '.$e->getMessage().PHP_EOL;
    exit(2);
}
