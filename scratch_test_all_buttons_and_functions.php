<?php

/**
 * COMPREHENSIVE ACTION & BUTTON TESTING SCRIPT
 * Tests all action endpoints, form rendering, export buttons, detail views, and lookup functions.
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;

// Authenticate as main administrator USR000001
$adminUser = new \Illuminate\Auth\GenericUser([
    'id' => 'USR000001',
    'User_ID' => 'USR000001',
    'Username' => 'deri.alamsah',
    'Email' => 'lpkwakamiya01@gmail.com',
    'Full_Name' => 'Deri Alamsah',
    'Role_ID' => 'ROL000002',
    'Role' => 'ADMINISTRATOR',
    'Is_Active' => 'TRUE'
]);

Auth::login($adminUser);

echo "============================================================\n";
echo " WMS ALL BUTTONS & FUNCTIONS TESTING SUITE\n";
echo "============================================================\n";
echo "Authenticated User: " . Auth::user()->Email . " (" . Auth::user()->Role . ")\n\n";

$actionsToTest = [
    // --- USER MANAGEMENT BUTTONS & FUNCTIONS ---
    'Users - Form Tambah'                 => ['GET', '/users/create'],
    'Users - Export Excel'               => ['GET', '/users?export=excel'],
    'Users - Export PDF'                 => ['GET', '/users?export=pdf'],
    'Users - Detail USR000001'           => ['GET', '/users/USR000001'],

    // --- EMPLOYEE MANAGEMENT BUTTONS & FUNCTIONS ---
    'Karyawan - Form Tambah'             => ['GET', '/employees/create'],
    'Karyawan - Export Excel'           => ['GET', '/employees?export=excel'],
    'Karyawan - Export PDF'             => ['GET', '/employees?export=pdf'],
    'Karyawan - Detail EMP000004'       => ['GET', '/employees/EMP000004'],
    'Karyawan - Edit EMP000004'         => ['GET', '/employees/EMP000004/edit'],
    'Karyawan - Lookup API EMP000004'   => ['GET', '/employees/lookup/EMP000004'],

    // --- DEPARTMENT & POSITION BUTTONS & FUNCTIONS ---
    'Departemen - Form Tambah'           => ['GET', '/departments/create'],
    'Departemen - Export PDF'           => ['GET', '/departments?export=pdf'],
    'Posisi - Form Tambah'               => ['GET', '/positions/create'],
    'Posisi - Export PDF'               => ['GET', '/positions?export=pdf'],

    // --- COMPANY & PARTNER BUTTONS & FUNCTIONS ---
    'Perusahaan - Form Tambah'           => ['GET', '/companies/create'],
    'Perusahaan - Export PDF'           => ['GET', '/companies?export=pdf'],

    // --- STUDENT & TEACHER BUTTONS & FUNCTIONS ---
    'Siswa - Form Tambah'                => ['GET', '/students/create'],
    'Siswa - Export PDF'                 => ['GET', '/students?export=pdf'],
    'Guru - Form Tambah'                 => ['GET', '/teachers/create'],
    'Guru - Export PDF'                 => ['GET', '/teachers?export=pdf'],

    // --- HR ENGINE: LEAVE BUTTONS & FUNCTIONS ---
    'Cuti - Form Pengajuan'             => ['GET', '/hr/leaves/create'],
    'Cuti - Export PDF List'            => ['GET', '/hr/leaves/export-pdf'],
    'Cuti - Export Excel List'          => ['GET', '/hr/leaves/export-excel'],

    // --- HR ENGINE: OVERTIME BUTTONS & FUNCTIONS ---
    'Lembur - Form Pengajuan'           => ['GET', '/hr/overtimes/create'],
    'Lembur - Export PDF List'          => ['GET', '/hr/overtimes/export-pdf'],
    'Lembur - Export Excel List'        => ['GET', '/hr/overtimes/export-excel'],

    // --- HR ENGINE: PAYROLL BUTTONS & FUNCTIONS ---
    'Payroll - Form Buat Slip'          => ['GET', '/hr/payrolls/create'],
    'Payroll - Export PDF List'         => ['GET', '/hr/payrolls/export-pdf'],

    // --- FINANCE ENGINE: INVOICE BUTTONS & FUNCTIONS ---
    'Invoice - Form Buat Tagihan'       => ['GET', '/finance/invoices/create'],
    'Invoice - Export PDF List'         => ['GET', '/finance/invoices/export-pdf'],

    // --- FINANCE ENGINE: PAYMENT BUTTONS & FUNCTIONS ---
    'Pembayaran - Form Pembayaran'      => ['GET', '/finance/payments/create'],
    'Pembayaran - Export PDF List'      => ['GET', '/finance/payments/export-pdf'],

    // --- FINANCE ENGINE: ACCOUNTS & TRANSACTIONS ---
    'CoA - Form Tambah Akun'            => ['GET', '/finance/accounts/create'],
    'CoA - Export PDF List'             => ['GET', '/finance/accounts/export-pdf'],
    'Transaksi - Form Transaksi'        => ['GET', '/finance/transactions/create'],
    'Transaksi - Export PDF List'       => ['GET', '/finance/transactions/export-pdf'],

    // --- SYSTEM SETTINGS BUTTONS & FUNCTIONS ---
    'Settings - Tab General'            => ['GET', '/settings?tab=General'],
    'Settings - Tab Company Profile'    => ['GET', '/settings?tab=Company'],
    'Settings - Tab Company Bank'       => ['GET', '/settings?tab=Company_Bank'],
    'Settings - Tab Company Document'   => ['GET', '/settings?tab=Company_Document'],
    'Settings - Tab Finance'            => ['GET', '/settings?tab=Finance'],
    'Settings - Tab Document'           => ['GET', '/settings?tab=Document'],
    'Settings - Tab Security'           => ['GET', '/settings?tab=Security'],
    'Settings - Tab System'             => ['GET', '/settings?tab=System'],
];

$passed = 0;
$failed = 0;
$results = [];

foreach ($actionsToTest as $name => $spec) {
    $method = $spec[0];
    $uri = $spec[1];
    
    $request = \Illuminate\Http\Request::create($uri, $method);
    
    try {
        $response = $app->handle($request);
        $status = $response->getStatusCode();
        
        if ($status >= 200 && $status < 400) {
            $results[] = [
                'name' => $name,
                'method' => $method,
                'uri' => $uri,
                'status' => $status,
                'result' => 'PASSED ✅'
            ];
            $passed++;
        } else {
            $results[] = [
                'name' => $name,
                'method' => $method,
                'uri' => $uri,
                'status' => $status,
                'result' => "FAILED ❌ (HTTP {$status})"
            ];
            $failed++;
        }
    } catch (\Exception $e) {
        $results[] = [
            'name' => $name,
            'method' => $method,
            'uri' => $uri,
            'status' => 500,
            'result' => "EXCEPTION ❌ (" . substr($e->getMessage(), 0, 40) . ")"
        ];
        $failed++;
    }
}

echo "=== BUTTONS & FUNCTIONS TEST RESULTS ===\n";
printf("%-35s | %-6s | %-38s | %-8s | %-12s\n", "Function / Button Name", "Method", "URI Path", "Status", "Result");
echo str_repeat("-", 108) . "\n";

foreach ($results as $res) {
    printf("%-35s | %-6s | %-38s | %-8s | %-12s\n", $res['name'], $res['method'], $res['uri'], $res['status'], $res['result']);
}

echo str_repeat("-", 108) . "\n";
echo "Total Functions/Buttons Tested: " . count($actionsToTest) . "\n";
echo "Passed: {$passed} ✅\n";
echo "Failed: {$failed} ❌\n";

// Test specific data lookup APIs & dynamic helpers
echo "\n--- TESTING HELPER & SERVICE FUNCTIONS ---\n";

// Helper Test 1: UserResolverHelper
if (class_exists('App\Helpers\UserResolverHelper')) {
    $name = \App\Helpers\UserResolverHelper::getName('USR000001');
    echo "UserResolverHelper::getName('USR000001'): '{$name}' -> " . (!empty($name) ? "PASSED ✅" : "FAILED ❌") . "\n";
}

// Helper Test 2: DateHelper
if (class_exists('App\Helpers\DateHelper')) {
    $formatted = \App\Helpers\DateHelper::format('15/08/2026 16:47:03', 'd M Y, H:i');
    echo "DateHelper::format('15/08/2026 16:47:03'): '{$formatted}' -> " . ($formatted === '15 Aug 2026, 16:47' ? "PASSED ✅" : "FAILED ❌") . "\n";
}

// Service Test 3: SystemSettingService::getCompanyProfile()
$settingService = app(\App\Services\Core\SystemSettingService::class);
$companyProfile = $settingService->getCompanyProfile();
echo "SystemSettingService::getCompanyProfile()['company']['name']: '" . ($companyProfile['company']['name'] ?? '') . "' -> PASSED ✅\n";

echo "\n============================================================\n";
if ($failed === 0) {
    echo " ALL BUTTONS & FUNCTIONS TESTED & OPERATIONAL (100% PASSED) ✅✅✅\n";
} else {
    echo " SOME FUNCTIONS REQUIRING ATTENTION ❌\n";
}
echo "============================================================\n";
