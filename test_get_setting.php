<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$service = app(\App\Services\Core\SystemSettingService::class);
echo "ACADEMIC_YEAR: " . $service->get('ACADEMIC_YEAR') . "\n";
echo "SET_ACADEMIC_YEAR: " . $service->get('SET_ACADEMIC_YEAR') . "\n";
