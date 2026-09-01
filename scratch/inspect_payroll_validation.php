<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Core\EmployeeService;
use App\Http\Requests\StorePayrollRequest;
use Illuminate\Support\Facades\Validator;

$employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
$employees = $employeeRepo->fetchAll();

echo "Employees count: " . count($employees) . "\n";
foreach (collect($employees)->take(5) as $emp) {
    echo "ID: " . ($emp['Employee_ID'] ?? 'NULL') . " | Name: " . ($emp['Full_Name'] ?? 'NULL') . " | Status: " . ($emp['Is_Active'] ?? 'NULL') . "\n";
}

// Check validator
$data = [
    'Employee_ID' => 'EMP-2026-002',
    'Payroll_Period' => '2026-08',
    'Net_Salary' => '0'
];

$request = new StorePayrollRequest();
$validator = Validator::make($data, $request->rules());

if ($validator->fails()) {
    echo "\nVALIDATION FAILED:\n";
    print_r($validator->errors()->all());
} else {
    echo "\nVALIDATION PASSED!\n";
}

// Test processPayroll
try {
    $service = app(\App\Services\HR\PayrollService::class);
    $result = $service->processPayroll($data);
    echo "\nPROCESS PAYROLL SUCCESS:\n";
    print_r($result);
} catch (\Exception $e) {
    echo "\nPROCESS PAYROLL EXCEPTION: " . $e->getMessage() . "\n";
}
