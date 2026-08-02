<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function addUserIdToSheet($sheetName) {
    $repo = new \App\Repositories\GoogleSheets\EmployeeRepository();
    $reflection = new ReflectionClass($repo);
    
    $serviceProp = $reflection->getProperty('service');
    $serviceProp->setAccessible(true);
    $service = $serviceProp->getValue($repo);
    
    $spreadsheetIdProp = $reflection->getProperty('spreadsheetId');
    $spreadsheetIdProp->setAccessible(true);
    $spreadsheetId = $spreadsheetIdProp->getValue($repo);
    
    $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName . '!1:1');
    $headers = $response->getValues()[0] ?? [];
    
    if (!in_array('User_ID', $headers)) {
        $headers[] = 'User_ID';
        $body = new \Google_Service_Sheets_ValueRange(['values' => [$headers]]);
        $params = ['valueInputOption' => 'USER_ENTERED'];
        $service->spreadsheets_values->update($spreadsheetId, $sheetName . '!1:1', $body, $params);
        echo 'Added User_ID to ' . $sheetName . PHP_EOL;
    } else {
        echo 'User_ID already exists in ' . $sheetName . PHP_EOL;
    }
}

addUserIdToSheet('MASTER_EMPLOYEE');
addUserIdToSheet('MASTER_STUDENT');
addUserIdToSheet('MASTER_TEACHER');
