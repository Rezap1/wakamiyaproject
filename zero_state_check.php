<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ZERO-STATE INVENTORY (READ-ONLY) ===\n\n";

// Users
$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$users = $userRepo->fetchAll();
echo "Users: " . count($users) . "\n";
foreach ($users as $u) {
    echo "  - {$u['User_ID']} | Role: " . ($u['Role_Name'] ?? $u['Role_ID'] ?? 'N/A') . " | Active: " . ($u['Is_Active'] ?? '?') . " | Email: " . ($u['Email'] ?? '?') . "\n";
}

// Business data repositories
$repos = [
    'StudentRepositoryInterface' => 'Students',
    'TeacherRepositoryInterface' => 'Teachers',
    'EmployeeRepositoryInterface' => 'Employees',
    'CompanyRepositoryInterface' => 'Companies',
    'ClassRepositoryInterface' => 'Classes',
    'BatchRepositoryInterface' => 'Batches',
    'SubjectRepositoryInterface' => 'Subjects',
    'ProgramRepositoryInterface' => 'Programs',
    'AcademicYearRepositoryInterface' => 'AcademicYears',
    'ClassEnrollmentRepositoryInterface' => 'ClassEnrollments',
    'ScheduleRepositoryInterface' => 'Schedules',
    'AttendanceRepositoryInterface' => 'Attendances',
    'AttendanceRequestRepositoryInterface' => 'AttendanceRequests',
    'ScoreRepositoryInterface' => 'Scores',
    'AssignmentRepositoryInterface' => 'Assignments',
    'InvoiceRepositoryInterface' => 'Invoices',
    'PaymentRepositoryInterface' => 'Payments',
    'TransactionRepositoryInterface' => 'Transactions',
    'PayrollRepositoryInterface' => 'Payrolls',
    'DocumentRepositoryInterface' => 'Documents',
    'NotificationRepositoryInterface' => 'Notifications',
    'ApprovalRepositoryInterface' => 'Approvals',
    'ApprovalHistoryRepositoryInterface' => 'ApprovalHistory',
    'AuditLogRepositoryInterface' => 'AuditLogs',
    'PermanentQrRepositoryInterface' => 'PermanentQR',
];

echo "\n=== RUNTIME DATA ===\n";
foreach ($repos as $interface => $label) {
    try {
        $repo = app("App\\Interfaces\\GoogleSheets\\{$interface}");
        $count = count($repo->fetchAll());
        $flag = $count > 0 ? " *** NON-ZERO ***" : "";
        echo "  {$label}: {$count}{$flag}\n";
    } catch (\Exception $e) {
        echo "  {$label}: ERROR - " . $e->getMessage() . "\n";
    }
}

// Structural data (should still be intact)
echo "\n=== STRUCTURAL DATA (Should be intact) ===\n";
$structRepos = [
    'RoleRepositoryInterface' => 'Roles',
    'DepartmentRepositoryInterface' => 'Departments',
    'PositionRepositoryInterface' => 'Positions',
    'ModuleRepositoryInterface' => 'Modules',
];
foreach ($structRepos as $interface => $label) {
    try {
        $repo = app("App\\Interfaces\\GoogleSheets\\{$interface}");
        $count = count($repo->fetchAll());
        echo "  {$label}: {$count}\n";
    } catch (\Exception $e) {
        echo "  {$label}: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
