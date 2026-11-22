<?php

use App\Support\Migration\QuizMultimediaSchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        QuizMultimediaSchemaGuard::assertMigrationTarget($this->getConnection());

        if (! Schema::hasTable('QUIZ_MEDIA_OPERATION')) {
            Schema::create('QUIZ_MEDIA_OPERATION', function (Blueprint $table): void {
                if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->engine = 'InnoDB';
                }
                $table->char('Idempotency_Key', 36)->primary();
                $table->string('User_ID', 191);
                $table->string('Operation_Type', 16);
                $table->string('Target_Quiz_ID', 191)->nullable();
                $table->char('Payload_Hash', 64);
                $table->string('Result_Quiz_ID', 191);
                $table->dateTime('Committed_At', 6);
                $table->index(['User_ID', 'Committed_At'], 'quiz_media_operation_user_time');
            });
        }

        if (! Schema::hasTable('QUIZ_MEDIA_TOKEN_USE')) {
            Schema::create('QUIZ_MEDIA_TOKEN_USE', function (Blueprint $table): void {
                if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->engine = 'InnoDB';
                }
                $table->char('Token_Hash', 64)->primary();
                $table->char('Idempotency_Key', 36);
                $table->string('User_ID', 191);
                $table->string('Quiz_ID', 191);
                $table->string('Question_ID', 191);
                $table->string('Media_Type', 16);
                $table->string('Final_Path', 512);
                $table->dateTime('Committed_At', 6);
                $table->index('Idempotency_Key', 'quiz_media_token_operation');
                $table->index('Quiz_ID', 'quiz_media_token_quiz');
            });
        }

        QuizMultimediaSchemaGuard::assertIdempotencyTables();
    }

    public function down(): void
    {
        QuizMultimediaSchemaGuard::assertMigrationTarget($this->getConnection());

        Schema::dropIfExists('QUIZ_MEDIA_TOKEN_USE');
        Schema::dropIfExists('QUIZ_MEDIA_OPERATION');
    }
};
