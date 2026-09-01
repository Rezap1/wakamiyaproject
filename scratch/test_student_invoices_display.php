<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Helpers\UserResolverHelper;
use App\Services\Finance\InvoiceService;

$invoiceService = app(InvoiceService::class);
$invoices = $invoiceService->getAll();

echo "Testing Invoice Student Details Resolution:\n";
foreach ($invoices as $inv) {
    $stdId = $inv['Student_ID'] ?? '';
    $stdDetail = UserResolverHelper::getStudentDetail($stdId);
    
    echo "Invoice ID: " . ($inv['Invoice_ID'] ?? '-') . "\n";
    echo "  Student ID  : {$stdId}\n";
    echo "  Student Name: {$stdDetail['name']}\n";
    echo "  Class Name  : {$stdDetail['class_name']}\n";
    echo "  Batch Name  : {$stdDetail['batch_name']}\n";
    echo "  Formatted   : {$stdDetail['formatted']}\n\n";
}
