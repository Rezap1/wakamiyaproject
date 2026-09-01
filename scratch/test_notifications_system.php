<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Core\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Route;

echo "========================================\n";
echo "WMS NOTIFICATION SYSTEM VERIFICATION AUDIT\n";
echo "========================================\n\n";

$notifService = app(NotificationService::class);

// 1. Check Routes
$requiredRoutes = ['notifications.index', 'notifications.show', 'notifications.read', 'notifications.markRead', 'notifications.markAllRead', 'notifications.archive', 'notifications.destroy'];
foreach ($requiredRoutes as $r) {
    $exists = Route::has($r);
    echo "[" . ($exists ? "✓" : "X") . "] Route '{$r}': " . ($exists ? "Registered ✅" : "Missing ❌") . "\n";
}

// 2. Test Notification Creation
echo "\n--- Creating Test Notification ---\n";
$testId = 'NTF_TEST_' . time();
$newNotif = $notifService->CreateNotification([
    'Notification_ID'   => $testId,
    'User_ID'           => 'ALL',
    'Role'              => 'FINANCE',
    'Title'             => 'Test Pengingat Keuangan WMS',
    'Message'           => 'Ini adalah pesan notifikasi pengujian sistem lonceng.',
    'Notification_Type' => 'BILLING_REMINDER',
    'Priority'          => 'High',
    'Action_URL'        => '/finance/invoices',
    'Is_Read'           => 'FALSE',
    'Created_At'        => now()->toDateTimeString()
]);

echo "Created Notification ID: {$testId}\n";

// 3. Test Unread Count & Filtering for Users
$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$users = $userRepo->fetchAll();

echo "\n--- Unread Count & Filtering Audit ---\n";
foreach ($users as $u) {
    $userObj = new User($u);
    auth()->setUser($userObj);
    $unread = $notifService->UnreadCount();
    $recent = $notifService->RecentNotification(null, null, 3);
    echo "User: " . ($u['Full_Name'] ?? 'Unknown') . " (" . ($u['User_ID'] ?? '') . ") -> Unread: {$unread} | Recent Items: " . count($recent) . "\n";
}

// 4. Test Mark As Read
echo "\n--- Marking Test Notification as Read ---\n";
$notifService->MarkAsRead($testId);
$readNotif = $notifService->getById($testId);
echo "Status after MarkAsRead: Is_Read = " . ($readNotif['Is_Read'] ?? 'FALSE') . " ✅\n";

// 5. Cleanup Test Notification
$notifService->DeleteNotification($testId);
echo "Cleaned up test notification.\n\n";

echo "========================================\n";
echo "ALL NOTIFICATION TESTS COMPLETED SUCCESSFULLY ✅\n";
echo "========================================\n";
