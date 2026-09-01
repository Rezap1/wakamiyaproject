<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$spreadsheetId = config('services.google.spreadsheet_id');
$credentialsPath = storage_path('app/google-credentials.json');
$targetSchemas = config('finance.schema', []);
$dryRun = in_array('--dry-run', $argv, true);

if (!$spreadsheetId) {
    fwrite(STDERR, "Missing Google spreadsheet ID.\n");
    exit(1);
}

if (!is_file($credentialsPath)) {
    fwrite(STDERR, "Missing Google credentials file: {$credentialsPath}\n");
    exit(1);
}

if ($targetSchemas === []) {
    fwrite(STDERR, "Missing finance schema contract.\n");
    exit(1);
}

$client = new \Google_Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$client->setAuthConfig($credentialsPath);
$service = new \Google_Service_Sheets($client);

$backupDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wms_h827_schema_backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Unable to create backup directory: {$backupDir}\n");
    exit(1);
}

$timestamp = date('Ymd_His');
$backupPath = $backupDir . DIRECTORY_SEPARATOR . "finance_schema_backup_{$timestamp}.json";
$backup = [
    'timestamp' => $timestamp,
    'spreadsheet_id' => $spreadsheetId,
    'sheets' => [],
];

$updates = [];

foreach ($targetSchemas as $sheetName => $requiredHeaders) {
    echo "Reading {$sheetName}...\n";
    $values = $service->spreadsheets_values->get($spreadsheetId, $sheetName)->getValues();

    if (empty($values)) {
        fwrite(STDERR, "Sheet {$sheetName} has no header row.\n");
        exit(1);
    }

    $currentHeaders = array_map(static fn ($header) => trim((string) $header), $values[0] ?? []);
    $normalized = [];
    foreach ($currentHeaders as $header) {
        if ($header === '') {
            continue;
        }
        $key = strtolower($header);
        $normalized[$key] = ($normalized[$key] ?? 0) + 1;
    }

    $duplicateHeaders = array_keys(array_filter($normalized, static fn (int $count): bool => $count > 1));
    if ($duplicateHeaders !== []) {
        fwrite(STDERR, "Duplicate headers detected in {$sheetName}: " . implode(', ', $duplicateHeaders) . "\n");
        exit(1);
    }

    $missingHeaders = array_values(array_diff($requiredHeaders, $currentHeaders));
    $updatedHeaders = array_merge($currentHeaders, $missingHeaders);

    $backup['sheets'][$sheetName] = [
        'current_headers' => $currentHeaders,
        'required_headers' => array_values($requiredHeaders),
        'missing_headers' => $missingHeaders,
        'row_count' => max(count($values) - 1, 0),
        'rows' => array_slice($values, 1),
    ];

    echo $sheetName . ': current=' . count($currentHeaders)
        . ' required=' . count($requiredHeaders)
        . ' missing=' . count($missingHeaders) . "\n";

    $unknownHeaders = array_values(array_diff($currentHeaders, $requiredHeaders));
    if ($unknownHeaders !== []) {
        echo $sheetName . ': extra=' . implode(', ', $unknownHeaders) . "\n";
    }

    if ($missingHeaders !== []) {
        $updates[$sheetName] = $updatedHeaders;
    }
}

file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Backup written to {$backupPath}\n";

if ($dryRun) {
    echo "Dry run only; no production write performed.\n";
    exit(0);
}

foreach ($updates as $sheetName => $headers) {
    echo "Updating {$sheetName} header row...\n";
    $body = new \Google_Service_Sheets_ValueRange([
        'values' => [$headers],
    ]);
    $service->spreadsheets_values->update(
        $spreadsheetId,
        $sheetName . '!1:1',
        $body,
        ['valueInputOption' => 'RAW']
    );
}

echo "Schema alignment complete.\n";
