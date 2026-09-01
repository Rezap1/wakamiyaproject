<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\Finance\AccountService::class);
$default = $service->getDefaultTransactionAccount();

echo "ACCOUNT RESOLUTION TEST:\n";
if ($default) {
    echo "PASS\n";
    print_r($default);
} else {
    echo "FAIL\n";
}
