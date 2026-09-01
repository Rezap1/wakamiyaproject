<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Core\UserService;

$userService = app(UserService::class);
$users = $userService->getAllUsers();

echo "Total users count: " . count($users) . "\n";
foreach ($users as $u) {
    echo "User_ID: " . ($u['User_ID'] ?? '') . " | Name: " . ($u['Full_Name'] ?? '') . " | Email: " . ($u['Email'] ?? '') . "\n";
}
