<?php
$employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
$payrollRepo = app(\App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class);
$employees = collect($employeeRepo->fetchAll());
$payrolls = collect($payrollRepo->getAll());
echo 'Employees: ' . $employees->count() . PHP_EOL;
echo 'Payrolls: ' . $payrolls->count() . PHP_EOL;
echo 'Paid Payrolls: ' . $payrolls->where('Status', 'Paid')->count() . PHP_EOL;
$latest = $payrolls->where('Status', 'Paid')->first();
if ($latest) {
    echo 'Latest Paid Payroll: ' . json_encode($latest) . PHP_EOL;
}
