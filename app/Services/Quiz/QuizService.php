<?php

namespace App\Services\Quiz;

use App\Interfaces\GoogleSheets\QuizAttemptRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizResultRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QuizService
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private QuizQuestionRepositoryInterface $questions,
        private QuizAttemptRepositoryInterface $attempts,
        private QuizResultRepositoryInterface $results,
        private QuizPeriodService $periods,
    ) {}

    public function create(array $data, array $teacher, string $userId): string
    {
        $now = $this->stamp($this->periods->now());
        $quizId = 'QIZ'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 20));
        $publish = ($data['intent'] ?? 'draft') === 'publish';
        $this->quizzes->create([
            'Quiz_ID' => $quizId,
            'Teacher_ID' => $teacher['Teacher_ID'],
            'Class_ID' => $data['Class_ID'],
            'Title' => trim($data['Title']),
            'Start_At' => $this->stamp($this->date($data['Start_At'])),
            'End_At' => $this->stamp($this->date($data['End_At'])),
            'Duration_Minutes' => (int) $data['Duration_Minutes'],
            'Status' => 'DRAFT',
            'Created_At' => $now,
            'Updated_At' => $now,
            'Created_By' => $userId,
            'Updated_By' => $userId,
        ]);
        $this->writeQuestions($quizId, $data['questions'], $now);
        if ($publish) {
            $this->quizzes->update($quizId, ['Status' => 'PUBLISHED', 'Updated_At' => $now, 'Updated_By' => $userId]);
        }

        return $quizId;
    }

    public function update(string $quizId, array $data, array $teacher, string $userId): void
    {
        Cache::lock('quiz_edit_'.hash('sha256', $quizId), 30)->block(10, function () use ($quizId, $data, $teacher, $userId) {
            $quiz = $this->requireQuizFresh($quizId);
            $this->assertTeacherOwns($quiz, $teacher['Teacher_ID']);
            abort_unless($this->isEditable($quiz), 409, 'Kuis tidak dapat diubah setelah aktif atau setelah percobaan dimulai.');
            $now = $this->stamp($this->periods->now());
            $existingIds = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $quizId)->pluck('Question_ID')->all();
            $this->questions->hardDeleteMany($existingIds);
            $this->writeQuestions($quizId, $data['questions'], $now);
            $this->quizzes->update($quizId, [
                'Class_ID' => $data['Class_ID'],
                'Title' => trim($data['Title']),
                'Start_At' => $this->stamp($this->date($data['Start_At'])),
                'End_At' => $this->stamp($this->date($data['End_At'])),
                'Duration_Minutes' => (int) $data['Duration_Minutes'],
                'Status' => ($data['intent'] ?? 'draft') === 'publish' ? 'PUBLISHED' : 'DRAFT',
                'Updated_At' => $now,
                'Updated_By' => $userId,
            ]);
        });
    }

    public function teacherIndex(string $teacherId): array
    {
        $questions = collect($this->questions->fetchAll())->groupBy('Quiz_ID');
        $attempts = collect($this->attempts->fetchAll())->groupBy('Quiz_ID');
        $results = collect($this->results->fetchAll())->groupBy('Quiz_ID');
        $rows = collect($this->quizzes->fetchAll())->where('Teacher_ID', $teacherId)->map(function ($quiz) use ($questions, $attempts, $results) {
            $quiz = (array) $quiz;
            $quiz['Lifecycle'] = $this->lifecycle($quiz);
            $quiz['Question_Count'] = $questions->get($quiz['Quiz_ID'], collect())->count();
            $quiz['Participant_Count'] = $results->get($quiz['Quiz_ID'], collect())->count();
            $quiz['Has_Attempt'] = $attempts->get($quiz['Quiz_ID'], collect())->isNotEmpty();
            $quiz['Editable'] = $this->isEditable($quiz, $quiz['Has_Attempt']);

            return $quiz;
        })->sortByDesc('Start_At')->values();

        return [
            'active' => $rows->where('Lifecycle', 'ACTIVE')->values(),
            'upcoming' => $rows->whereIn('Lifecycle', ['DRAFT', 'SCHEDULED'])->values(),
            'completed' => $rows->whereIn('Lifecycle', ['EXPIRED', 'CLOSED'])->values(),
        ];
    }

    public function teacherQuiz(string $quizId, string $teacherId): array
    {
        $quiz = (array) ($this->quizzes->findById($quizId) ?: abort(404));
        $this->assertTeacherOwns($quiz, $teacherId);
        $quiz['questions'] = collect($this->questions->fetchAll())->where('Quiz_ID', $quizId)->sortBy('Sort_Order')->values();
        $quiz['results'] = collect($this->results->fetchAll())->where('Quiz_ID', $quizId)->sortByDesc('Completed_At')->values();
        $quiz['Editable'] = $this->isEditable($quiz);

        return $quiz;
    }

    public function deleteDraft(string $quizId, string $teacherId): void
    {
        Cache::lock('quiz_edit_'.hash('sha256', $quizId), 30)->block(10, function () use ($quizId, $teacherId) {
            $quiz = $this->requireQuizFresh($quizId);
            $this->assertTeacherOwns($quiz, $teacherId);
            abort_unless(strtoupper((string) ($quiz['Status'] ?? '')) === 'DRAFT' && $this->isEditable($quiz), 409, 'Hanya draft tanpa attempt yang dapat dihapus.');
            $questionIds = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $quizId)->pluck('Question_ID')->all();
            $this->questions->hardDeleteMany($questionIds);
            $this->quizzes->hardDeleteMany([$quizId]);
        });
    }

    public function teacherResults(string $teacherId, ?string $classId = null)
    {
        return collect($this->results->fetchAll())->where('Teacher_ID', $teacherId)
            ->when($classId, fn ($rows) => $rows->where('Class_ID', $classId))
            ->sortByDesc('Completed_At')->values();
    }

    public function studentIndex(array $student): array
    {
        $now = $this->periods->now();
        $attempts = collect($this->attempts->fetchAll())->where('Student_ID', $student['Student_ID'])->keyBy('Quiz_ID');
        $results = collect($this->results->fetchAll())->where('Student_ID', $student['Student_ID'])->keyBy('Quiz_ID');
        $counts = collect($this->questions->fetchAll())->countBy('Quiz_ID');
        $rows = collect($this->quizzes->fetchAll())
            ->where('Class_ID', $student['Class_ID'])->where('Status', 'PUBLISHED')
            ->map(function ($quiz) use ($attempts, $results, $counts, $now) {
                $quiz = (array) $quiz;
                $attempt = $attempts->get($quiz['Quiz_ID']);
                $result = $results->get($quiz['Quiz_ID']);
                $quiz['Lifecycle'] = $this->lifecycle($quiz, $now);
                $quiz['Question_Count'] = (int) $counts->get($quiz['Quiz_ID'], 0);
                $quiz['Attempt_ID'] = $attempt['Attempt_ID'] ?? null;
                $quiz['Result_ID'] = $result['Result_ID'] ?? null;
                $quiz['Normalized_Score'] = $result['Normalized_Score'] ?? null;
                $quiz['Review_Available'] = $result ? $this->reviewAvailable((array) $result, $now) : false;

                return $quiz;
            })->values();

        return [
            'today' => $rows->filter(fn ($row) => in_array($row['Lifecycle'], ['ACTIVE'], true) && empty($row['Result_ID']))->values(),
            'upcoming' => $rows->where('Lifecycle', 'SCHEDULED')->values(),
            'completed' => $rows->filter(fn ($row) => ! empty($row['Result_ID']) && $row['Review_Available'])->values(),
        ];
    }

    public function start(string $quizId, array $student): array
    {
        $attemptId = $this->attemptId($quizId, $student['Student_ID']);

        return Cache::lock('quiz_start_'.hash('sha256', $attemptId), 30)->block(10, function () use ($quizId, $student, $attemptId) {
            $quiz = $this->requireQuizFresh($quizId);
            $this->assertStudentEligible($quiz, $student);
            abort_unless(($quiz['Status'] ?? '') === 'PUBLISHED', 403, 'Kuis belum diterbitkan.');
            $now = $this->periods->now();
            abort_unless($this->lifecycle($quiz, $now) === 'ACTIVE', 403, 'Kuis belum dimulai atau sudah ditutup.');
            $existing = collect($this->attempts->fetchAllFresh())->firstWhere('Attempt_ID', $attemptId);
            if (! $existing) {
                $end = $this->date($quiz['End_At']);
                $deadline = $now->addMinutes((int) $quiz['Duration_Minutes']);
                if ($deadline->greaterThan($end)) {
                    $deadline = $end;
                }
                $stamp = $this->stamp($now);
                $this->attempts->create($existing = [
                    'Attempt_ID' => $attemptId,
                    'Quiz_ID' => $quizId,
                    'Student_ID' => $student['Student_ID'],
                    'Started_At' => $stamp,
                    'Deadline_At' => $this->stamp($deadline),
                    'Status' => 'IN_PROGRESS',
                    'Submitted_Answers_JSON' => '',
                    'Submitted_At' => '',
                    'Created_At' => $stamp,
                    'Updated_At' => $stamp,
                ]);
            }

            return $this->playerPayload($quiz, (array) $existing, $student);
        });
    }

    public function resume(string $attemptId, array $student): array
    {
        $attempt = (array) ($this->attempts->findByIdFresh($attemptId) ?: abort(404));
        abort_unless(trim((string) ($attempt['Student_ID'] ?? '')) === trim($student['Student_ID']), 403);
        $quiz = $this->requireQuizFresh($attempt['Quiz_ID']);
        $this->assertStudentEligible($quiz, $student);
        abort_if(collect($this->results->fetchAllFresh())->contains('Attempt_ID', $attemptId), 409, 'Kuis sudah diselesaikan.');

        return $this->playerPayload($quiz, $attempt, $student);
    }

    public function submit(string $attemptId, array $student, array $answers): array
    {
        return Cache::lock('quiz_submit_'.hash('sha256', $attemptId), 45)->block(15, function () use ($attemptId, $student, $answers) {
            $resultId = $this->resultId($attemptId);
            $existingResult = $this->results->findByIdFresh($resultId);
            if ($existingResult) {
                abort_unless(($existingResult['Student_ID'] ?? '') === $student['Student_ID'], 403);

                return (array) $existingResult;
            }
            $attempt = (array) ($this->attempts->findByIdFresh($attemptId) ?: abort(404));
            abort_unless(($attempt['Student_ID'] ?? '') === $student['Student_ID'], 403);
            $quiz = $this->requireQuizFresh($attempt['Quiz_ID']);
            $this->assertStudentEligible($quiz, $student);
            $questions = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $quiz['Quiz_ID'])->sortBy('Sort_Order')->values();
            abort_if($questions->isEmpty(), 409, 'Kuis tidak memiliki soal.');
            $now = $this->periods->now();
            $onTime = $now->lessThan($this->date($attempt['Deadline_At'])) && $now->lessThan($this->date($quiz['End_At']));
            $accepted = [];
            $maximum = 0.0;
            $raw = 0.0;
            foreach ($questions as $question) {
                $questionId = (string) $question['Question_ID'];
                $point = (float) $question['Point'];
                $maximum += $point;
                $selected = strtoupper(trim((string) ($answers[$questionId] ?? '')));
                if ($onTime && in_array($selected, ['A', 'B', 'C', 'D'], true)) {
                    $accepted[$questionId] = $selected;
                    if ($selected === strtoupper(trim((string) $question['Correct_Option']))) {
                        $raw += $point;
                    }
                }
            }
            $normalized = $maximum > 0 ? round(($raw / $maximum) * 100, 4) : 0.0;
            $stamp = $this->stamp($now);
            $result = [
                'Result_ID' => $resultId,
                'Quiz_ID' => $quiz['Quiz_ID'],
                'Quiz_Title' => $quiz['Title'],
                'Attempt_ID' => $attemptId,
                'Student_ID' => $student['Student_ID'],
                'Student_Name' => $student['Full_Name'] ?? $student['Student_Name'] ?? 'Siswa',
                'Class_ID' => $quiz['Class_ID'],
                'Teacher_ID' => $quiz['Teacher_ID'],
                'Raw_Score' => $raw,
                'Maximum_Score' => $maximum,
                'Normalized_Score' => $normalized,
                'Started_At' => $attempt['Started_At'],
                'Completed_At' => $stamp,
                'Created_At' => $stamp,
            ];
            $this->results->create($result);
            $this->attempts->update($attemptId, [
                'Status' => $onTime ? 'SUBMITTED' : 'EXPIRED',
                'Submitted_Answers_JSON' => json_encode($accepted, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'Submitted_At' => $stamp,
                'Updated_At' => $stamp,
            ]);

            return $result;
        });
    }

    public function review(string $resultId, array $student): array
    {
        $result = (array) ($this->results->findByIdFresh($resultId) ?: abort(404));
        abort_unless(($result['Student_ID'] ?? '') === $student['Student_ID'] && ($result['Class_ID'] ?? '') === $student['Class_ID'], 403);
        abort_unless($this->reviewAvailable($result), 410, 'Masa review 4 menit telah berakhir.');
        $attempt = (array) ($this->attempts->findByIdFresh($result['Attempt_ID']) ?: abort(410));
        abort_unless(($attempt['Student_ID'] ?? '') === $student['Student_ID'], 403);
        $answers = json_decode((string) ($attempt['Submitted_Answers_JSON'] ?? '{}'), true) ?: [];
        $questions = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $result['Quiz_ID'])->sortBy('Sort_Order')->map(fn ($question) => [
            'Question_ID' => $question['Question_ID'],
            'Question_Text' => $question['Question_Text'],
            'Option_A' => $question['Option_A'], 'Option_B' => $question['Option_B'],
            'Option_C' => $question['Option_C'], 'Option_D' => $question['Option_D'],
            'Selected_Option' => $answers[$question['Question_ID']] ?? null,
        ])->values();

        return compact('result', 'questions');
    }

    public function leaderboard(string $classId, ?CarbonImmutable $at = null): array
    {
        $period = $this->periods->periodFor($at);
        $groups = collect($this->results->fetchAll())
            ->where('Class_ID', $classId)
            ->filter(fn ($row) => ! empty($row['Completed_At']) && $this->periods->contains($period, $row['Completed_At']))
            ->groupBy('Student_ID')->map(function ($rows) {
                $first = (array) $rows->first();

                return [
                    'Student_ID' => $first['Student_ID'],
                    'Student_Name' => $first['Student_Name'] ?: 'Siswa',
                    'Points' => round((float) $rows->sum(fn ($row) => (float) ($row['Normalized_Score'] ?? 0)), 4),
                ];
            })->sort(function ($a, $b) {
                return $b['Points'] <=> $a['Points'] ?: strcasecmp($a['Student_Name'], $b['Student_Name']);
            })->values();
        $rank = 0;
        $position = 0;
        $previous = null;
        $ranked = $groups->map(function ($row) use (&$rank, &$position, &$previous) {
            $position++;
            if ($previous === null || abs($row['Points'] - $previous) > 0.00005) {
                $rank = $position;
            }
            $previous = $row['Points'];
            $row['Rank'] = $rank;

            return $row;
        });

        return ['period' => $period, 'entries' => $ranked];
    }

    public function lifecycle(array $quiz, ?CarbonImmutable $now = null): string
    {
        if (strtoupper(trim((string) ($quiz['Status'] ?? 'DRAFT'))) === 'DRAFT') {
            return 'DRAFT';
        }
        $now ??= $this->periods->now();
        if ($now->lessThan($this->date($quiz['Start_At']))) {
            return 'SCHEDULED';
        }
        if ($now->greaterThanOrEqualTo($this->date($quiz['End_At']))) {
            return 'EXPIRED';
        }

        return 'ACTIVE';
    }

    public function reviewAvailable(array $result, ?CarbonImmutable $now = null): bool
    {
        $now ??= $this->periods->now();

        return $now->lessThan($this->date($result['Completed_At'])->addMinutes((int) config('quiz.review_minutes', 4)));
    }

    private function playerPayload(array $quiz, array $attempt, array $student): array
    {
        abort_unless(($attempt['Student_ID'] ?? '') === $student['Student_ID'], 403);
        $questions = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $quiz['Quiz_ID'])->sortBy('Sort_Order')->map(fn ($question) => [
            'Question_ID' => $question['Question_ID'],
            'Question_Text' => $question['Question_Text'],
            'Option_A' => $question['Option_A'], 'Option_B' => $question['Option_B'],
            'Option_C' => $question['Option_C'], 'Option_D' => $question['Option_D'],
            'Sort_Order' => $question['Sort_Order'],
        ])->values();
        abort_if($questions->isEmpty(), 409, 'Kuis tidak memiliki soal.');

        return ['quiz' => $quiz, 'attempt' => $attempt, 'questions' => $questions];
    }

    private function writeQuestions(string $quizId, array $questions, string $now): void
    {
        foreach (array_values($questions) as $index => $question) {
            $this->questions->create([
                'Question_ID' => 'QQN'.strtoupper(substr(hash('sha256', $quizId.'|'.$index), 0, 20)),
                'Quiz_ID' => $quizId,
                'Question_Text' => trim($question['Question_Text']),
                'Option_A' => trim($question['Option_A']), 'Option_B' => trim($question['Option_B']),
                'Option_C' => trim($question['Option_C']), 'Option_D' => trim($question['Option_D']),
                'Correct_Option' => strtoupper($question['Correct_Option']),
                'Point' => (float) $question['Point'],
                'Sort_Order' => $index + 1,
                'Created_At' => $now, 'Updated_At' => $now,
            ]);
        }
    }

    private function isEditable(array $quiz, ?bool $hasAttempt = null): bool
    {
        $hasAttempt ??= collect($this->attempts->fetchAll())->contains('Quiz_ID', $quiz['Quiz_ID']);

        return ! $hasAttempt && $this->periods->now()->lessThan($this->date($quiz['Start_At'])) && $this->lifecycle($quiz) !== 'ACTIVE';
    }

    private function requireQuizFresh(string $id): array
    {
        return (array) ($this->quizzes->findByIdFresh($id) ?: abort(404, 'Kuis tidak ditemukan.'));
    }

    private function assertTeacherOwns(array $quiz, string $teacherId): void
    {
        abort_unless(trim((string) ($quiz['Teacher_ID'] ?? '')) === trim($teacherId), 403);
    }

    private function assertStudentEligible(array $quiz, array $student): void
    {
        abort_unless(trim((string) ($quiz['Class_ID'] ?? '')) === trim((string) ($student['Class_ID'] ?? '')), 403);
    }

    private function attemptId(string $quizId, string $studentId): string
    {
        return 'QAT'.strtoupper(substr(hash('sha256', $quizId.'|'.$studentId), 0, 24));
    }

    private function resultId(string $attemptId): string
    {
        return 'QRS'.strtoupper(substr(hash('sha256', $attemptId), 0, 24));
    }

    private function date(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, $this->periods->timezone());
    }

    private function stamp(CarbonImmutable $value): string
    {
        return $value->setTimezone($this->periods->timezone())->format('Y-m-d H:i:s');
    }
}
