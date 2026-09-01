<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $ps = app(\App\Services\Finance\PaymentService::class);
    $ts = app(\App\Services\Finance\TransactionService::class);
    $is = app(\App\Services\Finance\InvoiceService::class);
    $fs = app(\App\Services\Dashboard\FinanceDashboardService::class);
    
    echo "Payments: " . count($ps->getAll()) . "\n";
    echo "Transactions: " . count($ts->getAll()) . "\n";
    echo "Invoices: " . count($is->getAll()) . "\n";
    
    $data = $fs->getDashboardData();
    echo "KPI:\n";
    print_r($data['kpi']);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
