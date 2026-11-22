<?php

namespace Tests\Feature;

use App\Services\Quiz\QuizMediaIdempotencyService;
use App\Services\Quiz\QuizMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\MySqlTestGuard;
use Tests\Support\UsesIsolatedMySqlDatabase;
use Tests\TestCase;

class QuizMultimediaMySqlConcurrencyTest extends TestCase
{
    use UsesIsolatedMySqlDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $connectionName = (string) config('wms_runtime.connection');
        if (! in_array((string) config("database.connections.{$connectionName}.driver"), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Quiz multimedia concurrency requires guarded wms_mysql_test.');
        }
        MySqlTestGuard::connection();
    }

    public function test_concurrent_identical_create_replays_single_quiz(): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $results = $this->runWorkers('create_same', $token, $payload);

            $this->assertSame(2, collect($results)->where('ok', true)->count());
            $this->assertCount(1, collect($results)->pluck('value')->unique());
            $this->assertIndependentWorkers($results);
            $this->assertSame(1, DB::table('QUIZ_MEDIA_OPERATION')->where('User_ID', $payload['user_id'])->count());
            $this->assertSame(1, DB::table('QUIZ')->whereIn('Quiz_ID', collect($results)->pluck('value'))->count());
        } finally {
            $this->cleanup($token);
        }
    }

    public function test_concurrent_same_key_different_payload_has_one_conflict(): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $payload['conflicting_titles'] = ['Payload A', 'Payload B'];
            $results = $this->runWorkers('create_conflict', $token, $payload);

            $this->assertSame(1, collect($results)->where('ok', true)->count());
            $this->assertSame(1, collect($results)->where('status', 409)->count());
            $this->assertIndependentWorkers($results);
            $this->assertSame(1, DB::table('QUIZ_MEDIA_OPERATION')->where('User_ID', $payload['user_id'])->count());
        } finally {
            $this->cleanup($token);
        }
    }

    public function test_database_uniqueness_blocks_double_token_without_shared_cache_lock(): void
    {
        $this->assertTokenRace('isolated');
    }

    public function test_shared_cache_lock_and_database_tombstone_block_double_token(): void
    {
        $this->assertTokenRace('shared');
    }

    public function test_concurrent_update_keeps_one_complete_question_set(): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $quizId = $this->seedQuiz($token);
            $payload['quiz_id'] = $quizId;
            $payload['operation_keys'] = [$this->uuid($token, 2), $this->uuid($token, 3)];
            $results = $this->runWorkers('update', $token, $payload);

            $this->assertSame(2, collect($results)->where('ok', true)->count());
            $this->assertIndependentWorkers($results);
            $this->assertContains(DB::table('QUIZ')->where('Quiz_ID', $quizId)->value('Title'), ['Concurrent Update 1', 'Concurrent Update 2']);
            $this->assertSame(1, DB::table('QUIZ_QUESTION')->where('Quiz_ID', $quizId)->count());
        } finally {
            $this->cleanup($token);
        }
    }

    public function test_crash_after_commit_leaves_authoritative_replay_and_token_tombstone(): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $media = $this->stageImage($payload['user_id']);
            $record = app(QuizMediaService::class)->staged($media['token'], $payload['user_id'], 'image');
            $quizId = 'QIZ-QM-'.$token;
            $payload += [
                'quiz_row' => [
                    'Quiz_ID' => $quizId, 'Teacher_ID' => $payload['teacher']['Teacher_ID'], 'Class_ID' => 'C-'.$token,
                    'Title' => 'Crash Commit', 'Start_At' => '2026-12-01 08:00:00', 'End_At' => '2026-12-01 09:00:00',
                    'Duration_Minutes' => 30, 'Status' => 'DRAFT',
                ],
                'operation_key' => $this->uuid($token, 4),
                'payload_hash' => hash('sha256', 'crash-'.$token),
                'committed_at' => '2026-11-21 08:00:00.000000',
                'token_use' => [
                    'token_hash' => app(QuizMediaIdempotencyService::class)->tokenHash($media['token']),
                    'question_id' => 'QQ-'.$token,
                    'media_type' => 'image',
                    'final_path' => 'quiz-media/'.$quizId.'/crash.png',
                ],
            ];
            $results = $this->runWorkers('crash_commit', $token, $payload, 1);

            $this->assertTrue($results[0]['ok']);
            $this->assertSame($quizId, app(QuizMediaIdempotencyService::class)->replayResult(
                $payload['operation_key'], $payload['user_id'], 'create', null, $payload['payload_hash']
            ));
            $this->assertNotNull(Cache::get('quiz_media_stage_'.hash('sha256', $media['token'])));
            try {
                app(QuizMediaIdempotencyService::class)->assertTokenUnused($media['token']);
                $this->fail('Committed token must remain consumed after simulated crash.');
            } catch (HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
            Storage::disk('local')->delete($record['path']);
            Cache::forget('quiz_media_stage_'.hash('sha256', $media['token']));
        } finally {
            $this->cleanup($token);
        }
    }

    public function test_real_deadlock_is_retried_without_partial_transaction(): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $payload['row_keys'] = [$this->uuid($token, 5), $this->uuid($token, 6)];
            foreach ($payload['row_keys'] as $key) {
                $this->insertOperation($key, $payload['user_id'], 'deadlock', 'Q-'.$token);
            }
            $results = $this->runWorkers('deadlock', $token, $payload);

            $this->assertSame(2, collect($results)->where('ok', true)->count());
            $this->assertIndependentWorkers($results);
            $this->assertTrue(collect($results)->contains(fn (array $result): bool => (int) data_get($result, 'value.attempts', 0) > 1));
            $this->assertSame(2, DB::table('QUIZ_MEDIA_OPERATION')->where('User_ID', $payload['user_id'])->count());
        } finally {
            $this->cleanup($token);
        }
    }

    public function test_lock_timeout_worker_fails_and_leaves_row_unchanged(): void
    {
        $token = $this->token();
        $connection = DB::connection((string) config('wms_runtime.connection'));
        try {
            $payload = $this->workerPayload($token);
            $payload['row_key'] = $this->uuid($token, 7);
            $this->insertOperation($payload['row_key'], $payload['user_id'], 'original', 'Q-'.$token);
            $connection->beginTransaction();
            $connection->table('QUIZ_MEDIA_OPERATION')->where('Idempotency_Key', $payload['row_key'])->lockForUpdate()->first();
            $results = $this->runWorkers('lock_timeout', $token, $payload, 1);
            $connection->rollBack();

            $this->assertFalse($results[0]['ok']);
            $this->assertSame('original', DB::table('QUIZ_MEDIA_OPERATION')->where('Idempotency_Key', $payload['row_key'])->value('Operation_Type'));
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $this->cleanup($token);
        }
    }

    public function test_concurrent_submit_creates_one_result_without_scoring_change(): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $this->seedAttempt($token, $payload);
            $results = $this->runWorkers('submit', $token, $payload);

            $this->assertSame(2, collect($results)->where('ok', true)->count());
            $this->assertIndependentWorkers($results);
            $result = (array) DB::table('QUIZ_RESULT')->where('Attempt_ID', $payload['attempt_id'])->first();
            $this->assertSame('10.0000', (string) $result['Raw_Score']);
            $this->assertSame(1, DB::table('QUIZ_RESULT')->where('Attempt_ID', $payload['attempt_id'])->count());
        } finally {
            $this->cleanup($token);
        }
    }

    public function test_concurrent_autosave_preserves_highest_version(): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $this->seedAttempt($token, $payload);
            $results = $this->runWorkers('autosave', $token, $payload);

            $this->assertIndependentWorkers($results);
            $state = json_decode((string) DB::table('QUIZ_ATTEMPT')->where('Attempt_ID', $payload['attempt_id'])->value('Submitted_Answers_JSON'), true);
            $this->assertSame(2, $state['__quiz_answer_version']);
            $this->assertSame('A', $state[$payload['question_id']]);
        } finally {
            $this->cleanup($token);
        }
    }

    public function test_cleanup_and_finalization_preserve_single_permanent_result(): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $this->seedAttempt($token, $payload, true);
            $payload['cleanup_at'] = '2026-12-31 12:00:00';
            $results = $this->runWorkers('cleanup_or_finalize', $token, $payload);

            $this->assertIndependentWorkers($results);
            $this->assertSame(1, DB::table('QUIZ_RESULT')->where('Attempt_ID', $payload['attempt_id'])->count());
            $this->assertSame(0, DB::table('QUIZ_ATTEMPT')->where('Attempt_ID', $payload['attempt_id'])->count());
        } finally {
            $this->cleanup($token);
        }
    }

    private function assertTokenRace(string $cacheMode): void
    {
        $token = $this->token();
        try {
            $payload = $this->workerPayload($token);
            $media = $this->stageImage($payload['user_id']);
            $record = app(QuizMediaService::class)->staged($media['token'], $payload['user_id'], 'image');
            $payload['quiz']['questions'][0]['Image_Token'] = $media['token'];
            $payload['cache_mode'] = $cacheMode;
            $payload['media_record'] = $record;
            $payload['media_token_hash'] = hash('sha256', $media['token']);
            $payload['operation_keys'] = [$this->uuid($token, 8), $this->uuid($token, 9)];
            $results = $this->runWorkers('token_create', $token, $payload);

            $this->assertSame(1, collect($results)->where('ok', true)->count());
            $this->assertSame(1, DB::table('QUIZ_MEDIA_TOKEN_USE')->where('User_ID', $payload['user_id'])->count());
            $this->assertIndependentWorkers($results);
        } finally {
            $this->cleanup($token);
        }
    }

    /** @return list<array<string,mixed>> */
    private function runWorkers(string $mode, string $token, array $payload, int $count = 2): array
    {
        $gate = storage_path('framework/testing/quiz-mysql-'.$token.'-'.bin2hex(random_bytes(2)));
        File::ensureDirectoryExists($gate);
        $payloadPath = $gate.'/payload.json';
        File::put($payloadPath, json_encode($payload, JSON_THROW_ON_ERROR));
        $processes = [];

        try {
            foreach (range(1, $count) as $slot) {
                $pipes = [];
                $process = proc_open([
                    PHP_BINARY, base_path('tests/Support/quiz_multimedia_concurrency_worker.php'),
                    $mode, $token, (string) $slot, $gate, $payloadPath,
                ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new \RuntimeException('Unable to start quiz concurrency worker.');
                }
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $processes[] = [$process, $pipes, '', ''];
            }
            $deadline = microtime(true) + 20;
            do {
                $ready = true;
                foreach (range(1, $count) as $slot) {
                    $ready = $ready && is_file($gate.'/ready-'.$slot);
                }
                if (! $ready) {
                    usleep(10_000);
                }
            } while (! $ready && microtime(true) < $deadline);
            if (! $ready) {
                throw new \RuntimeException('Quiz concurrency workers did not reach the barrier.');
            }
            File::put($gate.'/go', 'go');

            $running = true;
            while ($running && microtime(true) < $deadline) {
                $running = false;
                foreach ($processes as &$entry) {
                    [$process, $pipes] = $entry;
                    $entry[2] .= stream_get_contents($pipes[1]);
                    $entry[3] .= stream_get_contents($pipes[2]);
                    $running = $running || (proc_get_status($process)['running'] ?? false);
                }
                unset($entry);
                if ($running) {
                    usleep(10_000);
                }
            }
            if ($running) {
                throw new \RuntimeException('Quiz concurrency workers exceeded the 20 second timeout.');
            }

            $results = [];
            foreach ($processes as [$process, $pipes, $stdout, $stderr]) {
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $lines = array_values(array_filter(preg_split('/\R/', trim($stdout)) ?: []));
                $result = json_decode((string) end($lines), true);
                if (! is_array($result)) {
                    throw new \RuntimeException('Invalid worker output: '.$stderr.' '.$stdout);
                }
                $results[] = $result;
            }

            return $results;
        } finally {
            foreach ($processes as [$process, $pipes]) {
                if (is_resource($process)) {
                    $status = proc_get_status($process);
                    if ($status['running'] ?? false) {
                        proc_terminate($process, 9);
                    }
                }
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
            }
            File::deleteDirectory($gate);
        }
    }

    private function assertIndependentWorkers(array $results): void
    {
        $this->assertCount(count($results), collect($results)->pluck('pid')->unique());
        $this->assertCount(count($results), collect($results)->pluck('connection_id')->unique());
    }

    private function workerPayload(string $token): array
    {
        return [
            'user_id' => 'U-QM-'.$token,
            'teacher' => ['Teacher_ID' => 'T-QM-'.$token],
            'quiz' => [
                'Idempotency_Key' => $this->uuid($token, 1),
                'Title' => 'Concurrent Quiz '.$token,
                'Class_ID' => 'C-QM-'.$token,
                'Start_At' => '2026-12-01 08:00:00',
                'End_At' => '2026-12-01 09:00:00',
                'Duration_Minutes' => 30,
                'intent' => 'draft',
                'questions' => [$this->question()],
            ],
        ];
    }

    private function seedQuiz(string $token): string
    {
        $quizId = 'QIZ-QM-'.$token;
        DB::table('QUIZ')->insert([
            'Quiz_ID' => $quizId, 'Teacher_ID' => 'T-QM-'.$token, 'Class_ID' => 'C-QM-'.$token,
            'Title' => 'Seed', 'Start_At' => '2026-12-01 08:00:00', 'End_At' => '2026-12-01 09:00:00',
            'Duration_Minutes' => 30, 'Status' => 'DRAFT',
        ]);
        DB::table('QUIZ_QUESTION')->insert($this->questionRow($quizId, 'QQ-QM-'.$token));

        return $quizId;
    }

    private function seedAttempt(string $token, array &$payload, bool $expired = false): void
    {
        $quizId = 'QIZ-QM-'.$token;
        $questionId = 'QQ-QM-'.$token;
        $attemptId = 'QAT-QM-'.$token;
        $start = $expired ? '2026-10-01 00:00:00' : '2026-11-21 07:00:00';
        $deadline = $expired ? '2026-10-01 01:00:00' : '2030-01-01 00:00:00';
        DB::table('QUIZ')->insert([
            'Quiz_ID' => $quizId, 'Teacher_ID' => 'T-QM-'.$token, 'Class_ID' => 'C-QM-'.$token,
            'Title' => 'Attempt Quiz', 'Start_At' => $start, 'End_At' => $deadline,
            'Duration_Minutes' => 60, 'Status' => 'PUBLISHED',
        ]);
        DB::table('QUIZ_QUESTION')->insert($this->questionRow($quizId, $questionId));
        DB::table('QUIZ_ATTEMPT')->insert([
            'Attempt_ID' => $attemptId, 'Quiz_ID' => $quizId, 'Student_ID' => 'S-QM-'.$token,
            'Started_At' => $start, 'Deadline_At' => $deadline, 'Status' => 'IN_PROGRESS',
            'Submitted_Answers_JSON' => '',
        ]);
        $payload['attempt_id'] = $attemptId;
        $payload['question_id'] = $questionId;
        $payload['student'] = ['Student_ID' => 'S-QM-'.$token, 'Class_ID' => 'C-QM-'.$token, 'Full_Name' => 'Dummy'];
    }

    private function questionRow(string $quizId, string $questionId): array
    {
        return [
            'Question_ID' => $questionId, 'Quiz_ID' => $quizId, 'Question_Text' => 'Q?',
            'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D',
            'Correct_Option' => 'A', 'Point' => 10, 'Sort_Order' => 1,
        ];
    }

    private function question(): array
    {
        return [
            'Question_Text' => 'Q?', 'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D',
            'Correct_Option' => 'A', 'Point' => 10,
        ];
    }

    private function stageImage(string $userId): array
    {
        return app(QuizMediaService::class)->stage(UploadedFile::fake()->image('race.png', 32, 32), 'image', $userId);
    }

    private function insertOperation(string $key, string $userId, string $type, string $quizId): void
    {
        DB::table('QUIZ_MEDIA_OPERATION')->insert([
            'Idempotency_Key' => $key, 'User_ID' => $userId, 'Operation_Type' => $type,
            'Target_Quiz_ID' => null, 'Payload_Hash' => hash('sha256', $key), 'Result_Quiz_ID' => $quizId,
            'Committed_At' => '2026-11-21 08:00:00.000000',
        ]);
    }

    private function cleanup(string $token): void
    {
        $userId = 'U-QM-'.$token;
        $quizIds = DB::table('QUIZ_MEDIA_OPERATION')->where('User_ID', $userId)->pluck('Result_Quiz_ID')
            ->push('QIZ-QM-'.$token)->unique()->values();
        $paths = DB::table('QUIZ_QUESTION')->whereIn('Quiz_ID', $quizIds)->get()
            ->flatMap(fn (object $row) => [$row->Image_Path ?? null, $row->Audio_Path ?? null])->filter();
        Storage::disk('local')->delete($paths->all());
        DB::table('QUIZ_MEDIA_TOKEN_USE')->where('User_ID', $userId)->delete();
        DB::table('QUIZ_MEDIA_OPERATION')->where('User_ID', $userId)->delete();
        DB::table('QUIZ_RESULT')->whereIn('Quiz_ID', $quizIds)->delete();
        DB::table('QUIZ_ATTEMPT')->whereIn('Quiz_ID', $quizIds)->delete();
        DB::table('QUIZ_QUESTION')->whereIn('Quiz_ID', $quizIds)->delete();
        DB::table('QUIZ')->whereIn('Quiz_ID', $quizIds)->delete();
    }

    private function token(): string
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }

    private function uuid(string $token, int $suffix): string
    {
        return strtolower($token).'-0000-4000-8000-'.str_pad((string) $suffix, 12, '0', STR_PAD_LEFT);
    }
}
