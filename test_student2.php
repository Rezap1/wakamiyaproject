<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
$students = collect($studentRepo->fetchAll());
print_r($students->pluck('User_ID')->toArray());
print_r($students->first());
