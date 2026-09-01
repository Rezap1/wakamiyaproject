<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$repo = new \App\Repositories\GoogleSheets\ScoreRepository();
$reflection = new ReflectionClass($repo);
$serviceProp = $reflection->getProperty('service');
$serviceProp->setAccessible(true);
$service = $serviceProp->getValue($repo);

$spreadsheetIdProp = $reflection->getProperty('spreadsheetId');
$spreadsheetIdProp->setAccessible(true);
$spreadsheetId = $spreadsheetIdProp->getValue($repo);

try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'MASTER_SCORE!1:1');
    $values = $response->getValues();

    if (!empty($values)) {
        echo "Headers of MASTER_SCORE:\n";
        print_r($values[0]);
    } else {
        echo "No headers found in MASTER_SCORE.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
