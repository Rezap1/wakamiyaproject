<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$repo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
print_r(collect($repo->fetchAll())->where('Student_ID', 'STD000002')->toArray());
