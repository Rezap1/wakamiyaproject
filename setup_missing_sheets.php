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
if (file_exists($credentialsPath)) {
    $client->setAuthConfig($credentialsPath);
} else {
    die("Credentials not found\n");
}

$service = new Sheets($client);

// 1. Get existing sheets
$spreadsheet = $service->spreadsheets->get($spreadsheetId);
$existingSheets = [];
foreach ($spreadsheet->getSheets() as $sheet) {
    $existingSheets[] = $sheet->getProperties()->getTitle();
}

$sheetsToAdd = [
    'MASTER_PAYROLL' => ['Payroll_ID', 'Employee_ID', 'Period', 'Base_Salary', 'Total_Allowances', 'Total_Deductions', 'Net_Salary', 'Status', 'Generated_At', 'Approved_At', 'Paid_At'],
    'MASTER_SALARY_COMPONENT' => ['Component_ID', 'Type', 'Name', 'Amount', 'Is_Active', 'Created_At', 'Updated_At'],
    'MASTER_DOCUMENT' => ['Document_ID', 'Document_Number', 'Module', 'Reference_ID', 'Template_ID', 'Title', 'Content', 'Generated_By', 'Generated_Date', 'Status', 'QR_Code', 'Signature', 'Created_At', 'Updated_At'],
    'MASTER_DOCUMENT_TEMPLATE' => ['Template_ID', 'Module', 'Title', 'Header', 'Body', 'Footer', 'Is_Active', 'Created_At', 'Updated_At'],
    'MASTER_WORKFLOW' => ['Workflow_ID', 'Module', 'Role', 'Step_Order', 'Is_Active'],
    'MASTER_APPROVAL' => ['Approval_ID', 'Module', 'Reference_Type', 'Reference_ID', 'Current_Role', 'Status', 'Requester_ID', 'Created_At', 'Updated_At'],
    'MASTER_APPROVAL_HISTORY' => ['History_ID', 'Approval_ID', 'Workflow_ID', 'Action', 'Old_Status', 'New_Status', 'Remarks', 'Acted_By', 'Created_At'],
    'MASTER_AUDIT_LOG' => ['Audit_ID', 'User_ID', 'Role', 'Department', 'Module', 'Reference_Type', 'Reference_ID', 'Action', 'Old_Value', 'New_Value', 'IPAddress', 'Device', 'Browser', 'Operating_System', 'Location', 'Status', 'Created_At'],
    'MASTER_SYSTEM_SETTING' => ['Setting_ID', 'Category', 'Setting_Key', 'Setting_Name', 'Setting_Value', 'Value_Type', 'Description', 'Is_Public', 'Status', 'Created_By', 'Updated_By', 'Created_At', 'Updated_At'],
    'MASTER_SYSTEM_PARAMETER' => ['Parameter_ID', 'Module', 'Parameter_Key', 'Parameter_Value', 'Description', 'Status', 'Created_At', 'Updated_At'],
];

$requests = [];

foreach ($sheetsToAdd as $sheetTitle => $headers) {
    if (!in_array($sheetTitle, $existingSheets)) {
        echo "Preparing to create sheet: $sheetTitle\n";
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
        echo "Sheets created successfully.\n";
    } catch (\Exception $e) {
        echo "Error creating sheets: " . $e->getMessage() . "\n";
    }
} else {
    echo "All sheets already exist.\n";
}

// 2. Add headers to the newly created (or existing but empty) sheets
foreach ($sheetsToAdd as $sheetTitle => $headers) {
    try {
        // Check if header exists
        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetTitle . '!1:1');
        $values = $response->getValues();
        
        if (empty($values)) {
            echo "Adding headers to $sheetTitle...\n";
            $body = new ValueRange([
                'values' => [$headers]
            ]);
            $params = ['valueInputOption' => 'RAW'];
            $service->spreadsheets_values->update($spreadsheetId, $sheetTitle . '!A1', $body, $params);
            echo "Headers added to $sheetTitle.\n";
        }
    } catch (\Exception $e) {
        echo "Error adding headers to $sheetTitle: " . $e->getMessage() . "\n";
    }
}

echo "Setup Complete.\n";
?>
