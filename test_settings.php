<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('Role', 'ACADEMIC')->first();
if ($user) {
    Auth::login($user);
} else {
    // maybe try to find any user
    Auth::loginUsingId(1);
}

$request = Illuminate\Http\Request::create('/academic/settings', 'GET');
$response = $kernel->handle($request);
echo 'Status: ' . $response->getStatusCode() . "\n";
echo substr($response->getContent(), 0, 500);
