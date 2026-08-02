<?php
$file = 'routes/web.php';
$content = file_get_contents($file);

$replacements = [
    "Route::resource('users', UserController::class);" => "Route::get('/users/export-pdf', [UserController::class, 'exportPdf'])->name('users.export.pdf');\n        Route::resource('users', UserController::class);",
    "Route::resource('employees', EmployeeController::class);" => "Route::get('/employees/export-pdf', [EmployeeController::class, 'exportPdf'])->name('employees.export.pdf');\n        Route::resource('employees', EmployeeController::class);",
    "Route::resource('teachers', TeacherController::class);" => "Route::get('/teachers/export-pdf', [TeacherController::class, 'exportPdf'])->name('teachers.export.pdf');\n        Route::resource('teachers', TeacherController::class);",
    "Route::resource('departments', DepartmentController::class);" => "Route::get('/departments/export-pdf', [DepartmentController::class, 'exportPdf'])->name('departments.export.pdf');\n        Route::resource('departments', DepartmentController::class);",
    "Route::resource('positions', PositionController::class);" => "Route::get('/positions/export-pdf', [PositionController::class, 'exportPdf'])->name('positions.export.pdf');\n        Route::resource('positions', PositionController::class);"
];

$modified = false;
foreach ($replacements as $search => $replace) {
    if (strpos($content, $search) !== false && strpos($content, $replace) === false) {
        $content = str_replace($search, $replace, $content);
        $modified = true;
    }
}

if ($modified) {
    file_put_contents($file, $content);
    echo "Routes updated successfully.\n";
} else {
    echo "No changes made (already exists or search string not found).\n";
}
