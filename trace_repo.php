<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $repo = app(\App\Interfaces\GoogleSheets\AccountRepositoryInterface::class);
    $reflection = new \ReflectionClass($repo);
    
    $propId = $reflection->getParentClass()->getProperty('spreadsheetId');
    $propId->setAccessible(true);
    echo "SPREADSHEET ID: " . $propId->getValue($repo) . "\n";
    
    $propName = $reflection->getParentClass()->getProperty('sheetName');
    $propName->setAccessible(true);
    echo "SHEET NAME: '" . $propName->getValue($repo) . "'\n";
    
    echo "Attempting to fetchAll...\n";
    $data = $repo->fetchAll();
    echo "Fetch success! Count: " . collect($data)->count() . "\n";
    
} catch (\Exception $e) {
    echo "Exception Class: " . get_class($e) . "\n";
    echo "Exception Message: " . $e->getMessage() . "\n";
}
