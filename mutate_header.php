<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
    $reflection = new \ReflectionClass($userRepo);
    $serviceProperty = $reflection->getParentClass()->getProperty('service');
    $serviceProperty->setAccessible(true);
    $service = $serviceProperty->getValue($userRepo);

    $spreadsheetIdProperty = $reflection->getParentClass()->getProperty('spreadsheetId');
    $spreadsheetIdProperty->setAccessible(true);
    $spreadsheetId = $spreadsheetIdProperty->getValue($userRepo);

    $sheetNameProperty = $reflection->getParentClass()->getProperty('sheetName');
    $sheetNameProperty->setAccessible(true);
    $sheetName = $sheetNameProperty->getValue($userRepo);

    if ($sheetName !== 'MASTER_USER') {
        throw new \Exception("Wrong sheet name: $sheetName");
    }

    // BEFORE MUTATION
    $range = $sheetName . '!1:1';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $headers = $response->getValues()[0] ?? [];
    
    $beforeCount = count($headers);
    echo "BEFORE HEADER COUNT: $beforeCount\n";
    
    $phoneFound = in_array('Phone_Number', $headers);
    if ($phoneFound) {
        echo "Phone_Number already exists!\n";
    } else {
        // MUTATION
        $headers[] = 'Phone_Number';
        $updateRange = $sheetName . '!1:1';
        $body = new \Google_Service_Sheets_ValueRange([
            'values' => [$headers]
        ]);
        $params = [
            'valueInputOption' => 'RAW'
        ];
        $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
        echo "Phone_Number added.\n";
    }

    // AFTER MUTATION
    $responseAfter = $service->spreadsheets_values->get($spreadsheetId, $range);
    $headersAfter = $responseAfter->getValues()[0] ?? [];
    $afterCount = count($headersAfter);
    echo "AFTER HEADER COUNT: $afterCount\n";
    echo "Phone_Number present: " . (in_array('Phone_Number', $headersAfter) ? 'Yes' : 'No') . "\n";
    
    // clear cache just in case
    $userRepo->clearCache();

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
