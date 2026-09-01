<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$repo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
$repo->deleteRow('ATT-TEST-001');
echo 'Hard deleted ATT-TEST-001';
