<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (! in_array('--execute', $argv, true)) {
    echo "Safety guard active. Re-run with --execute to perform a real student QR scan.\n";
    exit(0);
}

$user = App\Models\User::whereHas('role', function ($query) {
    $query->where('Role_Name', 'like', '%STUDENT%');
})->firstOrFail();

auth()->login($user);

$service = app(App\Services\Academic\StudentQRAttendanceService::class);
$tokenData = $service->getDynamicToken();
$token = $tokenData['token'] ?? null;

$request = Illuminate\Http\Request::create('/attendance/student/scan', 'POST', [
    'token' => $token,
    'latitude' => -6.812391,
    'longitude' => 107.194458,
    'device_info' => 'CLI Test',
]);

$controller = app(App\Http\Controllers\Academic\StudentQRAttendanceController::class);

try {
    $response = $controller->scan($request);
    echo 'Response Status: ' . $response->getStatusCode() . "\n";
    echo 'Response Body: ' . $response->getContent() . "\n";
} catch (Throwable $exception) {
    echo 'Exception: ' . $exception->getMessage() . "\n";

    if (method_exists($exception, 'errors')) {
        print_r($exception->errors());
    }
}
