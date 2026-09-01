<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ClearValuesRequest;
use Illuminate\Support\Facades\Cache;

$spreadsheetId = config('services.google.spreadsheet_id');
$credentialsPath = storage_path('app/google-credentials.json');

$client = new Google_Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$client->setAuthConfig($credentialsPath);

$service = new Google_Service_Sheets($client);

// Clear range MASTER_PAYROLL!A2:Z500
$clearRequest = new Google_Service_Sheets_ClearValuesRequest();
$service->spreadsheets_values->clear($spreadsheetId, 'MASTER_PAYROLL!A2:Z500', $clearRequest);

Cache::flush();

$payrollRepo = app(\App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class);
$remaining = $payrollRepo->getAll();

echo "Hard clear completed. Remaining payroll records: " . count($remaining) . "\n";
