<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Google_Client;
use Google_Service_Sheets;

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

$sheetName = 'MASTER_ATTENDANCE';
$newColumns = ['Teacher_ID', 'Attendance_Date', 'Check_In_Time', 'Check_Out_Time', 'Semester', 'Academic_Year', 'Session_Status', 'Grace_Period'];

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
        } else {
            echo "Columns already exist in $sheetName.\n";
        }
    } else {
        echo "Header row not found in $sheetName.\n";
    }
} catch (\Exception $e) {
    echo "Error appending to $sheetName: " . $e->getMessage() . "\n";
}
