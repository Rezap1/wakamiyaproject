<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$settingRepo = app(\App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface::class);
$settingRepo->clearCache();
$settings = collect($settingRepo->fetchAll());

echo "Keys in MASTER_SYSTEM_SETTING:\n";
foreach($settings as $setting) {
    echo "- " . ($setting['Setting_Key'] ?? 'MISSING_KEY') . "\n";
}
