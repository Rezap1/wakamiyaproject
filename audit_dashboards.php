<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Fake login
$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$userRaw = collect($userRepo->fetchAll())->where('Role_ID', 'MASTER')->first() ?? collect($userRepo->fetchAll())->first();
if ($userRaw) {
    $userRaw['id'] = $userRaw['User_ID'] ?? 'USR001';
    \Illuminate\Support\Facades\Auth::login(new \Illuminate\Auth\GenericUser($userRaw));
}

echo "=== DASHBOARD DATA AUDIT ===\n";

try {
    echo "\n1. HR DASHBOARD\n";
    $hrService = app(\App\Services\Dashboard\HrDashboardService::class);
    $hrData = $hrService->getDashboardData();
    print_r($hrData['kpi']);

    echo "\n2. FINANCE DASHBOARD\n";
    $finService = app(\App\Services\Dashboard\FinanceDashboardService::class);
    $finData = $finService->getDashboardData();
    print_r($finData['kpi']);

    echo "\n3. ACADEMIC DASHBOARD\n";
    $acadService = app(\App\Services\Dashboard\AcademicDashboardService::class);
    $acadData = $acadService->getDashboardData();
    print_r($acadData['kpi']);

    echo "\n4. MARKETING DASHBOARD\n";
    // Marketing is served by MarketingDashboardController. Keep this audit helper aligned
    // with that controller's repository-backed KPI source.
    $companyRepo = app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class);
    $documentRepo = app(\App\Interfaces\GoogleSheets\DocumentRepositoryInterface::class);
    $countActive = static fn ($repo) => collect($repo->fetchAll())
        ->where('Is_Active', '!=', 'FALSE')
        ->count();
    print_r([
        'companies' => $countActive($companyRepo),
        'documents' => $countActive($documentRepo),
    ]);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
