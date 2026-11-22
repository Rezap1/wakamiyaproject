<?php

use App\Services\Quiz\QuizCleanupService;
use App\Services\Quiz\QuizMediaIdempotencyService;
use App\Services\Quiz\QuizService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\MySqlTestGuard;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $mode, $token, $slot, $gateDirectory, $payloadPath] = $argv;
$payload = json_decode((string) file_get_contents($payloadPath), true, 512, JSON_THROW_ON_ERROR);
config()->set('database.default', 'mysql');
config()->set('database.connections.mysql.database', 'wms_mysql_test');
config()->set('wms_runtime.connection', 'mysql');
config()->set('wms_runtime.database', 'wms_mysql_test');
DB::purge('mysql');
$connection = MySqlTestGuard::connection();

file_put_contents($gateDirectory.'/ready-'.$slot, 'ready');
$deadline = microtime(true) + 20;
while (! is_file($gateDirectory.'/go')) {
    if (microtime(true) >= $deadline) {
        fwrite(STDOUT, json_encode(['ok' => false, 'error' => 'barrier timeout']).PHP_EOL);
        exit(2);
    }
    usleep(10_000);
}

try {
    $value = match ($mode) {
        'create_same' => app(QuizService::class)->create($payload['quiz'], $payload['teacher'], $payload['user_id']),
        'create_conflict' => (function () use ($payload, $slot) {
            $quiz = $payload['quiz'];
            $quiz['Title'] = $payload['conflicting_titles'][(int) $slot - 1];

            return app(QuizService::class)->create($quiz, $payload['teacher'], $payload['user_id']);
        })(),
        'token_create' => (function () use ($payload, $slot) {
            if (($payload['cache_mode'] ?? '') === 'isolated') {
                config()->set('cache.default', 'array');
                Cache::forget('quiz_media_stage_'.$payload['media_token_hash']);
                Cache::put('quiz_media_stage_'.$payload['media_token_hash'], $payload['media_record'], now()->addHour());
            }

            $quiz = $payload['quiz'];
            $quiz['Idempotency_Key'] = $payload['operation_keys'][(int) $slot - 1];

            return app(QuizService::class)->create($quiz, $payload['teacher'], $payload['user_id']);
        })(),
        'update' => (function () use ($payload, $slot) {
            $quiz = $payload['quiz'];
            $quiz['Idempotency_Key'] = $payload['operation_keys'][(int) $slot - 1];
            $quiz['Title'] = 'Concurrent Update '.$slot;
            app(QuizService::class)->update($payload['quiz_id'], $quiz, $payload['teacher'], $payload['user_id']);

            return $quiz['Title'];
        })(),
        'submit' => app(QuizService::class)->submit($payload['attempt_id'], $payload['student'], [$payload['question_id'] => 'A']),
        'autosave' => app(QuizService::class)->autosave(
            $payload['attempt_id'],
            $payload['student'],
            [$payload['question_id'] => ((int) $slot === 1 ? 'B' : 'A')],
            (int) $slot,
        ),
        'cleanup_or_finalize' => (int) $slot === 1
            ? app(QuizService::class)->submit($payload['attempt_id'], $payload['student'], [])
            : app(QuizCleanupService::class)->run(CarbonImmutable::parse($payload['cleanup_at']), 10),
        'deadlock' => (function () use ($connection, $payload, $slot, $gateDirectory) {
            $attempt = 0;

            return $connection->transaction(function () use ($connection, $payload, $slot, $gateDirectory, &$attempt) {
                $attempt++;
                $keys = (int) $slot === 1 ? $payload['row_keys'] : array_reverse($payload['row_keys']);
                $connection->table('QUIZ_MEDIA_OPERATION')->where('Idempotency_Key', $keys[0])->lockForUpdate()->first();
                if ($attempt === 1) {
                    file_put_contents($gateDirectory.'/locked-'.$slot, 'locked');
                    $deadline = microtime(true) + 10;
                    while ((! is_file($gateDirectory.'/locked-1') || ! is_file($gateDirectory.'/locked-2')) && microtime(true) < $deadline) {
                        usleep(10_000);
                    }
                }
                $connection->table('QUIZ_MEDIA_OPERATION')->where('Idempotency_Key', $keys[1])->lockForUpdate()->first();

                return ['attempts' => $attempt];
            }, 5);
        })(),
        'lock_timeout' => (function () use ($connection, $payload) {
            $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $connection->table('QUIZ_MEDIA_OPERATION')
                ->where('Idempotency_Key', $payload['row_key'])
                ->update(['Operation_Type' => 'locked']);

            return 'unexpected-success';
        })(),
        'crash_commit' => (function () use ($connection, $payload) {
            $connection->transaction(function () use ($connection, $payload): void {
                $connection->table('QUIZ')->insert($payload['quiz_row']);
                app(QuizMediaIdempotencyService::class)->recordCommitted(
                    $payload['operation_key'],
                    $payload['user_id'],
                    'create',
                    null,
                    $payload['payload_hash'],
                    $payload['quiz_row']['Quiz_ID'],
                    [$payload['token_use']],
                    $payload['committed_at'],
                );
            });
            fwrite(STDOUT, json_encode([
                'ok' => true,
                'pid' => getmypid(),
                'connection_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
                'value' => 'committed-before-cleanup',
            ], JSON_THROW_ON_ERROR).PHP_EOL);
            exit(86);
        })(),
        default => throw new RuntimeException("Unknown worker mode {$mode}"),
    };

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'pid' => getmypid(),
        'connection_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
        'value' => $value,
    ], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'pid' => getmypid(),
        'connection_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id ?? null,
        'error' => get_class($exception),
        'status' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : null,
    ], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
