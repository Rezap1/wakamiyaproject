<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$firstUserRaw = collect($userRepo->fetchAll())->first();
if ($firstUserRaw) {
    // Inject 'id' for GenericUser compatibility
    $firstUserRaw['id'] = $firstUserRaw['User_ID'] ?? 'USR001';
    $genericUser = new \Illuminate\Auth\GenericUser($firstUserRaw);
    \Illuminate\Support\Facades\Auth::login($genericUser);
} else {
    // Mock the user for Auth
    $genericUser = new \Illuminate\Auth\GenericUser(['id' => 'SYS001', 'User_ID' => 'SYS001', 'Username' => 'SYSTEM']);
    \Illuminate\Support\Facades\Auth::login($genericUser);
}

$controller = app(\App\Http\Controllers\Finance\TransactionController::class);
$request = \Illuminate\Http\Request::create('/finance/transactions', 'POST', [
    'Transaction_Date' => '2026-08-27',
    'Type' => 'Income',
    'Category' => 'Pembayaran SPP',
    'Amount' => 1500000,
    'Reference_Type' => 'Other',
    'Reference_ID' => 'TEST-002',
    'Description' => 'Test Transaction',
    'Account_ID' => 'HACKED-ACCOUNT-ID' // This should be ignored!
]);

echo "TRANSACTION FORM POST:\n";
try {
    $response = $controller->store($request);
    echo "Class: " . get_class($response) . "\n";
    if (method_exists($response, 'getTargetUrl')) {
        echo "Redirect URL: " . $response->getTargetUrl() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// Check the database
$repo = app(\App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class);
$latest = collect($repo->fetchAll())->last();
echo "Stored Account_ID: " . $latest['Account_ID'] . "\n";
echo "Stored Created_By: " . $latest['Created_By'] . "\n";
echo "Type: " . $latest['Type'] . "\n";
echo "Amount: " . $latest['Amount'] . "\n";
if ($latest['Reference_ID'] === 'TEST-002') {
    echo "Deleting TEST-002...\n";
    $repo->delete($latest['Transaction_ID']);
}
