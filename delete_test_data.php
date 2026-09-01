<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);

$teacherId = 'TCH000002';
$userId = 'USR000010';

echo "Deleting Teacher: " . $teacherId . "\n";
try {
    $teacherRepo->delete($teacherId);
    echo "Successfully deleted Teacher.\n";
} catch (\Exception $e) {
    echo "Error deleting Teacher: " . $e->getMessage() . "\n";
}

echo "Deleting User: " . $userId . "\n";
try {
    $userRepo->delete($userId);
    echo "Successfully deleted User.\n";
} catch (\Exception $e) {
    echo "Error deleting User: " . $e->getMessage() . "\n";
}
