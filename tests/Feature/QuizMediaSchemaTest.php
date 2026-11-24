<?php

namespace Tests\Feature;

use App\Repositories\GoogleSheets\QuizAttemptRepository;
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

        $attemptRuntime = config('wms_runtime.runtime_columns.QUIZ_ATTEMPT');
        $this->assertSame(['Class_ID'], $attemptRuntime);
        $this->assertSame($attemptRuntime, array_slice(QuizAttemptRepository::HEADERS, -count($attemptRuntime)));
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

    public function test_audience_migration_is_additive_idempotent_and_backfills_legacy_class(): void
    {
        $this->createLegacyQuizTables();
        DB::table('QUIZ')->insert([
            'Quiz_ID' => 'Q-LEGACY', 'Class_ID' => 'C-ONE',
            'Created_At' => '2026-11-20 08:00:00', 'Updated_At' => '2026-11-21 08:00:00',
        ]);
        DB::table('QUIZ_ATTEMPT')->insert(['Attempt_ID' => 'A-LEGACY', 'Quiz_ID' => 'Q-LEGACY', 'Student_ID' => 'S1']);
        DB::table('QUIZ_RESULT')->insert(['Result_ID' => 'R-LEGACY', 'Attempt_ID' => 'A-LEGACY', 'Class_ID' => 'C-HISTORICAL']);
        $migration = require database_path('migrations/wms_mysql/2026_11_23_000001_add_quiz_audience_and_runtime_state.php');

        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('QUIZ_AUDIENCE_CLASS')->count());
        $this->assertSame('C-ONE', DB::table('QUIZ_AUDIENCE_CLASS')->where('Quiz_ID', 'Q-LEGACY')->value('Class_ID'));
        $this->assertSame(0, (int) DB::table('QUIZ_RUNTIME_STATE')->where('Quiz_ID', 'Q-LEGACY')->value('Attempt_Generation'));
        $this->assertTrue(Schema::hasTable('QUIZ_TOMBSTONE'));
        $this->assertTrue(Schema::hasColumn('QUIZ', 'Class_ID'), 'Legacy single-class column must remain intact.');
        $this->assertSame('C-HISTORICAL', DB::table('QUIZ_ATTEMPT')->where('Attempt_ID', 'A-LEGACY')->value('Class_ID'));
    }

    public function test_audience_migration_rejects_incompatible_partial_table(): void
    {
        $this->createLegacyQuizTables();
        Schema::create('QUIZ_AUDIENCE_CLASS', function (Blueprint $table): void {
            $table->string('Quiz_ID')->primary();
        });
        $migration = require database_path('migrations/wms_mysql/2026_11_23_000001_add_quiz_audience_and_runtime_state.php');

        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    public function test_audience_migration_backfills_every_attempt_across_chunk_boundaries(): void
    {
        $this->createLegacyQuizTables();
        DB::table('QUIZ')->insert([
            'Quiz_ID' => 'Q-BULK', 'Class_ID' => 'C-BULK',
            'Created_At' => '2026-11-20 08:00:00', 'Updated_At' => '2026-11-21 08:00:00',
        ]);
        collect(range(1, 501))->chunk(100)->each(function ($numbers): void {
            DB::table('QUIZ_ATTEMPT')->insert($numbers->map(fn (int $number): array => [
                'Attempt_ID' => sprintf('A-BULK-%04d', $number),
                'Quiz_ID' => 'Q-BULK',
                'Student_ID' => sprintf('S-BULK-%04d', $number),
            ])->all());
        });

        $migration = require database_path('migrations/wms_mysql/2026_11_23_000001_add_quiz_audience_and_runtime_state.php');
        $migration->up();

        $this->assertSame(501, DB::table('QUIZ_ATTEMPT')->where('Class_ID', 'C-BULK')->count());
        $this->assertSame(0, DB::table('QUIZ_ATTEMPT')->whereNull('Class_ID')->orWhere('Class_ID', '')->count());
    }

    private function mediaColumns(): array
    {
        return ['Image_Path', 'Image_Mime', 'Image_Size_Bytes', 'Audio_Path', 'Audio_Mime', 'Audio_Size_Bytes', 'Audio_Duration_Milliseconds'];
    }

    private function createLegacyQuizTables(): void
    {
        Schema::create('QUIZ', function (Blueprint $table): void {
            $table->string('Quiz_ID', 191)->primary();
            $table->string('Class_ID', 191);
            $table->dateTime('Created_At', 6)->nullable();
            $table->dateTime('Updated_At', 6)->nullable();
        });
        Schema::create('QUIZ_ATTEMPT', function (Blueprint $table): void {
            $table->string('Attempt_ID', 191)->primary();
            $table->string('Quiz_ID', 191);
            $table->string('Student_ID', 191);
        });
        Schema::create('QUIZ_RESULT', function (Blueprint $table): void {
            $table->string('Result_ID', 191)->primary();
            $table->string('Attempt_ID', 191);
            $table->string('Class_ID', 191);
        });
    }
}
