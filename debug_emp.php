<?php
$employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
$employees = collect($employeeRepo->fetchAll());
echo "All Employees: \n";
foreach ($employees as $e) {
    echo $e['Employee_ID'] . ' -> ' . ($e['User_ID'] ?? 'no user') . ' -> ' . ($e['Full_Name'] ?? 'no name') . "\n";
}
