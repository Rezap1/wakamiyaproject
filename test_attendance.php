<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$attendanceRepo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
$attendances = collect($attendanceRepo->fetchAll())->where('Student_ID', 'STD000001')->values();
echo "Attendances:\n";
print_r($attendances->toArray());
