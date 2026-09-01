<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$attendanceRepo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
$all = collect($attendanceRepo->fetchAll());
$studentId = 'STD000002';
$myAttendances = $all->where('Student_ID', $studentId);
$presentCount = $myAttendances->filter(function($a) {
    return in_array(strtoupper($a['Status'] ?? ''), ['PRESENT', 'LATE', 'HADIR', 'TERLAMBAT']);
})->count();
$total = $myAttendances->count();
echo 'Percentage: ' . ($total > 0 ? round(($presentCount / $total) * 100) : 0) . "%\n";
