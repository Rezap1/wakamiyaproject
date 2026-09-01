<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$service = app(\App\Services\Core\SystemSettingService::class);
print_r($service->category('Academic')->toArray());
print_r($service->getParameters()->where('Module', 'Academic')->toArray());
