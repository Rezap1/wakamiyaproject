<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$repo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
$all = collect($repo->fetchAll())->map->only(['Employee_ID', 'Status', 'Is_Active'])->toArray();
print_r($all);

$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$users = collect($userRepo->fetchAll())->map->only(['User_ID', 'Role_ID', 'Full_Name'])->toArray();
print_r($users);
