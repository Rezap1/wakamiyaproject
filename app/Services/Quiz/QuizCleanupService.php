<?php

namespace App\Services\Quiz;

use App\Interfaces\GoogleSheets\QuizAttemptRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizResultRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class QuizCleanupService
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private QuizQuestionRepositoryInterface $questions,
        private QuizAttemptRepositoryInterface $attempts,
        private QuizResultRepositoryInterface $results,
        private QuizPeriodService $periods,
    ) {}

    public function run(?CarbonImmutable $at = null, ?int $limit = null): array
    {
        $now = $at?->setTimezone($this->periods->timezone()) ?? $this->periods->now();
        $limit ??= (int) config('quiz.cleanup_batch_size', 50);

        return Cache::lock('quiz_cleanup_global', 300)->block(5, function () use ($now, $limit) {
            $quizzes = collect($this->quizzes->fetchAllFresh());
            $questions = collect($this->questions->fetchAllFresh());
            $attempts = collect($this->attempts->fetchAllFresh());
            $results = collect($this->results->fetchAllFresh());
            $eligible = $quizzes->filter(function ($quiz) use ($attempts, $results, $now) {
                $endRaw = trim((string) ($quiz['End_At'] ?? ''));
                if ($endRaw === '') {
                    return false;
                }
                $end = CarbonImmutable::parse($endRaw, $this->periods->timezone());
                if ($now->lessThan($end) || ! $this->periods->isClosed($this->periods->periodFor($end), $now)) {
                    return false;
                }
                $quizAttempts = $attempts->where('Quiz_ID', $quiz['Quiz_ID']);
                if ($quizAttempts->contains(fn ($attempt) => $now->lessThan(CarbonImmutable::parse($attempt['Deadline_At'], $this->periods->timezone())))) {
                    return false;
                }

                return ! $results->where('Quiz_ID', $quiz['Quiz_ID'])->contains(
                    fn ($result) => $now->lessThan(CarbonImmutable::parse($result['Completed_At'], $this->periods->timezone())->addMinutes((int) config('quiz.review_minutes', 4)))
                );
            })->sortBy('End_At')->take(max(1, $limit))->values();

            $summary = ['quizzes' => 0, 'questions' => 0, 'attempts' => 0, 'results_created' => 0];
            foreach ($eligible as $quiz) {
                $quizId = $quiz['Quiz_ID'];
                $quizQuestions = $questions->where('Quiz_ID', $quizId);
                $quizAttempts = $attempts->where('Quiz_ID', $quizId);
                $quizResults = $results->where('Quiz_ID', $quizId);
                $maximum = (float) $quizQuestions->sum(fn ($row) => (float) ($row['Point'] ?? 0));

                foreach ($quizAttempts as $attempt) {
                    if ($quizResults->contains('Attempt_ID', $attempt['Attempt_ID'])) {
                        continue;
                    }
                    $completed = trim((string) ($attempt['Deadline_At'] ?? $quiz['End_At']));
                    $this->results->create([
                        'Result_ID' => 'QRS'.strtoupper(substr(hash('sha256', $attempt['Attempt_ID']), 0, 24)),
                        'Quiz_ID' => $quizId,
                        'Quiz_Title' => $quiz['Title'],
                        'Attempt_ID' => $attempt['Attempt_ID'],
                        'Student_ID' => $attempt['Student_ID'],
                        'Student_Name' => $attempt['Student_ID'],
                        'Class_ID' => $quiz['Class_ID'],
                        'Teacher_ID' => $quiz['Teacher_ID'],
                        'Raw_Score' => 0,
                        'Maximum_Score' => $maximum,
                        'Normalized_Score' => 0,
                        'Started_At' => $attempt['Started_At'],
                        'Completed_At' => $completed,
                        'Created_At' => $now->format('Y-m-d H:i:s'),
                    ]);
                    $summary['results_created']++;
                }

                // Every destructive repository call performs a fresh authoritative
                // read, re-resolves row indexes, and deletes bottom-to-top.
                $summary['questions'] += $this->questions->hardDeleteMany($quizQuestions->pluck('Question_ID')->all());
                $summary['attempts'] += $this->attempts->hardDeleteMany($quizAttempts->pluck('Attempt_ID')->all());
                $summary['quizzes'] += $this->quizzes->hardDeleteMany([$quizId]);
            }

            return $summary;
        });
    }
}
