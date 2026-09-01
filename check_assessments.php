<?php
require __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$client->setAuthConfig(__DIR__ . '/storage/app/google-credentials.json');

$service = new \Google_Service_Sheets($client);
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$spreadsheetId = config('services.google.spreadsheet_id');

try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'ASSESSMENTS!A1:Z1');
    $values = $response->getValues();

    if (!empty($values)) {
        echo "Headers of ASSESSMENTS:\n";
        print_r($values[0]);
    } else {
        echo "No headers found in ASSESSMENTS.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
