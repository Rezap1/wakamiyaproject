<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
$student = collect($studentRepo->fetchAll())->first();
$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$user = $userRepo->findById($student['User_ID']);

$model = new \App\Models\User();
$model->User_ID = $user['User_ID'];
$model->Role = 'STUDENT';
auth()->login($model);

$controller = app(\App\Http\Controllers\Finance\StudentBillingController::class);
try {
    $inv = app(\App\Services\Finance\InvoiceService::class)->getById('INV-STU-2026-000003');
    if (!$inv) {
        die("No invoice for student.\n");
    }
    echo "Testing invoice: {$inv['Invoice_ID']}\n";
    $response = $controller->downloadInvoicePdf($inv['Invoice_ID']);
    
    if ($response instanceof \Illuminate\Http\RedirectResponse) {
        echo "REDIRECT WITH SESSION: \n";
        print_r(session()->all());
    } else {
        echo "SUCCESS PDF GENERATED\n";
    }
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
