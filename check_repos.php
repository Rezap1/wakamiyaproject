<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$classes = app(App\Interfaces\GoogleSheets\ClassRepositoryInterface::class)->getAll();
$subjects = app(App\Interfaces\GoogleSheets\SubjectRepositoryInterface::class)->getAll();

print_r($classes[0] ?? []);
print_r($subjects[0] ?? []);
