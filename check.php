<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$user = collect($userRepo->fetchAll())->firstWhere('Username', 'Rezap1') ?? collect($userRepo->fetchAll())->firstWhere('Full_Name', 'Rezap1');
$studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
$student = collect($studentRepo->fetchAll())->firstWhere('User_ID', $user['User_ID'] ?? '');
if(!$student) {
    $student = collect($studentRepo->fetchAll())->firstWhere('Full_Name', $user['Full_Name'] ?? $user['Username'] ?? '');
}
dump(['user' => $user, 'student' => $student]);
