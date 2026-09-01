<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$repo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
$all = collect($repo->fetchAll());
echo "EMPLOYEE DATA:\n";
foreach ($all as $item) {
    echo "ID: " . ($item['Employee_ID'] ?? '') . ", Status: " . ($item['Status'] ?? '') . "\n";
}

$acadRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
echo "\nSTUDENT DATA:\n";
foreach (collect($acadRepo->fetchAll()) as $item) {
    echo "ID: " . ($item['Student_ID'] ?? '') . ", Status: " . ($item['Status'] ?? '') . ", Is_Active: " . ($item['Is_Active'] ?? '') . "\n";
}
