<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
$roleRepo = app(\App\Interfaces\GoogleSheets\RoleRepositoryInterface::class);

$teacherRole = collect($roleRepo->fetchAll())->firstWhere('Role_Name', 'TEACHER');
$users = collect($userRepo->fetchAll())->where('Role_ID', $teacherRole['Role_ID']);

echo "Users with TEACHER role:\n";
foreach($users as $u) {
    echo $u['User_ID'] . " - " . $u['Full_Name'] . "\n";
}

$teachers = $teacherRepo->fetchAll();
echo "\nTeachers in MASTER_TEACHER:\n";
foreach($teachers as $t) {
    echo $t['Teacher_ID'] . " - " . $t['Full_Name'] . " (User_ID: " . $t['User_ID'] . ")\n";
}
