<?php
$file = 'routes/web.php';
$content = file_get_contents($file);

if (strpos($content, 'PayrollController') === false) {
    // We need to add the uses and the routes.
    $searchUse = "use App\Http\Controllers\Core\HrDashboardController;";
    $replaceUse = "use App\Http\Controllers\Core\HrDashboardController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\PayrollDocumentController;";

    $content = str_replace($searchUse, $replaceUse, $content);

    // Add HR routes
    $searchHr = "Route::get('/dashboard/hr', [HrDashboardController::class, 'index'])->name('dashboard.hr');";
    $replaceHr = "Route::get('/dashboard/hr', [HrDashboardController::class, 'index'])->name('dashboard.hr');
        Route::resource('payrolls', PayrollController::class);
        Route::get('/payrolls/{id}/slip', [PayrollDocumentController::class, 'showSlip'])->name('payrolls.slip');";
    
    $content = str_replace($searchHr, $replaceHr, $content);

    file_put_contents($file, $content);
    echo "Routes added.\n";
} else {
    echo "Routes already exist.\n";
}
?>
