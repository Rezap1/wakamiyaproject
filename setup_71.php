<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_AddSheetRequest;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_Request;
use Google_Service_Sheets_SheetProperties;

$client = new Google_Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');

$credentialsPath = storage_path('app/google-credentials.json');
if (file_exists($credentialsPath)) {
    $client->setAuthConfig($credentialsPath);
} else {
    die("Credentials not found\n");
}

$service = new Google_Service_Sheets($client);
$spreadsheetId = config('services.google.spreadsheet_id');

// 1. Append columns to ASSIGNMENT and SUBMISSION
$updates = [
    'MASTER_ASSIGNMENT' => ['Schedule_ID', 'Assignment_Type', 'Attachment', 'Publish_Date', 'Maximum_Score', 'Status'],
    'MASTER_SUBMISSION' => ['Comment']
];

foreach ($updates as $sheetName => $newColumns) {
    try {
        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName . '!1:1');
        $values = $response->getValues();
        
        if (!empty($values)) {
            $headers = $values[0];
            $added = false;
            foreach ($newColumns as $col) {
                if (!in_array($col, $headers)) {
                    $headers[] = $col;
                    $added = true;
                }
            }
            if ($added) {
                $body = new \Google_Service_Sheets_ValueRange([
                    'values' => [$headers]
                ]);
                $params = ['valueInputOption' => 'USER_ENTERED'];
                $service->spreadsheets_values->update($spreadsheetId, $sheetName . '!1:1', $body, $params);
                echo "Appended columns to $sheetName.\n";
            }
        }
    } catch (\Exception $e) {
        echo "Error appending to $sheetName: " . $e->getMessage() . "\n";
    }
}

// 2. Create MASTER_NOTIFICATION sheet
try {
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    $existingSheets = [];
    foreach ($spreadsheet->getSheets() as $sheet) {
        $existingSheets[] = $sheet->getProperties()->getTitle();
    }

    if (!in_array('MASTER_NOTIFICATION', $existingSheets)) {
        $addSheetRequest = new Google_Service_Sheets_AddSheetRequest();
        $properties = new Google_Service_Sheets_SheetProperties();
        $properties->setTitle('MASTER_NOTIFICATION');
        $addSheetRequest->setProperties($properties);
        
        $request = new Google_Service_Sheets_Request();
        $request->setAddSheet($addSheetRequest);
        
        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => [$request]
        ]);
        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        echo "Created MASTER_NOTIFICATION sheet.\n";
    }
    
    // Add headers to MASTER_NOTIFICATION
    $response = $service->spreadsheets_values->get($spreadsheetId, 'MASTER_NOTIFICATION!1:1');
    if (empty($response->getValues())) {
        $headers = ['Notification_ID', 'User_ID', 'Title', 'Message', 'Is_Read', 'Link', 'Created_At', 'Updated_At'];
        $body = new \Google_Service_Sheets_ValueRange(['values' => [$headers]]);
        $params = ['valueInputOption' => 'USER_ENTERED'];
        $service->spreadsheets_values->append($spreadsheetId, 'MASTER_NOTIFICATION!A1', $body, $params);
        echo "Added headers to MASTER_NOTIFICATION.\n";
    }
    
} catch (\Exception $e) {
    echo "Error with MASTER_NOTIFICATION: " . $e->getMessage() . "\n";
}
