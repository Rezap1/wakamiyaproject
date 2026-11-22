<?php

namespace Tests\Feature;

use App\Repositories\GoogleSheets\QuizQuestionRepository;
use App\Services\Quiz\QuizMediaIdempotencyService;
use App\Services\Quiz\QuizMediaService;
use App\Services\Quiz\QuizService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class QuizMediaSchemaTest extends TestCase
{
    public function test_media_migration_fails_when_legacy_question_table_is_missing(): void
    {
        $migration = require database_path('migrations/wms_mysql/2026_11_20_000001_add_quiz_question_media_fields.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires the existing QUIZ_QUESTION table');
        $migration->up();
    }

    public function test_additive_migration_upgrades_legacy_question_schema_with_nullable_media_columns(): void
    {
        Schema::create('QUIZ_QUESTION', function (Blueprint $table): void {
            $table->string('Question_ID')->primary();
            $table->string('Quiz_ID')->nullable();
            $table->text('Question_Text')->nullable();
        });

        $migration = require database_path('migrations/wms_mysql/2026_11_20_000001_add_quiz_question_media_fields.php');
        $migration->up();

        foreach ($this->mediaColumns() as $column) {
            $this->assertTrue(Schema::hasColumn('QUIZ_QUESTION', $column), $column);
        }
        $columns = collect(Schema::getColumns('QUIZ_QUESTION'))->keyBy('name');
        foreach ($this->mediaColumns() as $column) {
            $this->assertTrue((bool) $columns[$column]['nullable'], "{$column} must remain nullable for legacy quizzes.");
        }
    }

    public function test_runtime_columns_and_repository_headers_are_exactly_synchronized(): void
    {
        $runtime = config('wms_runtime.runtime_columns.QUIZ_QUESTION');

        $this->assertSame($this->mediaColumns(), $runtime);
        $this->assertSame($runtime, array_slice(QuizQuestionRepository::HEADERS, -count($runtime)));
    }

    public function test_media_migration_rejects_an_incompatible_existing_column(): void
    {
        Schema::create('QUIZ_QUESTION', function (Blueprint $table): void {
            $table->string('Question_ID')->primary();
            $table->integer('Image_Path')->nullable();
        });

        $migration = require database_path('migrations/wms_mysql/2026_11_20_000001_add_quiz_question_media_fields.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Image_Path has an incompatible type');
        $migration->up();
    }

    public function test_idempotency_migration_creates_authoritative_unique_records(): void
    {
        $migration = require database_path('migrations/wms_mysql/2026_11_21_000001_create_quiz_media_idempotency_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('QUIZ_MEDIA_OPERATION'));
        $this->assertTrue(Schema::hasTable('QUIZ_MEDIA_TOKEN_USE'));

        DB::table('QUIZ_MEDIA_OPERATION')->insert([
            'Idempotency_Key' => '10000000-0000-4000-8000-000000000001',
            'User_ID' => 'U1',
            'Operation_Type' => 'create',
            'Target_Quiz_ID' => null,
            'Payload_Hash' => str_repeat('a', 64),
            'Result_Quiz_ID' => 'Q1',
            'Committed_At' => '2026-11-21 08:00:00.000000',
        ]);
        DB::table('QUIZ_MEDIA_TOKEN_USE')->insert([
            'Token_Hash' => str_repeat('b', 64),
            'Idempotency_Key' => '10000000-0000-4000-8000-000000000001',
            'User_ID' => 'U1',
            'Quiz_ID' => 'Q1',
            'Question_ID' => 'QQ1',
            'Media_Type' => 'image',
            'Final_Path' => 'quiz-media/Q1/QQ1/a.jpg',
            'Committed_At' => '2026-11-21 08:00:00.000000',
        ]);

        $this->expectException(QueryException::class);
        DB::table('QUIZ_MEDIA_TOKEN_USE')->insert([
            'Token_Hash' => str_repeat('b', 64),
            'Idempotency_Key' => '20000000-0000-4000-8000-000000000002',
            'User_ID' => 'U2',
            'Quiz_ID' => 'Q2',
            'Question_ID' => 'QQ2',
            'Media_Type' => 'image',
            'Final_Path' => 'quiz-media/Q2/QQ2/b.jpg',
            'Committed_At' => '2026-11-21 08:01:00.000000',
        ]);
    }

    public function test_container_injects_durable_idempotency_into_quiz_services(): void
    {
        $quizService = $this->app->make(QuizService::class);
        $quizProperty = new \ReflectionProperty($quizService, 'mediaIdempotency');
        $this->assertInstanceOf(QuizMediaIdempotencyService::class, $quizProperty->getValue($quizService));

        $mediaService = $this->app->make(QuizMediaService::class);
        $mediaProperty = new \ReflectionProperty($mediaService, 'idempotency');
        $this->assertInstanceOf(QuizMediaIdempotencyService::class, $mediaProperty->getValue($mediaService));
    }

    public function test_idempotency_migration_resumes_after_one_table_was_already_created(): void
    {
        Schema::create('QUIZ_MEDIA_OPERATION', function (Blueprint $table): void {
            $table->char('Idempotency_Key', 36)->primary();
            $table->string('User_ID', 191);
            $table->string('Operation_Type', 16);
            $table->string('Target_Quiz_ID', 191)->nullable();
            $table->char('Payload_Hash', 64);
            $table->string('Result_Quiz_ID', 191);
            $table->dateTime('Committed_At', 6);
            $table->index(['User_ID', 'Committed_At'], 'quiz_media_operation_user_time');
        });

        $migration = require database_path('migrations/wms_mysql/2026_11_21_000001_create_quiz_media_idempotency_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('QUIZ_MEDIA_TOKEN_USE'));
    }

    public function test_idempotency_migration_rejects_incompatible_existing_table(): void
    {
        Schema::create('QUIZ_MEDIA_OPERATION', function (Blueprint $table): void {
            $table->string('Idempotency_Key')->primary();
        });

        $migration = require database_path('migrations/wms_mysql/2026_11_21_000001_create_quiz_media_idempotency_tables.php');

        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    private function mediaColumns(): array
    {
        return ['Image_Path', 'Image_Mime', 'Image_Size_Bytes', 'Audio_Path', 'Audio_Mime', 'Audio_Size_Bytes', 'Audio_Duration_Milliseconds'];
    }
}
