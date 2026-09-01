<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = app(\App\Services\Core\UserService::class)->getAllUsers();
$rezap1 = collect($users)->where('Username', 'Rezap1')->first();
print_r($rezap1);

if ($rezap1) {
    $teachers = app(\App\Services\Core\TeacherService::class)->getAllTeachers();
    $teacher = collect($teachers)->where('User_ID', $rezap1['User_ID'])->first();
    echo "\nTeacher Data:\n";
    print_r($teacher);
}
