<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$repo = app(App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface::class);
$ayService = app(App\Services\Core\AcademicYearService::class);
$ayService->create([
    'Year_Name' => '2026/2027',
    'Term' => 'Ganjil',
    'Is_Active' => 'TRUE',
    'Description' => 'Tahun Ajaran 2026/2027 Semester Ganjil'
]);
echo "Created Academic Year\n";
