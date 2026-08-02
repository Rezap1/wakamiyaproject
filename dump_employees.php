<?php
$employees = app(App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll();
foreach($employees as $emp) {
    echo "ID: " . $emp['Employee_ID'] . " | UserID: " . ($emp['User_ID'] ?? 'null') . " | Name: " . $emp['Full_Name'] . " | Phone: " . ($emp['Phone_Number'] ?? 'null') . "\n";
}
