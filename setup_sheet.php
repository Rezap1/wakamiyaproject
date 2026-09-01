<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_ValueRange;

try {
    $spreadsheetId = config('services.google.spreadsheet_id');
    $client = new Google_Client();
    $client->setApplicationName('Wakamiya Management System');
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig(storage_path('app/google-credentials.json'));
    
    $service = new Google_Service_Sheets($client);
    
    $sheetName = 'MASTER_PERMANENT_QR';
    
    // Check if sheet exists
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    $exists = false;
    foreach ($spreadsheet->getSheets() as $sheet) {
        if ($sheet->getProperties()->getTitle() === $sheetName) {
            $exists = true;
            break;
        }
    }
    
    if (!$exists) {
        echo "Creating sheet: {$sheetName}...\n";
        $body = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => [
                [
                    'addSheet' => [
                        'properties' => [
                            'title' => $sheetName
                        ]
                    ]
                ]
            ]
        ]);
        $service->spreadsheets->batchUpdate($spreadsheetId, $body);
        echo "Sheet created.\n";
    } else {
        echo "Sheet {$sheetName} already exists.\n";
    }
    
    // Set Headers
    echo "Setting headers...\n";
    $headers = ['QR_ID', 'QR_TYPE', 'IDENTIFIER', 'LABEL', 'STATUS', 'CREATED_AT', 'CREATED_BY', 'UPDATED_AT', 'DEACTIVATED_AT'];
    $body = new Google_Service_Sheets_ValueRange([
        'values' => [$headers]
    ]);
    $params = ['valueInputOption' => 'USER_ENTERED'];
    $service->spreadsheets_values->update($spreadsheetId, $sheetName . '!A1:I1', $body, $params);
    echo "Headers set successfully.\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
