<?php

namespace App\Services\Quiz;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuizAudienceService
{
    private ?Collection $audienceCache = null;

    /** @return list<string> */
    public function classIds(array $quiz): array
    {
        $quizId = trim((string) ($quiz['Quiz_ID'] ?? ''));
        if ($quizId !== '' && $this->available()) {
            $ids = $this->allByQuiz()->get($quizId, []);
            if ($ids !== []) {
                return $ids;
            }
        }

        $legacy = trim((string) ($quiz['Class_ID'] ?? ''));

        return $legacy === '' ? [] : [$legacy];
    }

    /** @return Collection<string,list<string>> */
    public function allByQuiz(): Collection
    {
        if ($this->audienceCache !== null) {
            return $this->audienceCache;
        }
        if (! $this->available()) {
            return collect();
        }

        return $this->audienceCache = $this->connection()->table('QUIZ_AUDIENCE_CLASS')->orderBy('Class_ID')->get()
            ->groupBy('Quiz_ID')->map(fn ($rows) => $rows->pluck('Class_ID')->map(fn ($id) => trim((string) $id))->values()->all());
    }

    /** @param list<string> $classIds */
    public function replace(string $quizId, array $classIds, string $createdAt): void
    {
        $classIds = collect($classIds)->map(fn ($id) => trim((string) $id))->filter()->unique()->values()->all();
        if ($classIds === []) {
            throw new \InvalidArgumentException('Minimal satu kelas audience diperlukan.');
        }
        if (! $this->available()) {
            return;
        }

        $query = $this->connection()->table('QUIZ_AUDIENCE_CLASS')->where('Quiz_ID', $quizId);
        $query->whereNotIn('Class_ID', $classIds)->delete();
        foreach ($classIds as $classId) {
            $this->connection()->table('QUIZ_AUDIENCE_CLASS')->insertOrIgnore([
                'Quiz_ID' => $quizId,
                'Class_ID' => $classId,
                'Created_At' => $createdAt,
            ]);
        }
        $this->audienceCache = null;
    }

    public function delete(string $quizId): void
    {
        if ($this->available()) {
            $this->connection()->table('QUIZ_AUDIENCE_CLASS')->where('Quiz_ID', $quizId)->delete();
            $this->audienceCache = null;
        }
    }

    public function includes(array $quiz, string $classId): bool
    {
        return in_array(trim($classId), $this->classIds($quiz), true);
    }

    private function available(): bool
    {
        try {
            return $this->connection()->getSchemaBuilder()->hasTable('QUIZ_AUDIENCE_CLASS');
        } catch (\Throwable) {
            return false;
        }
    }

    private function connection()
    {
        return DB::connection((string) config('wms_runtime.connection'));
    }
}
