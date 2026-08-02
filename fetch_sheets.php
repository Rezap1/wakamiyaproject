<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$client = new \Google_Client();
$client->setAuthConfig(storage_path('app/google-credentials.json'));
$client->addScope(\Google_Service_Sheets::SPREADSHEETS);
$service = new \Google_Service_Sheets($client);
$spreadsheet = $service->spreadsheets->get(config('services.google.spreadsheet_id'));
foreach ($spreadsheet->getSheets() as $sheet) {
    echo $sheet->getProperties()->getTitle() . PHP_EOL;
}
