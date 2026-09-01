<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$allUsers = $userRepo->fetchAll();
$finUser = collect($allUsers)->first(function($u) {
    return str_contains(strtoupper($u['Role_ID'] ?? ''), 'ROLE003') || str_contains(strtoupper($u['Role'] ?? ''), 'FINANCE') || str_contains(strtoupper($u['email'] ?? ''), 'finance');
}) ?? $allUsers[0];
$user = new User($finUser);

Auth::setUser($user);

echo "Logged in as: " . $user->Full_Name . " (" . $user->email . ")\n";

try {
    $html = view('dashboard.finance', [
        'kpi' => [],
        'charts' => [],
        'reminders' => [],
        'recentActivities' => []
    ])->render();
    echo "Render Success! HTML Output Length: " . strlen($html) . " bytes.\n";
    file_put_contents(__DIR__ . '/dashboard_output.html', $html);
} catch (\Throwable $e) {
    echo "RENDER ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
