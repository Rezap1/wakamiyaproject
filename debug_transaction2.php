<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Mock user
    $user = \App\Models\User::first();
    \Illuminate\Support\Facades\Auth::login($user);
    
    $ps = app(\App\Services\Finance\PaymentService::class);
    $ts = app(\App\Services\Finance\TransactionService::class);
    
    $paymentId = 'RCT-STU-2026-000001';
    $payment = $ps->getById($paymentId);
    
    $targetAccountId = $ps->resolvePaymentAccount($payment['Payment_Method'] ?? 'TRANSFER', null);
    
    echo "Resolved Account ID: " . $targetAccountId . "\n";
    
    $transactionData = [
        'Transaction_Date' => now()->format('Y-m-d'),
        'Account_ID' => $targetAccountId,
        'Type' => 'Income',
        'Category' => 'Payment Receipt',
        'Amount' => (float) ($payment['Amount_Paid'] ?? 0),
        'Reference_Type' => 'Payment',
        'Reference_ID' => $paymentId,
        'Description' => "Pembayaran Verifikasi Kuitansi #{$paymentId} untuk Invoice #" . ($payment['Invoice_ID'] ?? ''),
    ];
    
    echo "Creating transaction...\n";
    $ts->create($transactionData);
    echo "Transaction created successfully!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
