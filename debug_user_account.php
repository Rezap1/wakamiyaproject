<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test Account Default
$account = app(\App\Services\Finance\AccountService::class)->getDefaultTransactionAccount();
if (!$account) {
    echo "Default Account: NULL\n";
    $repo = app(\App\Interfaces\GoogleSheets\AccountRepositoryInterface::class);
    $all = $repo->fetchAll();
    echo "All Accounts count: " . count($all) . "\n";
    if (count($all) > 0) {
        print_r($all->first());
    }
} else {
    echo "Default Account Found:\n";
    print_r($account);
}

// Test User Role
$user = \App\Models\User::first();
echo "\nFirst User:\n";
echo "Name: " . ($user->User_Name ?? $user->Name ?? $user->name ?? 'N/A') . "\n";
echo "Role: " . ($user->Role_ID ?? $user->Role ?? $user->role ?? 'N/A') . "\n";
