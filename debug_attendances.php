<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$repo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
$data = $repo->fetchAll()->toArray();
echo json_encode($data, JSON_PRETTY_PRINT);
