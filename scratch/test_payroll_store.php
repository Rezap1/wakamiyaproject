<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\HR\PayrollCalculationEngine;

$engine = app(PayrollCalculationEngine::class);

echo "--- TEST 1: Auto Calculation from HR Settings (Net_Salary = 0) ---\n";
$calc1 = $engine->calculate('EMP000005', '2026-08', ['Net_Salary' => 0]);
echo "Employee: " . $calc1['employee_name'] . "\n";
echo "Base Salary: Rp " . number_format($calc1['base_salary'], 0, ',', '.') . "\n";
echo "Total Deduction: Rp " . number_format($calc1['total_deduction'], 0, ',', '.') . "\n";
echo "Net Salary: Rp " . number_format($calc1['net_salary'], 0, ',', '.') . "\n\n";

echo "--- TEST 2: Overridden Net Salary (Net_Salary = 3500000) ---\n";
$calc2 = $engine->calculate('EMP000005', '2026-08', ['Net_Salary' => 3500000]);
echo "Employee: " . $calc2['employee_name'] . "\n";
echo "Base Salary: Rp " . number_format($calc2['base_salary'], 0, ',', '.') . "\n";
echo "Total Deduction: Rp " . number_format($calc2['total_deduction'], 0, ',', '.') . "\n";
echo "Net Salary: Rp " . number_format($calc2['net_salary'], 0, ',', '.') . "\n";
