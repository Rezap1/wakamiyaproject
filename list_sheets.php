<?php
require __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName('Wakamiya Management System');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$client->setAuthConfig(__DIR__ . '/storage/app/google-credentials.json');

$service = new \Google_Service_Sheets($client);
$spreadsheetId = '1i-U2pW656O2aWq0B7k7p6k8aJ-j4h4b4U2J2v5Y4U2k'; // Let's check config/services.php for spreadsheet_id

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$spreadsheetId = config('services.google.spreadsheet_id');

try {
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    echo "Available Sheets:\n";
    foreach ($spreadsheet->getSheets() as $sheet) {
        echo "- " . $sheet->getProperties()->getTitle() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
