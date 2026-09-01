<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$repo = app(\App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class);

try {
    $data = $repo->fetchAll();
    if ($data->count() > 0) {
        echo "Headers of MASTER_SCORE (keys of first row):\n";
        print_r(array_keys($data->first()));
    } else {
        echo "No data found.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
