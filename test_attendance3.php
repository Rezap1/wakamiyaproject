<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$attendanceRepo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
$all = collect($attendanceRepo->fetchAll());
print_r($all->first());
