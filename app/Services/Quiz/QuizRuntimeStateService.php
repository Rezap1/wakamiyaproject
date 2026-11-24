<?php

namespace App\Services\Quiz;

use Illuminate\Support\Facades\DB;

class QuizRuntimeStateService
{
    public function ensure(string $quizId, string $stamp): void
    {
        if ($this->hasTable('QUIZ_RUNTIME_STATE')) {
            $this->connection()->table('QUIZ_RUNTIME_STATE')->insertOrIgnore([
                'Quiz_ID' => $quizId,
                'Attempt_Generation' => 0,
                'Updated_At' => $stamp,
            ]);
        }
    }

    public function generation(string $quizId): int
    {
        if (! $this->hasTable('QUIZ_RUNTIME_STATE')) {
            return 0;
        }

        return max(0, (int) ($this->connection()->table('QUIZ_RUNTIME_STATE')->where('Quiz_ID', $quizId)->value('Attempt_Generation') ?? 0));
    }

    public function incrementGeneration(string $quizId, string $stamp): int
    {
        if (! $this->hasTable('QUIZ_RUNTIME_STATE')) {
            return 0;
        }
        $this->ensure($quizId, $stamp);
        $this->connection()->table('QUIZ_RUNTIME_STATE')->where('Quiz_ID', $quizId)->increment('Attempt_Generation', 1, ['Updated_At' => $stamp]);

        return $this->generation($quizId);
    }

    public function tombstone(string $quizId, string $userId, string $stamp): void
    {
        if ($this->hasTable('QUIZ_TOMBSTONE')) {
            $this->connection()->table('QUIZ_TOMBSTONE')->updateOrInsert(
                ['Quiz_ID' => $quizId],
                ['Deleted_By' => $userId, 'Deleted_At' => $stamp],
            );
        }
        if ($this->hasTable('QUIZ_RUNTIME_STATE')) {
            $this->connection()->table('QUIZ_RUNTIME_STATE')->where('Quiz_ID', $quizId)->delete();
        }
    }

    public function isTombstoned(string $quizId): bool
    {
        return $this->hasTable('QUIZ_TOMBSTONE')
            && $this->connection()->table('QUIZ_TOMBSTONE')->where('Quiz_ID', $quizId)->exists();
    }

    private function hasTable(string $table): bool
    {
        try {
            return $this->connection()->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function connection()
    {
        return DB::connection((string) config('wms_runtime.connection'));
    }
}
