<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertSafeTarget();
        $schema = Schema::connection((string) config('wms_runtime.connection', config('database.default')));

        if (! $schema->hasTable('PRACTICE_QUIZ')) {
            $schema->create('PRACTICE_QUIZ', function (Blueprint $table): void {
                $this->innodb($table);
                $table->string('Practice_Quiz_ID', 36)->primary();
                $table->string('Teacher_ID', 191)->index();
                $table->string('Title', 160);
                $table->text('Description')->nullable();
                $table->text('Instructions')->nullable();
                $table->unsignedSmallInteger('Duration_Minutes');
                $table->string('Status', 20)->default('DRAFT')->index();
                $table->string('Current_Revision_ID', 36)->nullable()->index();
                $table->string('Draft_Revision_ID', 36)->nullable()->index();
                $table->dateTime('Created_At', 6);
                $table->dateTime('Updated_At', 6);
                $table->dateTime('Published_At', 6)->nullable();
                $table->dateTime('Deleted_At', 6)->nullable()->index();
                $table->string('Deleted_By', 191)->nullable();
            });
        }

        if (! $schema->hasTable('PRACTICE_QUIZ_REVISION')) {
            $schema->create('PRACTICE_QUIZ_REVISION', function (Blueprint $table): void {
                $this->innodb($table);
                $table->string('Revision_ID', 36)->primary();
                $table->string('Practice_Quiz_ID', 36);
                $table->unsignedInteger('Revision_Number');
                $table->string('Title', 160);
                $table->text('Description')->nullable();
                $table->text('Instructions')->nullable();
                $table->unsignedSmallInteger('Duration_Minutes');
                $table->unsignedSmallInteger('Question_Count');
                $table->string('Status', 20);
                $table->dateTime('Created_At', 6);
                $table->string('Created_By', 191);
                $table->unique(['Practice_Quiz_ID', 'Revision_Number'], 'practice_revision_number_unique');
                $table->foreign('Practice_Quiz_ID', 'practice_revision_quiz_fk')->references('Practice_Quiz_ID')->on('PRACTICE_QUIZ');
            });
        }

        if (! $schema->hasTable('PRACTICE_QUESTION_REVISION')) {
            $schema->create('PRACTICE_QUESTION_REVISION', function (Blueprint $table): void {
                $this->innodb($table);
                $table->string('Question_ID', 36)->primary();
                $table->string('Revision_ID', 36);
                $table->unsignedSmallInteger('Sort_Order');
                $table->text('Question_Text');
                $table->text('Option_A');
                $table->text('Option_B');
                $table->text('Option_C');
                $table->text('Option_D');
                $table->char('Correct_Option', 1);
                $table->decimal('Point', 10, 2);
                $table->text('Explanation')->nullable();
                $table->string('Image_Path', 500)->nullable();
                $table->string('Image_Mime', 100)->nullable();
                $table->unsignedBigInteger('Image_Size_Bytes')->nullable();
                $table->string('Audio_Path', 500)->nullable();
                $table->string('Audio_Mime', 100)->nullable();
                $table->unsignedBigInteger('Audio_Size_Bytes')->nullable();
                $table->unsignedInteger('Audio_Duration_Milliseconds')->nullable();
                $table->dateTime('Created_At', 6);
                $table->unique(['Revision_ID', 'Sort_Order'], 'practice_question_order_unique');
                $table->foreign('Revision_ID', 'practice_question_revision_fk')->references('Revision_ID')->on('PRACTICE_QUIZ_REVISION');
            });
        }

        if (! $schema->hasTable('PRACTICE_ATTEMPT')) {
            $schema->create('PRACTICE_ATTEMPT', function (Blueprint $table): void {
                $this->innodb($table);
                $table->string('Attempt_ID', 36)->primary();
                $table->string('Practice_Quiz_ID', 36);
                $table->string('Revision_ID', 36);
                $table->string('Student_ID', 191);
                $table->string('Class_ID', 191)->nullable();
                $table->unsignedInteger('Attempt_Number');
                $table->string('Status', 20)->default('IN_PROGRESS');
                $table->string('Active_Key', 400)->nullable()->unique('practice_one_active_attempt_unique');
                $table->unsignedBigInteger('Answer_Version')->default(0);
                $table->dateTime('Started_At', 6);
                $table->dateTime('Deadline_At', 6);
                $table->dateTime('Completed_At', 6)->nullable();
                $table->dateTime('Created_At', 6);
                $table->dateTime('Updated_At', 6);
                $table->unique(['Practice_Quiz_ID', 'Student_ID', 'Attempt_Number'], 'practice_attempt_number_unique');
                $table->index(['Student_ID', 'Status'], 'practice_attempt_student_status');
                $table->index(['Status', 'Deadline_At'], 'practice_attempt_expiry');
                $table->foreign('Practice_Quiz_ID', 'practice_attempt_quiz_fk')->references('Practice_Quiz_ID')->on('PRACTICE_QUIZ');
                $table->foreign('Revision_ID', 'practice_attempt_revision_fk')->references('Revision_ID')->on('PRACTICE_QUIZ_REVISION');
            });
        }

        if (! $schema->hasTable('PRACTICE_ANSWER')) {
            $schema->create('PRACTICE_ANSWER', function (Blueprint $table): void {
                $this->innodb($table);
                $table->string('Answer_ID', 36)->primary();
                $table->string('Attempt_ID', 36);
                $table->string('Question_ID', 36);
                $table->char('Selected_Option', 1)->nullable();
                $table->unsignedBigInteger('Answer_Version');
                $table->dateTime('Saved_At', 6);
                $table->unique(['Attempt_ID', 'Question_ID'], 'practice_answer_unique');
                $table->foreign('Attempt_ID', 'practice_answer_attempt_fk')->references('Attempt_ID')->on('PRACTICE_ATTEMPT');
                $table->foreign('Question_ID', 'practice_answer_question_fk')->references('Question_ID')->on('PRACTICE_QUESTION_REVISION');
            });
        }

        if (! $schema->hasTable('PRACTICE_RESULT')) {
            $schema->create('PRACTICE_RESULT', function (Blueprint $table): void {
                $this->innodb($table);
                $table->string('Result_ID', 36)->primary();
                $table->string('Attempt_ID', 36)->unique();
                $table->string('Practice_Quiz_ID', 36);
                $table->string('Revision_ID', 36);
                $table->string('Student_ID', 191);
                $table->string('Class_ID', 191)->nullable();
                $table->string('Quiz_Title', 160);
                $table->unsignedInteger('Attempt_Number');
                $table->decimal('Raw_Score', 12, 2);
                $table->decimal('Max_Score', 12, 2);
                $table->decimal('Normalized_Score', 7, 2);
                $table->unsignedSmallInteger('Correct_Count');
                $table->unsignedSmallInteger('Incorrect_Count');
                $table->unsignedSmallInteger('Unanswered_Count');
                $table->dateTime('Started_At', 6);
                $table->dateTime('Completed_At', 6);
                $table->unsignedInteger('Duration_Seconds');
                $table->dateTime('Created_At', 6);
                $table->index(['Student_ID', 'Completed_At'], 'practice_result_student_history');
                $table->index(['Practice_Quiz_ID', 'Student_ID'], 'practice_result_progress');
                $table->foreign('Attempt_ID', 'practice_result_attempt_fk')->references('Attempt_ID')->on('PRACTICE_ATTEMPT');
                $table->foreign('Practice_Quiz_ID', 'practice_result_quiz_fk')->references('Practice_Quiz_ID')->on('PRACTICE_QUIZ');
                $table->foreign('Revision_ID', 'practice_result_revision_fk')->references('Revision_ID')->on('PRACTICE_QUIZ_REVISION');
            });
        }

        if (! $schema->hasTable('PRACTICE_OPERATION')) {
            $schema->create('PRACTICE_OPERATION', function (Blueprint $table): void {
                $this->innodb($table);
                $table->string('Operation_Key', 36)->primary();
                $table->string('User_ID', 191);
                $table->string('Operation', 40);
                $table->string('Scope_ID', 191)->nullable();
                $table->char('Payload_Hash', 64);
                $table->text('Result_JSON')->nullable();
                $table->dateTime('Created_At', 6);
                $table->index(['User_ID', 'Operation'], 'practice_operation_lookup');
            });
        }
    }

    public function down(): void
    {
        $this->assertSafeTarget();
        $schema = Schema::connection((string) config('wms_runtime.connection', config('database.default')));
        $schema->dropIfExists('PRACTICE_OPERATION');
        $schema->dropIfExists('PRACTICE_RESULT');
        $schema->dropIfExists('PRACTICE_ANSWER');
        $schema->dropIfExists('PRACTICE_ATTEMPT');
        $schema->dropIfExists('PRACTICE_QUESTION_REVISION');
        $schema->dropIfExists('PRACTICE_QUIZ_REVISION');
        $schema->dropIfExists('PRACTICE_QUIZ');
    }

    private function innodb(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
    }

    private function assertSafeTarget(): void
    {
        $name = (string) config('wms_runtime.connection', config('database.default'));
        $connection = DB::connection($name);
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            if (! app()->environment('testing')) {
                throw new RuntimeException('Practice quiz migration requires MariaDB/MySQL outside tests.');
            }

            return;
        }

        $expected = trim((string) config('wms_runtime.database'));
        $actual = trim((string) $connection->getDatabaseName());
        if ($expected === '' || ! hash_equals($expected, $actual)) {
            throw new RuntimeException("Practice quiz migration target mismatch: expected '{$expected}', actual '{$actual}'.");
        }
        $schema = Schema::connection($name);
        foreach (['MASTER_USER', 'MASTER_TEACHER', 'MASTER_STUDENT', 'QUIZ', 'QUIZ_ATTEMPT', 'QUIZ_RESULT'] as $marker) {
            if (! $schema->hasTable($marker)) {
                throw new RuntimeException("Practice quiz migration requires existing WMS marker table '{$marker}'.");
            }
        }
    }
};
