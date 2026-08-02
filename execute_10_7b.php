<?php
// execute_10_7b.php
// This script automates the Phase 10.7B Execution to inject Multi-Export features

$baseDir = __DIR__;
$webPhpPath = $baseDir . '/routes/web.php';
$webPhpContent = file_get_contents($webPhpPath);

$modules = [
    'users' => ['App\Http\Controllers\Core\UserController', 'Users'],
    'departments' => ['App\Http\Controllers\Core\DepartmentController', 'Departments'],
    'positions' => ['App\Http\Controllers\Core\PositionController', 'Positions'],
    'employees' => ['App\Http\Controllers\Core\EmployeeController', 'Employees'],
    'teachers' => ['App\Http\Controllers\Core\TeacherController', 'Teachers'],
    'programs' => ['App\Http\Controllers\Core\ProgramController', 'Programs'],
    'batches' => ['App\Http\Controllers\Core\BatchController', 'Batches'],
    'classes' => ['App\Http\Controllers\Core\ClassController', 'Classes'],
    'students' => ['App\Http\Controllers\Core\StudentController', 'Students'],
    'companies' => ['App\Http\Controllers\Core\CompanyController', 'Companies'],
    'activity' => ['App\Http\Controllers\Core\ActivityController', 'Activity Log'],
    'audit' => ['App\Http\Controllers\Core\AuditLogController', 'Audit Log'],
    'notifications' => ['App\Http\Controllers\Core\NotificationController', 'Notifications'],
    'documents' => ['App\Http\Controllers\Core\DocumentController', 'Documents'],
    'modules' => ['App\Http\Controllers\Core\ModuleController', 'Modules'],
    'subjects' => ['App\Http\Controllers\Academic\SubjectController', 'Subjects', 'academic/subjects'],
    'schedules' => ['App\Http\Controllers\Academic\ScheduleController', 'Schedules', 'academic/schedules'],
    'attendances' => ['App\Http\Controllers\Academic\AttendanceController', 'Attendances', 'academic/attendances'],
    'assessments' => ['App\Http\Controllers\Academic\AssessmentController', 'Assessments', 'academic/assessments'],
    'scores' => ['App\Http\Controllers\Academic\ScoreController', 'Scores', 'academic/scores'],
    'accounts' => ['App\Http\Controllers\Finance\AccountController', 'Accounts', 'finance/accounts'],
    'transactions' => ['App\Http\Controllers\Finance\TransactionController', 'Transactions', 'finance/transactions'],
    'invoices' => ['App\Http\Controllers\Finance\InvoiceController', 'Invoices', 'finance/invoices'],
    'payments' => ['App\Http\Controllers\Finance\PaymentController', 'Payments', 'finance/payments'],
    'payrolls' => ['App\Http\Controllers\Hr\PayrollController', 'Payroll', 'hr/payroll'],
];

// Process Routes
foreach ($modules as $prefix => $data) {
    $controllerPath = $data[0];
    $className = basename(str_replace('\\', '/', $controllerPath));
    $search = "Route::prefix('$prefix')->name('$prefix.')->group(function () {";
    
    // Some routes are just Route::resource or in different format, skipping complex route parsing here, we will just add it if prefix found
    if (strpos($webPhpContent, $search) !== false && strpos($webPhpContent, "Route::get('/preview-pdf', [$className::class, 'previewPdf'])->name('preview-pdf');") === false) {
        $replacement = $search . "\n        Route::get('/preview-pdf', [$className::class, 'previewPdf'])->name('preview-pdf');\n        Route::get('/export-pdf', [$className::class, 'exportPdf'])->name('export-pdf');\n        Route::get('/export-excel', [$className::class, 'exportExcel'])->name('export-excel');\n        Route::get('/export-csv', [$className::class, 'exportCsv'])->name('export-csv');\n        Route::get('/print', [$className::class, 'print'])->name('print');";
        $webPhpContent = str_replace($search, $replacement, $webPhpContent);
    }
}
file_put_contents($webPhpPath, $webPhpContent);
echo "Updated routes/web.php\n";

// Process Blade Index views
$viewsDir = $baseDir . '/resources/views';
foreach ($modules as $prefix => $data) {
    $viewPath = $data[2] ?? $prefix;
    $indexPath = $viewsDir . '/' . $viewPath . '/index.blade.php';
    if (file_exists($indexPath)) {
        $indexContent = file_get_contents($indexPath);
        
        if (strpos($indexContent, '<x-universal.multi-export') === false) {
            $newActions = "<x-slot:headerActions>\n        <x-universal.multi-export route-prefix=\"$prefix\" />\n    </x-slot:headerActions>";
            
            if (preg_match('/<x-slot:headerActions>(.*?)<\/x-slot:headerActions>/s', $indexContent, $matches)) {
                $indexContent = str_replace($matches[0], $newActions, $indexContent);
            } else if (strpos($indexContent, '<x-slot:toolbar>') !== false) {
                $indexContent = str_replace('<x-slot:toolbar>', $newActions . "\n    <x-slot:toolbar>", $indexContent);
            } else {
                // Just put it before data-table
                $indexContent = str_replace('<x-universal.data-table', $newActions . "\n    <x-universal.data-table", $indexContent);
            }
            file_put_contents($indexPath, $indexContent);
            echo "Updated $indexPath\n";
        }
    }
}

// Process Controllers
foreach ($modules as $prefix => $data) {
    $controllerPath = $baseDir . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $data[0]) . '.php';
    if (file_exists($controllerPath)) {
        $controllerContent = file_get_contents($controllerPath);
        
        // Skip if already Exportable
        if (strpos($controllerContent, 'use \App\Traits\Exportable;') === false && strpos($controllerContent, 'use Exportable;') === false) {
            
            $className = basename(str_replace('\\', '/', $data[0]));
            $pattern = '/class ' . $className . ' extends Controller\s*\{/';
            
            if (preg_match($pattern, $controllerContent, $matches)) {
                $traitStr = "\n    use \App\Traits\Exportable;\n\n    protected function getExportConfig(\Illuminate\Http\Request \$request)\n    {\n        // TODO: Map actual service call and data based on \$request filters\n        \$data = collect([]); \n        \n        return [\n            'moduleName' => strtoupper('{$data[1]}'),\n            'data' => \$data,\n            'pdfView' => 'pdf.generic_table',\n            'headers' => ['ID', 'Name', 'Status'],\n            'mapRow' => function(\$row) {\n                return [\$row['id'] ?? '-', \$row['name'] ?? '-', \$row['status'] ?? '-'];\n            },\n            'isLandscape' => false,\n            'summary' => '<tr><td>Total Records</td><td>: '.\$data->count().'</td></tr>'\n        ];\n    }\n";
                $controllerContent = str_replace($matches[0], $matches[0] . $traitStr, $controllerContent);
                file_put_contents($controllerPath, $controllerContent);
                echo "Injected Exportable into $controllerPath\n";
            }
        }
    }
}

// Create generic PDF table view for rapid scaffolding
$genericPdf = $viewsDir . '/pdf/generic_table.blade.php';
if (!file_exists($genericPdf)) {
    $content = "@extends('pdf.' . (isset(\$isPrintMode) && \$isPrintMode ? 'print_layout' : 'report_layout'))\n@section('content')\n<table class=\"enterprise-table\">\n    <thead>\n        <tr>\n            @foreach(\$headers ?? [] as \$header)\n                <th>{{ \$header }}</th>\n            @endforeach\n        </tr>\n    </thead>\n    <tbody>\n        @forelse(\$records as \$record)\n            <tr>\n                @php \$mapped = isset(\$mapRow) ? \$mapRow(\$record) : []; @endphp\n                @foreach(\$mapped as \$cell)\n                    <td>{{ \$cell }}</td>\n                @endforeach\n            </tr>\n        @empty\n            <tr><td colspan=\"{{ count(\$headers ?? ['']) }}\" style=\"text-align:center;\">No data available</td></tr>\n        @endforelse\n    </tbody>\n</table>\n@endsection";
    file_put_contents($genericPdf, $content);
    echo "Created generic pdf table view\n";
}

echo "Done.\n";

