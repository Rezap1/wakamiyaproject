<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$repo = app(\App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class);
$first = $repo->fetchAll()->first();
print_r(array_keys($first ?? []));
