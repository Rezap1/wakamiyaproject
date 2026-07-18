<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$client = new Google_Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$client->setAuthConfig(storage_path('app/google-credentials.json'));

$service = new Google_Service_Sheets($client);
$response = $service->spreadsheets_values->get(config('services.google.spreadsheet_id'), 'MATCHING!1:1');
print_r($response->getValues()[0]);
