<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$firstUserRaw = collect($userRepo->fetchAll())->first();
if ($firstUserRaw) {
    // Inject 'id' for GenericUser compatibility
    $firstUserRaw['id'] = $firstUserRaw['User_ID'] ?? 'USR001';
    $genericUser = new \Illuminate\Auth\GenericUser($firstUserRaw);
    \Illuminate\Support\Facades\Auth::login($genericUser);
} else {
    // Mock the user for Auth
    $genericUser = new \Illuminate\Auth\GenericUser(['id' => 'SYS001', 'User_ID' => 'SYS001', 'Username' => 'SYSTEM']);
    \Illuminate\Support\Facades\Auth::login($genericUser);
}

$repo = app(\App\Interfaces\GoogleSheets\AccountRepositoryInterface::class);
$service = app(\App\Services\Finance\AccountService::class);

echo "==============================\n";
echo "MASTER_ACCOUNT BEFORE\n";
echo "==============================\n";
try {
    $all = collect($repo->fetchAll());
    echo "Jumlah Row Saat Ini: " . $all->count() . "\n";
    if ($all->count() > 0) {
        echo "Contoh Data Pertama:\n";
        print_r($all->first());
    } else {
        echo "Data benar-benar kosong.\n";
    }

    if ($all->count() === 0) {
        echo "\nMelakukan Provisioning Akun Kas Utama...\n";
        $data = [
            'Account_Code' => '101',
            'Account_Name' => 'Kas Utama',
            'Account_Category' => 'ASSET',
            'Parent_Account_ID' => '',
            'Description' => 'Kas utama untuk transaksi operasional harian',
        ];
        
        $created = $service->create($data);
        echo "\nACCOUNT CREATED:\n";
        print_r($created);
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
