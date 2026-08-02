<?php
$file = 'routes/web.php';
$content = file_get_contents($file);

if (strpos($content, 'InvoiceController') === false) {
    // We need to add the uses and the routes.
    $searchUse = "use App\Http\Controllers\Core\FinanceDashboardController;";
    $replaceUse = "use App\Http\Controllers\Core\FinanceDashboardController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\StudentBillingController;";

    $content = str_replace($searchUse, $replaceUse, $content);

    // Add Finance routes
    $searchFinance = "Route::get('/dashboard/finance', [FinanceDashboardController::class, 'index'])->name('dashboard.finance');";
    $replaceFinance = "Route::get('/dashboard/finance', [FinanceDashboardController::class, 'index'])->name('dashboard.finance');
        Route::resource('invoices', InvoiceController::class);
        Route::resource('payments', PaymentController::class)->only(['index', 'show', 'update']);
        Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');";
    
    $content = str_replace($searchFinance, $replaceFinance, $content);

    // Add Student routes
    $searchStudent = "Route::get('/dashboard/student', [StudentDashboardController::class, 'index'])->name('dashboard.student');";
    $replaceStudent = "Route::get('/dashboard/student', [StudentDashboardController::class, 'index'])->name('dashboard.student');
        Route::get('/student/billing', [StudentBillingController::class, 'index'])->name('student.billing.index');
        Route::get('/student/billing/{id}', [StudentBillingController::class, 'show'])->name('student.billing.show');
        Route::post('/student/billing/{id}/pay', [StudentBillingController::class, 'pay'])->name('student.billing.pay');";

    $content = str_replace($searchStudent, $replaceStudent, $content);

    file_put_contents($file, $content);
    echo "Routes added.\n";
} else {
    echo "Routes already exist.\n";
}
?>
