<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$user = collect($userRepo->fetchAll())->firstWhere('User_ID', 'USR000010');
print_r($user);
