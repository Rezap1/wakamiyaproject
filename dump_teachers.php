<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
$teachers = $teacherRepo->fetchAll();

echo "Teachers in MASTER_TEACHER:\n";
foreach($teachers as $t) {
    echo "- Teacher_ID: " . ($t['Teacher_ID'] ?? 'MISSING') . "\n";
    echo "  User_ID: " . ($t['User_ID'] ?? 'MISSING') . "\n";
    echo "  Full_Name: " . ($t['Full_Name'] ?? 'MISSING') . "\n";
    echo "  Is_Active: '" . ($t['Is_Active'] ?? 'MISSING') . "'\n";
}
