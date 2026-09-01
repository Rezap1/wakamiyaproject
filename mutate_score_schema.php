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
    $response = $service->spreadsheets_values->get($spreadsheetId, 'MASTER_SCORE!1:1');
    $headers = $response->getValues()[0] ?? [];

    if (!in_array('Assessment_Category', $headers)) {
        $headers[] = 'Assessment_Category';
    }
    if (!in_array('Evaluation_Details', $headers)) {
        $headers[] = 'Evaluation_Details';
    }

    $body = new \Google_Service_Sheets_ValueRange([
        'values' => [$headers]
    ]);

    $params = [
        'valueInputOption' => 'USER_ENTERED'
    ];

    $result = $service->spreadsheets_values->update(
        $spreadsheetId,
        'MASTER_SCORE!1:1',
        $body,
        $params
    );

    echo "Successfully updated MASTER_SCORE schema.\n";
    print_r($headers);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
