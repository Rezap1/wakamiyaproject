<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$service = app(\App\Services\Core\SystemSettingService::class);
$result = $service->set('SET_ACADEMIC_YEAR', '2025/2026', 'test@example.com');
echo $result ? 'Success' : 'Failed';
