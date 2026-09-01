<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$repo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
$repo->softDelete('ATT-TEST-001');
echo 'Deleted ATT-TEST-001';
