<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
print_r(collect($teacherRepo->fetchAll())->first());
