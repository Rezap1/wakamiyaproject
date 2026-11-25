<?php

use App\Services\PracticeQuiz\PracticeQuizService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $attempt = $app->make(PracticeQuizService::class)->start((string) $argv[1], [
        'Student_ID' => (string) $argv[2], 'Class_ID' => 'MYSQL-CLASS', 'Full_Name' => 'Concurrency Student',
    ]);
    fwrite(STDOUT, json_encode(['ok' => true, 'attempt_id' => $attempt['Attempt_ID']], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
