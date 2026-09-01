<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$app = app();
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$repo = app(\App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class);
print_r(collect($repo->fetchAll())->firstWhere('Invoice_ID', 'INV-STU-2026-000003'));
