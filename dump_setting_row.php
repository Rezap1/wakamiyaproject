<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$settingRepo = app(\App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface::class);
$settings = collect($settingRepo->fetchAll());

$bankSetting = $settings->firstWhere('Setting_Key', 'COMPANY_BANK_NAME');
echo "Keys:\n";
print_r(array_keys($settings->first()));
echo "\nSetting for COMPANY_BANK_NAME:\n";
print_r($bankSetting);
