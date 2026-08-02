<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\ValueRange;

$spreadsheetId = config('services.google.spreadsheet_id');

$client = new Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$credentialsPath = storage_path('app/google-credentials.json');
$client->setAuthConfig($credentialsPath);
$service = new Sheets($client);

// Get existing sheets
$spreadsheet = $service->spreadsheets->get($spreadsheetId);
$existingSheets = [];
foreach ($spreadsheet->getSheets() as $sheet) {
    $existingSheets[] = $sheet->getProperties()->getTitle();
}

$sheetsToAdd = [
    'FINANCE_INVOICE' => ['Invoice_ID', 'Student_ID', 'Period', 'Amount', 'Description', 'Status', 'Due_Date', 'Created_At', 'Updated_At'],
    'FINANCE_PAYMENT' => ['Payment_ID', 'Invoice_ID', 'Student_ID', 'Amount_Paid', 'Payment_Date', 'Payment_Method', 'Reference_Number', 'Proof_Image', 'Status', 'Verified_By', 'Verified_At', 'Notes', 'Created_At', 'Updated_At'],
    'MASTER_NOTIFICATION' => ['Notification_ID', 'User_ID', 'Role', 'Title', 'Message', 'Type', 'Priority', 'Is_Read', 'Created_At'],
    'ASSESSMENTS' => ['Assessment_ID', 'Subject_ID', 'Class_ID', 'Title', 'Type', 'Max_Score', 'Date', 'Status', 'Created_At'],
    'MASTER_ACADEMIC_YEAR' => ['id', 'Year_Name', 'Status', 'Start_Date', 'End_Date'],
    'MASTER_SCORE' => ['Score_ID', 'Assessment_ID', 'Student_ID', 'Score', 'Grade', 'Remarks', 'Status', 'Created_At', 'Updated_At'],
    'MASTER_ATTENDANCE' => ['Attendance_ID', 'Class_ID', 'Date', 'Student_ID', 'Status', 'Remarks', 'Recorded_By', 'Created_At', 'Updated_At'],
];

$requests = [];
foreach ($sheetsToAdd as $sheetTitle => $headers) {
    if (!in_array($sheetTitle, $existingSheets)) {
        echo "Missing sheet detected: $sheetTitle. Queuing creation...\n";
        $addSheetRequest = new AddSheetRequest();
        $properties = new SheetProperties();
        $properties->setTitle($sheetTitle);
        $addSheetRequest->setProperties($properties);
        
        $request = new Request();
        $request->setAddSheet($addSheetRequest);
        $requests[] = $request;
    }
}

if (!empty($requests)) {
    $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
        'requests' => $requests
    ]);
    try {
        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        echo "Missing sheets created successfully.\n";
    } catch (\Exception $e) {
        echo "Error creating sheets: " . $e->getMessage() . "\n";
    }
} else {
    echo "No missing sheets needed creation.\n";
}

// Add headers
foreach ($sheetsToAdd as $sheetTitle => $headers) {
    try {
        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetTitle . '!1:1');
        $values = $response->getValues();
        
        if (empty($values)) {
            echo "Adding headers to $sheetTitle...\n";
            $body = new ValueRange(['values' => [$headers]]);
            $params = ['valueInputOption' => 'RAW'];
            $service->spreadsheets_values->update($spreadsheetId, $sheetTitle . '!A1', $body, $params);
            echo "Headers added to $sheetTitle.\n";
        }
    } catch (\Exception $e) {
        // Just ignore if it fails
    }
}

echo "Thorough QA Setup Complete.\n";
?>
