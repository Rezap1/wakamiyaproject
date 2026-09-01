<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$attendanceRepo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
$all = collect($attendanceRepo->fetchAll());
foreach ($all as $a) {
    $str = json_encode($a);
    if (str_contains($str, 'STD000001') || str_contains($str, 'USR000010') || str_contains($str, 'Helmi')) {
        print_r($a);
    }
}
