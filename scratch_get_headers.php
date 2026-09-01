<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = new Google_Client();
$client->setAuthConfig(storage_path('app/google-credentials.json'));
$client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
$service = new Google_Service_Sheets($client);
$response = $service->spreadsheets_values->get(config('services.google.spreadsheet_id'), 'MASTER_ATTENDANCE!A1:Z1');
print_r($response->getValues()[0] ?? []);
