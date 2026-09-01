<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$teachers = app(\App\Services\Core\TeacherService::class)->getAllTeachers();
print_r($teachers);
