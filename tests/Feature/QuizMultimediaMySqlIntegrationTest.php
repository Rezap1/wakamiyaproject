<?php

namespace Tests\Feature;

use App\Services\Quiz\QuizMediaIdempotencyService;
use App\Services\Quiz\QuizMediaService;
use App\Services\Quiz\QuizService;
use App\Support\Migration\QuizMultimediaSchemaGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\MySqlTestGuard;
use Tests\Support\UsesIsolatedMySqlDatabase;
use Tests\TestCase;

class QuizMultimediaMySqlIntegrationTest extends TestCase
{
    use UsesIsolatedMySqlDatabase;

    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        $connectionName = (string) config('wms_runtime.connection');
        if (! in_array((string) config("database.connections.{$connectionName}.driver"), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Quiz multimedia MySQL integration requires guarded wms_mysql_test.');
        }
        $connection = MySqlTestGuard::connection();
        QuizMultimediaSchemaGuard::assertMediaColumns();
        QuizMultimediaSchemaGuard::assertIdempotencyTables();
        $connection->beginTransaction();
        $this->transactionStarted = true;
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::connection((string) config('wms_runtime.connection'))->rollBack();
        }
        parent::tearDown();
    }

    public function test_mysql_schema_and_legacy_text_only_quiz_remain_compatible(): void
    {
        $this->assertTrue(Schema::hasColumns('QUIZ_QUESTION', array_keys(QuizMultimediaSchemaGuard::mediaColumns())));
        $quizId = 'QIZ-QMMYSQL-TEXT';
        DB::table('QUIZ')->insert([
            'Quiz_ID' => $quizId, 'Teacher_ID' => 'T-QMMYSQL', 'Class_ID' => 'C-QMMYSQL', 'Title' => 'Text Only',
            'Start_At' => '2026-12-01 08:00:00', 'End_At' => '2026-12-01 09:00:00', 'Duration_Minutes' => 30, 'Status' => 'DRAFT',
        ]);
        DB::table('QUIZ_QUESTION')->insert([
            'Question_ID' => 'QQ-QMMYSQL-TEXT', 'Quiz_ID' => $quizId, 'Question_Text' => 'Text?',
            'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D',
            'Correct_Option' => 'A', 'Point' => 10, 'Sort_Order' => 1,
        ]);

        $row = (array) DB::table('QUIZ_QUESTION')->where('Question_ID', 'QQ-QMMYSQL-TEXT')->first();
        foreach (array_keys(QuizMultimediaSchemaGuard::mediaColumns()) as $column) {
            $this->assertNull($row[$column]);
        }
    }

    public function test_mysql_create_and_update_persist_image_audio_and_media_only_question(): void
    {
        $audioPath = (string) getenv('QUIZ_AUDIO_MYSQL_FIXTURE');
        if (! is_file($audioPath)) {
            $this->markTestSkipped('Actual MySQL audio integration requires QUIZ_AUDIO_MYSQL_FIXTURE.');
        }
        $userId = 'U-QMMYSQL-MEDIA';
        $media = app(QuizMediaService::class);
        $image = $media->stage(UploadedFile::fake()->image('image.png', 64, 64), 'image', $userId);
        $audio = $media->stage($this->uploadedCopy($audioPath, 'audio.mp3', 'audio/mpeg'), 'audio', $userId);
        $payload = $this->payload('b0000000-0000-4000-8000-000000000001', [
            $this->question() + ['Image_Token' => $image['token']],
            $this->question() + ['Audio_Token' => $audio['token']],
        ]);
        $payload['questions'][1]['Question_Text'] = '';
        $quizId = app(QuizService::class)->create($payload, ['Teacher_ID' => 'T-QMMYSQL'], $userId);

        $questions = DB::table('QUIZ_QUESTION')->where('Quiz_ID', $quizId)->orderBy('Sort_Order')->get();
        $this->assertNotNull($questions[0]->Image_Path);
        $this->assertNull($questions[0]->Audio_Path);
        $this->assertNotNull($questions[1]->Audio_Path);
        $this->assertSame(90000, (int) $questions[1]->Audio_Duration_Milliseconds);
        $this->assertSame('', (string) $questions[1]->Question_Text);

        $replacement = $media->stage(UploadedFile::fake()->image('replacement.webp', 32, 32), 'image', $userId);
        $update = $this->payload('b0000000-0000-4000-8000-000000000002', [
            $this->question() + ['Image_Token' => $replacement['token']],
            $this->question() + ['Remove_Audio' => true],
        ]);
        app(QuizService::class)->update($quizId, $update, ['Teacher_ID' => 'T-QMMYSQL'], $userId);
        $updated = DB::table('QUIZ_QUESTION')->where('Quiz_ID', $quizId)->orderBy('Sort_Order')->get();
        $this->assertNotNull($updated[0]->Image_Path);
        $this->assertNull($updated[1]->Audio_Path);
        $this->assertNull($updated[1]->Audio_Duration_Milliseconds);
    }

    public function test_mysql_idempotency_retry_conflict_gone_and_tombstone_are_authoritative(): void
    {
        $payload = $this->payload('b0000000-0000-4000-8000-000000000003', [$this->question()]);
        $service = app(QuizService::class);
        $first = $service->create($payload, ['Teacher_ID' => 'T-QMMYSQL'], 'U-QMMYSQL-IDEM');
        $this->assertSame($first, $service->create($payload, ['Teacher_ID' => 'T-QMMYSQL'], 'U-QMMYSQL-IDEM'));

        $different = $payload;
        $different['Title'] = 'Different';
        try {
            $service->create($different, ['Teacher_ID' => 'T-QMMYSQL'], 'U-QMMYSQL-IDEM');
            $this->fail('Different payload must conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        DB::table('QUIZ_QUESTION')->where('Quiz_ID', $first)->delete();
        DB::table('QUIZ')->where('Quiz_ID', $first)->delete();
        try {
            $service->create($payload, ['Teacher_ID' => 'T-QMMYSQL'], 'U-QMMYSQL-IDEM');
            $this->fail('Deleted committed resource must return gone.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('QUIZ_MEDIA_OPERATION')->where('Idempotency_Key', $payload['Idempotency_Key'])->count());
    }

    public function test_mysql_duplicate_token_rolls_back_the_losing_operation(): void
    {
        $idempotency = app(QuizMediaIdempotencyService::class);
        $tokenHash = hash('sha256', 'QMMYSQL-DUPLICATE');
        $use = [[
            'token_hash' => $tokenHash, 'question_id' => 'QQ-QMMYSQL', 'media_type' => 'image',
            'final_path' => 'quiz-media/qmmysql/image.png',
        ]];
        $idempotency->recordCommitted('b0000000-0000-4000-8000-000000000004', 'U1', 'create', null, str_repeat('a', 64), 'Q1', $use, '2026-11-21 08:00:00.000000');
        try {
            $idempotency->recordCommitted('b0000000-0000-4000-8000-000000000005', 'U2', 'create', null, str_repeat('b', 64), 'Q2', $use, '2026-11-21 08:00:01.000000');
            $this->fail('Duplicate token must lose the transaction.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('QUIZ_MEDIA_OPERATION')->where('Idempotency_Key', 'b0000000-0000-4000-8000-000000000005')->count());
    }

    private function payload(string $key, array $questions): array
    {
        return [
            'Idempotency_Key' => $key, 'Title' => 'MySQL Multimedia', 'Class_ID' => 'C-QMMYSQL',
            'Start_At' => '2026-12-01 08:00:00', 'End_At' => '2026-12-01 09:00:00',
            'Duration_Minutes' => 30, 'intent' => 'draft', 'questions' => $questions,
        ];
    }

    private function question(): array
    {
        return [
            'Question_Text' => 'Question', 'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D',
            'Correct_Option' => 'A', 'Point' => 10,
        ];
    }

    private function uploadedCopy(string $source, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'qmmysql-audio-');
        copy($source, $path);
        $this->beforeApplicationDestroyed(static fn () => @unlink($path));

        return new UploadedFile($path, $name, $mime, UPLOAD_ERR_OK, true);
    }
}
