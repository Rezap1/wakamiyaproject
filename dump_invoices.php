<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ir = app(\App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class);
print_r($ir->fetchAll()->toArray());
