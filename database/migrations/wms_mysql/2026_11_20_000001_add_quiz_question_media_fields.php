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
        QuizMultimediaSchemaGuard::requireQuestionTable();

        Schema::table('QUIZ_QUESTION', function (Blueprint $table): void {
            if (! Schema::hasColumn('QUIZ_QUESTION', 'Image_Path')) {
                $table->string('Image_Path', 512)->nullable();
            }
            if (! Schema::hasColumn('QUIZ_QUESTION', 'Image_Mime')) {
                $table->string('Image_Mime', 100)->nullable();
            }
            if (! Schema::hasColumn('QUIZ_QUESTION', 'Image_Size_Bytes')) {
                $table->unsignedBigInteger('Image_Size_Bytes')->nullable();
            }
            if (! Schema::hasColumn('QUIZ_QUESTION', 'Audio_Path')) {
                $table->string('Audio_Path', 512)->nullable();
            }
            if (! Schema::hasColumn('QUIZ_QUESTION', 'Audio_Mime')) {
                $table->string('Audio_Mime', 100)->nullable();
            }
            if (! Schema::hasColumn('QUIZ_QUESTION', 'Audio_Size_Bytes')) {
                $table->unsignedBigInteger('Audio_Size_Bytes')->nullable();
            }
            if (! Schema::hasColumn('QUIZ_QUESTION', 'Audio_Duration_Milliseconds')) {
                $table->unsignedBigInteger('Audio_Duration_Milliseconds')->nullable();
            }
        });

        QuizMultimediaSchemaGuard::assertMediaColumns();
    }

    public function down(): void
    {
        QuizMultimediaSchemaGuard::assertMigrationTarget($this->getConnection());

        if (! Schema::hasTable('QUIZ_QUESTION')) {
            return;
        }

        $columns = collect([
            'Image_Path',
            'Image_Mime',
            'Image_Size_Bytes',
            'Audio_Path',
            'Audio_Mime',
            'Audio_Size_Bytes',
            'Audio_Duration_Milliseconds',
        ])->filter(fn (string $column): bool => Schema::hasColumn('QUIZ_QUESTION', $column))->all();

        if ($columns !== []) {
            Schema::table('QUIZ_QUESTION', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
