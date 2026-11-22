<?php

namespace Tests\Feature;

use App\Services\Quiz\QuizMediaIdempotencyService;
use App\Services\Quiz\QuizService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QuizMediaIdempotencyTest extends TestCase
{
    private QuizMediaIdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/wms_mysql/2026_11_21_000001_create_quiz_media_idempotency_tables.php'))->up();
        $this->service = new QuizMediaIdempotencyService;
    }

    public function test_committed_operation_replays_and_token_remains_consumed_without_cache(): void
    {
        $key = '10000000-0000-4000-8000-000000000011';
        $token = '20000000-0000-4000-8000-000000000022';
        $this->service->recordCommitted($key, 'U1', 'create', null, str_repeat('a', 64), 'Q1', [[
            'token_hash' => $this->service->tokenHash($token),
            'question_id' => 'QQ1',
            'media_type' => 'image',
            'final_path' => 'quiz-media/Q1/QQ1/a.jpg',
        ]], '2026-11-21 08:00:00.000000');

        $this->assertSame('Q1', $this->service->replayResult($key, 'U1', 'create', null, str_repeat('a', 64)));

        try {
            $this->service->assertTokenUnused($token);
            $this->fail('Committed staging token must remain unusable after cache cleanup.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_same_key_with_different_payload_is_rejected(): void
    {
        $key = '30000000-0000-4000-8000-000000000033';
        $this->service->recordCommitted($key, 'U1', 'update', 'Q1', str_repeat('a', 64), 'Q1', [], '2026-11-21 08:00:00.000000');

        $this->expectException(HttpException::class);
        $this->service->replayResult($key, 'U1', 'update', 'Q1', str_repeat('b', 64));
    }

    public function test_duplicate_token_rolls_back_second_operation_record(): void
    {
        $tokenHash = str_repeat('c', 64);
        $first = '40000000-0000-4000-8000-000000000044';
        $second = '50000000-0000-4000-8000-000000000055';
        $use = [[
            'token_hash' => $tokenHash,
            'question_id' => 'QQ1',
            'media_type' => 'audio',
            'final_path' => 'quiz-media/Q1/QQ1/a.mp3',
        ]];
        $this->service->recordCommitted($first, 'U1', 'create', null, str_repeat('a', 64), 'Q1', $use, '2026-11-21 08:00:00.000000');

        try {
            $this->service->recordCommitted($second, 'U2', 'create', null, str_repeat('b', 64), 'Q2', $use, '2026-11-21 08:01:00.000000');
            $this->fail('A durable token can only be committed once.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertFalse(DB::table('QUIZ_MEDIA_OPERATION')->where('Idempotency_Key', $second)->exists());
        $this->assertSame(1, DB::table('QUIZ_MEDIA_TOKEN_USE')->count());
    }

    public function test_outer_database_rollback_does_not_consume_operation_or_token(): void
    {
        $key = '60000000-0000-4000-8000-000000000066';
        $token = '70000000-0000-4000-8000-000000000077';
        try {
            DB::connection((string) config('wms_runtime.connection'))->transaction(function () use ($key, $token): void {
                $this->service->recordCommitted($key, 'U1', 'create', null, str_repeat('d', 64), 'Q1', [[
                    'token_hash' => $this->service->tokenHash($token),
                    'question_id' => 'QQ1',
                    'media_type' => 'image',
                    'final_path' => 'quiz-media/Q1/QQ1/a.jpg',
                ]], '2026-11-21 08:00:00.000000');
                throw new \RuntimeException('simulate failure before commit');
            });
        } catch (\RuntimeException) {
        }

        $this->assertNull($this->service->replayResult($key, 'U1', 'create', null, str_repeat('d', 64)));
        $this->service->assertTokenUnused($token);
        $this->addToAssertionCount(1);
    }

    public function test_identical_create_request_returns_committed_quiz_without_duplicate_rows(): void
    {
        (require database_path('migrations/wms_mysql/2026_10_02_000001_create_wms_business_schema.php'))->up();
        (require database_path('migrations/wms_mysql/2026_11_20_000001_add_quiz_question_media_fields.php'))->up();
        $service = $this->app->make(QuizService::class);
        $payload = [
            'Idempotency_Key' => '90000000-0000-4000-8000-000000000099',
            'Title' => 'Kuis Idempotent',
            'Class_ID' => 'C1',
            'Start_At' => '2026-12-01 08:00:00',
            'End_At' => '2026-12-01 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'draft',
            'questions' => [[
                'Question_Text' => 'Pertanyaan',
                'Option_A' => 'A',
                'Option_B' => 'B',
                'Option_C' => 'C',
                'Option_D' => 'D',
                'Correct_Option' => 'A',
                'Point' => 10,
            ]],
        ];

        $first = $service->create($payload, ['Teacher_ID' => 'T1'], 'U1');
        $second = $service->create($payload, ['Teacher_ID' => 'T1'], 'U1');

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('QUIZ')->count());
        $this->assertSame(1, DB::table('QUIZ_QUESTION')->count());
        $this->assertSame(1, DB::table('QUIZ_MEDIA_OPERATION')->count());

        $payload['Title'] = 'Payload Berbeda';
        try {
            $service->create($payload, ['Teacher_ID' => 'T1'], 'U1');
            $this->fail('The same idempotency key cannot be rebound to another payload.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('QUIZ')->count());
    }

    public function test_retry_after_committed_quiz_was_removed_returns_gone_without_recreation(): void
    {
        (require database_path('migrations/wms_mysql/2026_10_02_000001_create_wms_business_schema.php'))->up();
        (require database_path('migrations/wms_mysql/2026_11_20_000001_add_quiz_question_media_fields.php'))->up();
        $service = $this->app->make(QuizService::class);
        $payload = [
            'Idempotency_Key' => 'a0000000-0000-4000-8000-000000000099',
            'Title' => 'Kuis yang Dibersihkan',
            'Class_ID' => 'C1',
            'Start_At' => '2026-12-01 08:00:00',
            'End_At' => '2026-12-01 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'draft',
            'questions' => [[
                'Question_Text' => 'Pertanyaan',
                'Option_A' => 'A',
                'Option_B' => 'B',
                'Option_C' => 'C',
                'Option_D' => 'D',
                'Correct_Option' => 'A',
                'Point' => 10,
            ]],
        ];

        $quizId = $service->create($payload, ['Teacher_ID' => 'T1'], 'U1');
        DB::table('QUIZ_MEDIA_TOKEN_USE')->insert([
            'Token_Hash' => str_repeat('e', 64),
            'Idempotency_Key' => $payload['Idempotency_Key'],
            'User_ID' => 'U1',
            'Quiz_ID' => $quizId,
            'Question_ID' => 'REMOVED',
            'Media_Type' => 'image',
            'Final_Path' => 'quiz-media/removed.jpg',
            'Committed_At' => '2026-11-21 08:00:00.000000',
        ]);
        DB::table('QUIZ_QUESTION')->where('Quiz_ID', $quizId)->delete();
        DB::table('QUIZ')->where('Quiz_ID', $quizId)->delete();

        try {
            $service->create($payload, ['Teacher_ID' => 'T1'], 'U1');
            $this->fail('A committed operation whose resource is gone must not recreate the quiz.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }

        $this->assertSame(0, DB::table('QUIZ')->count());
        $this->assertSame(1, DB::table('QUIZ_MEDIA_OPERATION')->count());
        $this->assertSame(1, DB::table('QUIZ_MEDIA_TOKEN_USE')->count());
    }
}
