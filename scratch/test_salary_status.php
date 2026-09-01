<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Core\DashboardHelperService;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;

$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$users = $userRepo->fetchAll();

echo "Testing getSalaryStatus for all users:\n";
foreach ($users as $u) {
    $userId = $u['User_ID'] ?? '';
    $name = $u['Full_Name'] ?? '';
    $status = DashboardHelperService::getSalaryStatus($userId);
    echo "User: {$name} ({$userId}) -> Salary Status: {$status}\n";
}

$payrollRepo = app(PayrollRepositoryInterface::class);
echo "\nPayrolls in MASTER_PAYROLL count: " . count($payrollRepo->getAll()) . "\n";
foreach ($payrollRepo->getAll() as $p) {
    echo "ID: " . ($p['Payroll_ID'] ?? '') . " | EmpID: " . ($p['Employee_ID'] ?? '') . " | Status: " . ($p['Status'] ?? '') . " | Period: " . ($p['Payroll_Period'] ?? '') . "\n";
}
