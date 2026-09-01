<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
$classRepo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
$batchRepo = app(\App\Interfaces\GoogleSheets\BatchRepositoryInterface::class);

$students = $studentRepo->fetchAll();
$classes = $classRepo->fetchAll();
$batches = $batchRepo->fetchAll();

echo "Students count: " . count($students) . "\n";
if (count($students) > 0) {
    echo "Student sample keys:\n";
    print_r(array_keys($students[0]));
    echo "\nSample student record:\n";
    print_r($students[0]);
}

echo "\nClasses count: " . count($classes) . "\n";
if (count($classes) > 0) {
    echo "Class sample record:\n";
    print_r($classes[0]);
}

echo "\nBatches count: " . count($batches) . "\n";
if (count($batches) > 0) {
    echo "Batch sample record:\n";
    print_r($batches[0]);
}
