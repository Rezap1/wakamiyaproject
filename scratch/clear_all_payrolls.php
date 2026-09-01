<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use Illuminate\Support\Facades\Cache;

$payrollRepo = app(PayrollRepositoryInterface::class);
$allPayrolls = $payrollRepo->getAll();

echo "Initial Payrolls Count: " . count($allPayrolls) . "\n";

$deletedCount = 0;
foreach ($allPayrolls as $p) {
    $id = $p['Payroll_ID'] ?? null;
    if ($id) {
        $res = $payrollRepo->delete($id);
        echo "Deleting {$id}: " . ($res ? 'SUCCESS' : 'FAILED') . "\n";
        $deletedCount++;
    }
}

// Clear all payroll caches
Cache::forget('payroll_sheet_all');
Cache::flush(); // Flush cache to ensure no concurrency lock or counter remains

$remainingPayrolls = $payrollRepo->getAll();
echo "\nDeleted: {$deletedCount} records.\n";
echo "Remaining Payrolls Count: " . count($remainingPayrolls) . "\n";
