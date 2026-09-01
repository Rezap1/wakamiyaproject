<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$classRepo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
$classes = collect($classRepo->fetchAll());
$class = $classes->firstWhere('Class_ID', 'CLS000002');
echo "Class Name: " . ($class['Class_Name'] ?? 'Not Found') . "\n";

$batchRepo = app(\App\Interfaces\GoogleSheets\BatchRepositoryInterface::class);
$batches = collect($batchRepo->fetchAll());
$batch = $batches->firstWhere('Batch_ID', 'BAT000002');
echo "Batch Name: " . ($batch['Batch_Name'] ?? 'Not Found') . "\n";
