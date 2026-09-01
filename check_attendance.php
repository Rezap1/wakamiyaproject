<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$attService = app(\App\Services\Academic\AttendanceService::class);
$attendances = $attService->getAll();
$todays = $attendances->filter(function($a) { return $a['Attendance_Date'] === '2026-08-20'; });
print_r($todays->toArray());
