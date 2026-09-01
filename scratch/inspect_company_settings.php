<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Core\SystemSettingService;

$service = app(SystemSettingService::class);
$allSettings = $service->getSettings();

echo "All Settings Count: " . count($allSettings) . "\n\n";

foreach ($allSettings as $s) {
    $id = $s['Setting_ID'] ?? '';
    $key = $s['Setting_Key'] ?? '';
    $name = $s['Setting_Name'] ?? '';
    $val = $s['Setting_Value'] ?? '';
    if (str_contains(strtolower($key), 'company') || str_contains(strtolower($key), 'brand') || str_contains(strtolower($key), 'app') || str_contains(strtolower($id), 'company') || str_contains(strtolower($id), 'brand')) {
        echo "ID: {$id} | Key: {$key} | Name: {$name} | Value: {$val}\n";
    }
}

echo "\n--- getCompanyProfile() Output ---\n";
print_r($service->getCompanyProfile());
