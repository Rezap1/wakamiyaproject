<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$scheduleRepo = app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class);
$all = collect($scheduleRepo->fetchAll());
print_r($all->where('Class_ID', 'CLS000002')->toArray());
