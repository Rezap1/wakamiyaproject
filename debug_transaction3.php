<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $repo = app(\App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class);
    
    $transactionData = [
        'Transaction_ID' => $repo->generateNewId(),
        'Transaction_Date' => now()->format('Y-m-d'),
        'Account_ID' => '102',
        'Type' => 'Income',
        'Category' => 'Payment Receipt',
        'Amount' => 1500000,
        'Reference_Type' => 'Payment',
        'Reference_ID' => 'RCT-STU-2026-000001',
        'Description' => "Test Transaction",
        'Is_Active' => 'TRUE',
        'Created_By' => 'SYSTEM',
        'Created_At' => now()->toDateTimeString(),
        'Updated_At' => now()->toDateTimeString()
    ];
    
    echo "Creating transaction directly...\n";
    $repo->create($transactionData);
    echo "Transaction created successfully in repo!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
