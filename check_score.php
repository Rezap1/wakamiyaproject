<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$repo = new \App\Repositories\GoogleSheets\ScoreRepository();

try {
    $data = $repo->fetchAll();
    if ($data->count() > 0) {
        echo "Headers of MASTER_SCORE (keys of first row):\n";
        print_r(array_keys($data->first()));
        
        echo "\nData preview:\n";
        print_r($data->first());
    } else {
        echo "No data found in MASTER_SCORE.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
