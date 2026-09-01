<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Finance\InvoiceController;
use Illuminate\Http\Request;

echo "========================================\n";
echo "INVOICE CONTROLLER DIAGNOSTIC VERIFICATION\n";
echo "========================================\n\n";

try {
    $controller = app(InvoiceController::class);
    
    // 1. Test index
    $reqIndex = Request::create('/finance/invoices', 'GET');
    $resIndex = $controller->index($reqIndex);
    echo "[✓] index() method executed cleanly -> View: " . $resIndex->name() . "\n";

    // 2. Test create
    $resCreate = $controller->create();
    echo "[✓] create() method executed cleanly -> View: " . $resCreate->name() . "\n";

    echo "\n========================================\n";
    echo "NO ERRORS FOUND IN INVOICE CONTROLLER! ✅\n";
    echo "========================================\n";
} catch (\Throwable $e) {
    echo "ERROR DETECTED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
