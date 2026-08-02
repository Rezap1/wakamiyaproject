<?php

$webPhpPath = __DIR__ . '/routes/web.php';
$content = file_get_contents($webPhpPath);

// Remove the finance and hr dummy routes
$content = preg_replace("/\s*'accounts'.*?,/s", "", $content);
$content = preg_replace("/\s*'transactions'.*?,/s", "", $content);
$content = preg_replace("/\s*'invoices'.*?,/s", "", $content);
$content = preg_replace("/\s*'payments'.*?,/s", "", $content);
$content = preg_replace("/\s*'reports\.finance'.*?,/s", "", $content);
$content = preg_replace("/\s*'payrolls'.*?,/s", "", $content);

$financeRoutes = <<<EOT

    // Finance - Accounts
    Route::prefix('finance/accounts')->name('accounts.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\AccountController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\AccountController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\AccountController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\AccountController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\AccountController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\AccountController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\AccountController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Finance\AccountController::class, 'store'])->name('store');
        Route::get('/{id}', [\App\Http\Controllers\Finance\AccountController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\AccountController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\AccountController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\AccountController::class, 'destroy'])->name('destroy');
    });

    // Finance - Transactions
    Route::prefix('finance/transactions')->name('transactions.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\TransactionController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\TransactionController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\TransactionController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\TransactionController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\TransactionController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\TransactionController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\TransactionController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Finance\TransactionController::class, 'store'])->name('store');
        Route::get('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\TransactionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'destroy'])->name('destroy');
    });

    // Finance - Invoices
    Route::prefix('finance/invoices')->name('invoices.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\InvoiceController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\InvoiceController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\InvoiceController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\InvoiceController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\InvoiceController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\InvoiceController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Finance\InvoiceController::class, 'store'])->name('store');
        Route::post('/{id}/publish', [\App\Http\Controllers\Finance\InvoiceController::class, 'publish'])->name('publish');
        Route::get('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\InvoiceController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'destroy'])->name('destroy');
    });

    // Finance - Payments
    Route::prefix('finance/payments')->name('payments.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\PaymentController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\PaymentController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\PaymentController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\PaymentController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\PaymentController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\PaymentController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\PaymentController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Finance\PaymentController::class, 'store'])->name('store');
        Route::post('/{id}/verify', [\App\Http\Controllers\Finance\PaymentController::class, 'verify'])->name('verify');
        Route::get('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\PaymentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'destroy'])->name('destroy');
    });

    // Finance - Reports
    Route::prefix('finance/reports')->name('reports.finance.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\ReportController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\ReportController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\ReportController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\ReportController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\ReportController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\ReportController::class, 'index'])->name('index');
        Route::get('/cash-flow', [\App\Http\Controllers\Finance\ReportController::class, 'cash_flow'])->name('cash_flow');
        Route::get('/outstanding', [\App\Http\Controllers\Finance\ReportController::class, 'outstanding'])->name('outstanding');
    });

    // HR - Payrolls
    Route::prefix('hr/payrolls')->name('payrolls.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Hr\PayrollController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Hr\PayrollController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Hr\PayrollController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Hr\PayrollController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Hr\PayrollController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Hr\PayrollController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Hr\PayrollController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Hr\PayrollController::class, 'store'])->name('store');
        Route::post('/{id}/pay', [\App\Http\Controllers\Hr\PayrollController::class, 'pay'])->name('pay');
        Route::get('/{id}/slip', [\App\Http\Controllers\Hr\PayrollController::class, 'slip'])->name('slip');
        Route::get('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Hr\PayrollController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'destroy'])->name('destroy');
    });

EOT;

$content = str_replace('// Permission Management', $financeRoutes . "\n    // Permission Management", $content);

file_put_contents($webPhpPath, $content);
echo "Added Finance and Payroll routes!\n";

// Now inject the UI into the index views
$viewsDir = __DIR__ . '/resources/views';
$modules = [
    'accounts' => 'finance/accounts',
    'transactions' => 'finance/transactions',
    'invoices' => 'finance/invoices',
    'payments' => 'finance/payments',
    'reports.finance' => 'finance/reports',
    'payrolls' => 'hr/payroll',
];

foreach ($modules as $prefix => $viewPath) {
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
                $indexContent = str_replace('<x-universal.data-table', $newActions . "\n    <x-universal.data-table", $indexContent);
            }
            file_put_contents($indexPath, $indexContent);
            echo "Updated $indexPath\n";
        }
    }
}
