<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$app = app();
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$repo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$users = $repo->fetchAll();
$student = collect($users)->firstWhere('Role', 'STUDENT');
echo $student['Email'] ?? 'No email found';
