<?php

use App\Support\Migration\QuizMultimediaSchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        QuizMultimediaSchemaGuard::assertMigrationTarget($this->getConnection());
        if (! Schema::hasTable('QUIZ') || ! Schema::hasTable('QUIZ_ATTEMPT')) {
            throw new RuntimeException('Quiz audience migration requires the existing QUIZ and QUIZ_ATTEMPT tables.');
        }
        if (! Schema::hasColumn('QUIZ_ATTEMPT', 'Class_ID')) {
            Schema::table('QUIZ_ATTEMPT', function (Blueprint $table): void {
                // Runtime-only columns are appended so the exact repository
                // header order remains legacy columns followed by additions.
                $table->string('Class_ID', 191)->nullable();
            });
        }

        if (! Schema::hasTable('QUIZ_AUDIENCE_CLASS')) {
            Schema::create('QUIZ_AUDIENCE_CLASS', function (Blueprint $table): void {
                if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->engine = 'InnoDB';
                }
                $table->string('Quiz_ID', 191);
                $table->string('Class_ID', 191);
                $table->dateTime('Created_At', 6);
                $table->primary(['Quiz_ID', 'Class_ID'], 'quiz_audience_class_primary');
                $table->index(['Class_ID', 'Quiz_ID'], 'quiz_audience_class_lookup');
            });
        }
        if (! Schema::hasTable('QUIZ_RUNTIME_STATE')) {
            Schema::create('QUIZ_RUNTIME_STATE', function (Blueprint $table): void {
                if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->engine = 'InnoDB';
                }
                $table->string('Quiz_ID', 191)->primary();
                $table->unsignedBigInteger('Attempt_Generation')->default(0);
                $table->dateTime('Updated_At', 6);
            });
        }
        if (! Schema::hasTable('QUIZ_TOMBSTONE')) {
            Schema::create('QUIZ_TOMBSTONE', function (Blueprint $table): void {
                if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->engine = 'InnoDB';
                }
                $table->string('Quiz_ID', 191)->primary();
                $table->string('Deleted_By', 191);
                $table->dateTime('Deleted_At', 6);
            });
        }

        QuizMultimediaSchemaGuard::assertAudienceTables();

        DB::table('QUIZ_ATTEMPT')
            ->where(function ($query): void {
                $query->whereNull('Class_ID')->orWhere('Class_ID', '');
            })
            ->chunkById(500, function ($attempts): void {
                foreach ($attempts as $attempt) {
                    $classId = DB::table('QUIZ_RESULT')->where('Attempt_ID', $attempt->Attempt_ID)->value('Class_ID')
                        ?: DB::table('QUIZ')->where('Quiz_ID', $attempt->Quiz_ID)->value('Class_ID');
                    if (trim((string) $classId) !== '') {
                        DB::table('QUIZ_ATTEMPT')->where('Attempt_ID', $attempt->Attempt_ID)->update(['Class_ID' => $classId]);
                    }
                }
            }, 'Attempt_ID', 'Attempt_ID');

        DB::table('QUIZ')->select(['Quiz_ID', 'Class_ID', 'Updated_At', 'Created_At'])
            ->orderBy('Quiz_ID')->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $quizId = trim((string) $row->Quiz_ID);
                    $classId = trim((string) $row->Class_ID);
                    $stamp = (string) ($row->Updated_At ?: $row->Created_At ?: now()->format('Y-m-d H:i:s'));
                    if ($quizId === '' || $classId === '') {
                        continue;
                    }
                    DB::table('QUIZ_AUDIENCE_CLASS')->insertOrIgnore([
                        'Quiz_ID' => $quizId,
                        'Class_ID' => $classId,
                        'Created_At' => $stamp,
                    ]);
                    DB::table('QUIZ_RUNTIME_STATE')->insertOrIgnore([
                        'Quiz_ID' => $quizId,
                        'Attempt_Generation' => 0,
                        'Updated_At' => $stamp,
                    ]);
                }
            });

        QuizMultimediaSchemaGuard::assertAudienceTables();
    }

    public function down(): void
    {
        QuizMultimediaSchemaGuard::assertMigrationTarget($this->getConnection());
        Schema::dropIfExists('QUIZ_TOMBSTONE');
        Schema::dropIfExists('QUIZ_RUNTIME_STATE');
        Schema::dropIfExists('QUIZ_AUDIENCE_CLASS');
    }
};
