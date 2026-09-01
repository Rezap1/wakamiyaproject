<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('Role_ID', 'MASTER')->first() ?? \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

// Simulate a POST request with a FAKE Account_ID
$request = \Illuminate\Http\Request::create('/finance/transactions', 'POST', [
    '_token' => csrf_token(),
    'Transaction_Date' => '2026-08-27',
    'Type' => 'Income',
    'Category' => 'Pembayaran SPP',
    'Amount' => 1500000,
    'Reference_Type' => 'Other',
    'Reference_ID' => 'TEST-001',
    'Description' => 'Test Transaction',
    'Account_ID' => 'HACKED-ACCOUNT-ID' // This should be ignored!
]);

$response = $kernel->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
if ($response->isRedirect()) {
    echo "Redirect URL: " . $response->getTargetUrl() . "\n";
}
if ($response->getStatusCode() === 500 && $response->exception) {
    echo "Exception: " . $response->exception->getMessage() . "\n";
}

// Check the database
$repo = app(\App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class);
$latest = collect($repo->fetchAll())->last();
echo "Stored Account_ID: " . $latest['Account_ID'] . "\n";
echo "Type: " . $latest['Type'] . "\n";
echo "Amount: " . $latest['Amount'] . "\n";

// Cleanup (delete the test transaction)
$repo->delete($latest['Transaction_ID']);
