<?php

use App\Repositories\GoogleSheets\AttendanceRepository;
use App\Repositories\GoogleSheets\UserRepository;
use App\Services\Finance\PaymentService;
use App\Services\Quiz\QuizService;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $mode, $token, $slot, $gateDirectory] = $argv;

config()->set('database.default', 'mysql');
config()->set('database.connections.mysql.database', 'wms_mysql_test');
config()->set('wms_runtime.connection', 'mysql');
config()->set('wms_runtime.database', 'wms_mysql_test');
DB::purge('mysql');

file_put_contents($gateDirectory.'/ready-'.$slot, 'ready');
$deadline = microtime(true) + 15;
while (! is_file($gateDirectory.'/go')) {
    if (microtime(true) >= $deadline) {
        fwrite(STDOUT, json_encode(['ok' => false, 'error' => 'barrier timeout']).PHP_EOL);
        exit(2);
    }
    usleep(10_000);
}

try {
    $value = match ($mode) {
        'quiz_submit' => app(QuizService::class)->submit(
            "QAT-{$token}",
            [
                'Student_ID' => "STU-{$token}",
                'Class_ID' => "CLS-{$token}",
                'Full_Name' => 'M2 Concurrent Student',
            ],
            ["QQ-{$token}" => 'A']
        ),
        'attendance' => app(AttendanceRepository::class)->create([
            'Attendance_ID' => "ATT-{$token}-{$slot}",
            'Student_ID' => "STU-{$token}",
            'Class_ID' => "CLS-{$token}",
            'Attendance_Date' => '2026-10-02',
            'Attendance_Type' => 'CLASS_QR',
            'Status' => 'PRESENT',
        ]),
        'id' => app(UserRepository::class)->generateNewId('M2CON'.$token, 6),
        'payment' => (function () use ($token, $slot) {
            Auth::setUser(new GenericUser([
                'id' => 'USR-M2-FINANCE',
                'User_ID' => 'USR-M2-FINANCE',
                'Role' => 'FINANCE',
                'Role_ID' => 'ROLE-M2-FINANCE',
            ]));

            return app(PaymentService::class)->verifyPayment(
                "PAY-{$token}-{$slot}",
                'USR-M2-FINANCE',
                'Verified',
                '',
                "ACC-{$token}"
            );
        })(),
        default => throw new RuntimeException("Unknown worker mode {$mode}"),
    };

    fwrite(STDOUT, json_encode(['ok' => true, 'value' => $value], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'error' => get_class($exception),
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
