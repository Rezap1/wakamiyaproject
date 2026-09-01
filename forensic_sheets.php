<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Google_Client;
use Google_Service_Sheets;

try {
    $runtimeSpreadsheetId = config('services.google.spreadsheet_id');
    echo "RUNTIME SPREADSHEET ID: " . $runtimeSpreadsheetId . "\n";
    
    $client = new Google_Client();
    $client->setApplicationName('Wakamiya Management System');
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig(storage_path('app/google-credentials.json'));
    
    $service = new Google_Service_Sheets($client);
    
    $spreadsheet = $service->spreadsheets->get($runtimeSpreadsheetId);
    
    echo "WORKSHEETS FOUND:\n";
    $masterAccountFound = false;
    foreach ($spreadsheet->getSheets() as $sheet) {
        $title = $sheet->getProperties()->getTitle();
        echo "- '" . $title . "'\n";
        if (strtoupper(trim($title)) === 'MASTER_ACCOUNT' || strcasecmp(trim($title), 'MASTER_ACCOUNT') === 0 || str_contains(strtoupper($title), 'MASTER_ACCOUNT')) {
            $masterAccountFound = true;
            echo "  *** MATCH FOUND: '" . $title . "' ***\n";
        }
    }
    
    echo "\nMASTER_ACCOUNT EXISTS IN RUNTIME SPREADSHEET: " . ($masterAccountFound ? "YES" : "NO") . "\n";
    
    if ($masterAccountFound) {
        $response = $service->spreadsheets_values->get($runtimeSpreadsheetId, 'MASTER_ACCOUNT!A1:Z1');
        $headers = $response->getValues()[0] ?? [];
        echo "HEADERS COUNT: " . count($headers) . "\n";
        echo "HEADERS: " . implode(', ', $headers) . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
