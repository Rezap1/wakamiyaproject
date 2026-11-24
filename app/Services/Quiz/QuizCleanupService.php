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
        private ?QuizMediaService $media = null,
        private ?QuizAudienceService $audiences = null,
        private ?QuizRuntimeStateService $runtimeState = null,
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
                Cache::lock('quiz_mutation_'.hash('sha256', $quizId), 90)->block(15, function () use (
                    $quiz,
                    $quizId,
                    $questions,
                    $attempts,
                    $now,
                    &$summary,
                ): void {
                    $quizQuestions = $questions->where('Quiz_ID', $quizId);
                    $quizAttempts = $attempts->where('Quiz_ID', $quizId);
                    $maximum = (float) $quizQuestions->sum(fn ($row) => (float) ($row['Point'] ?? 0));

                    foreach ($quizAttempts as $attempt) {
                        $attemptId = trim((string) ($attempt['Attempt_ID'] ?? ''));
                        if ($attemptId === '') {
                            continue;
                        }

                        $hasResult = collect($this->results->fetchAllFresh())->contains('Attempt_ID', $attemptId);
                        if (! $hasResult) {
                            $completed = trim((string) ($attempt['Deadline_At'] ?? $quiz['End_At']));
                            $answers = $this->answerMap((string) ($attempt['Submitted_Answers_JSON'] ?? ''));
                            $raw = (float) $quizQuestions->sum(function ($question) use ($answers) {
                                $selected = strtoupper(trim((string) ($answers[$question['Question_ID']] ?? '')));
                                $correct = strtoupper(trim((string) ($question['Correct_Option'] ?? '')));

                                return $selected !== '' && $selected === $correct ? (float) ($question['Point'] ?? 0) : 0.0;
                            });
                            $this->results->create([
                                'Result_ID' => 'QRS'.strtoupper(substr(hash('sha256', $attemptId), 0, 24)),
                                'Quiz_ID' => $quizId,
                                'Quiz_Title' => $quiz['Title'],
                                'Attempt_ID' => $attemptId,
                                'Student_ID' => $attempt['Student_ID'],
                                'Student_Name' => $attempt['Student_ID'],
                                'Class_ID' => trim((string) ($attempt['Class_ID'] ?? '')) ?: $quiz['Class_ID'],
                                'Teacher_ID' => $quiz['Teacher_ID'],
                                'Raw_Score' => $raw,
                                'Maximum_Score' => $maximum,
                                'Normalized_Score' => $maximum > 0 ? round(($raw / $maximum) * 100, 4) : 0,
                                'Started_At' => $attempt['Started_At'],
                                'Completed_At' => $completed,
                                'Created_At' => $now->format('Y-m-d H:i:s'),
                            ]);
                            $summary['results_created']++;
                        }

                        $summary['attempts'] += $this->attempts->hardDeleteMany([$attemptId]);
                    }

                    // Keep the shared quiz lock through all destructive DB calls.
                    $summary['questions'] += $this->questions->hardDeleteMany($quizQuestions->pluck('Question_ID')->all());
                    ($this->audiences ??= app(QuizAudienceService::class))->delete($quizId);
                    $summary['quizzes'] += $this->quizzes->hardDeleteMany([$quizId]);
                    ($this->runtimeState ??= app(QuizRuntimeStateService::class))->tombstone(
                        $quizId,
                        'SYSTEM',
                        $now->format('Y-m-d H:i:s'),
                    );
                    if ($this->media !== null) {
                        $this->media->deleteUnreferenced($quizQuestions->flatMap(fn ($row) => [$row['Image_Path'] ?? null, $row['Audio_Path'] ?? null])->filter());
                    }
                });
            }

            if ($this->media !== null) {
                $remaining = collect($this->questions->fetchAllFresh())
                    ->flatMap(fn ($row) => [$row['Image_Path'] ?? null, $row['Audio_Path'] ?? null])
                    ->filter();
                $this->media->cleanupOrphans($remaining);
            }

            return $summary;
        });
    }

    /** @return array<string, string> */
    private function answerMap(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $answers = isset($decoded['answers']) && is_array($decoded['answers']) ? $decoded['answers'] : $decoded;

        return collect($answers)->mapWithKeys(function ($answer, $questionId) {
            $answer = strtoupper(trim((string) $answer));

            return in_array($answer, ['A', 'B', 'C', 'D'], true)
                ? [trim((string) $questionId) => $answer]
                : [];
        })->all();
    }
}
