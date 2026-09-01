<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
$students = collect($studentRepo->fetchAll());
$student2 = $students->firstWhere('User_ID', 'USR000012');
print_r($student2);
