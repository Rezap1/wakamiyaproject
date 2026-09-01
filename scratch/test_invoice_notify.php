<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;

$hasRoute = Route::has('invoices.notify');
echo "Route 'invoices.notify' registered: " . ($hasRoute ? 'YES ✅' : 'NO ❌') . "\n";

if ($hasRoute) {
    $route = Route::getRoutes()->getByName('invoices.notify');
    echo "URI: " . $route->uri() . "\n";
    echo "Action: " . $route->getActionName() . "\n";
}
