<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
$students = collect($studentRepo->fetchAll());
$student = $students->firstWhere('User_ID', 'U-001');
print_r($student);

$classRepo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
print_r(collect($classRepo->fetchAll())->where('Class_ID', $student['Class_ID'])->first());

$attendanceRepo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
$attendances = collect($attendanceRepo->fetchAll())->where('Student_ID', $student['Student_ID'])->values();
echo "Attendances:\n";
print_r($attendances);
