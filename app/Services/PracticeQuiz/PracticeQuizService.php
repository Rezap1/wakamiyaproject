<?php

namespace App\Services\PracticeQuiz;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PracticeQuizService
{
    public function __construct(private PracticeQuizMediaService $media) {}

    public function teacherIndex(string $teacherId, string $search = ''): LengthAwarePaginator
    {
        return $this->db()->table('PRACTICE_QUIZ')
            ->where('Teacher_ID', trim($teacherId))->whereNull('Deleted_At')
            ->when(trim($search) !== '', fn ($q) => $q->where('Title', 'like', '%'.trim($search).'%'))
            ->orderByDesc('Updated_At')->paginate(12)->withQueryString();
    }

    public function teacherQuiz(string $id, string $teacherId): array
    {
        $quiz = $this->quiz($id);
        abort_unless(hash_equals((string) $quiz->Teacher_ID, trim($teacherId)), 403);
        abort_if($quiz->Deleted_At !== null, 404);

        return $this->quizPayload($quiz);
    }

    public function save(?string $id, array $data, string $teacherId, string $userId): string
    {
        $creating = $id === null || trim($id) === '';
        $id = $creating ? (string) Str::uuid() : trim((string) $id);
        $operation = $creating ? 'CREATE' : 'SAVE';
        $lock = Cache::lock('practice_quiz_mutation_'.hash('sha256', $id), 120);
        abort_unless($lock->get(), 423, 'Latihan sedang diproses oleh permintaan lain.');
        $plan = null;
        try {
            $existing = $this->db()->table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', $id)->first();
            if ($existing) {
                abort_unless(hash_equals((string) $existing->Teacher_ID, trim($teacherId)), 403);
                abort_if($existing->Deleted_At !== null, 410);
            }
            $payloadHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $prior = $this->idempotentResult($data['Idempotency_Key'], $userId, $operation, $payloadHash);
            if ($prior !== null) {
                return $prior;
            }

            $sourceRevisionId = $existing ? ($existing->Draft_Revision_ID ?: $existing->Current_Revision_ID) : null;
            $currentQuestions = $sourceRevisionId
                ? $this->questions((string) $sourceRevisionId)->map(fn ($r) => (array) $r)->all()
                : [];
            $revisionId = (string) Str::uuid();
            $plan = $this->media->prepare($id, $revisionId, $data['questions'], $currentQuestions, $userId);
            $now = now()->format('Y-m-d H:i:s.u');
            $status = $data['intent'] === 'publish' ? 'PUBLISHED' : 'DRAFT';

            $this->db()->transaction(function () use ($id, $revisionId, $data, $teacherId, $userId, $existing, $plan, $now, $status, $payloadHash, $operation): void {
                $revisionNumber = ((int) $this->db()->table('PRACTICE_QUIZ_REVISION')->where('Practice_Quiz_ID', $id)->lockForUpdate()->max('Revision_Number')) + 1;
                $keepsPublishedRevision = $existing && $existing->Status === 'PUBLISHED' && $status === 'DRAFT';
                $base = [
                    'Teacher_ID' => trim($teacherId), 'Title' => trim($data['Title']),
                    'Description' => trim((string) ($data['Description'] ?? '')) ?: null,
                    'Instructions' => trim((string) ($data['Instructions'] ?? '')) ?: null,
                    'Duration_Minutes' => (int) $data['Duration_Minutes'], 'Status' => $status,
                    'Current_Revision_ID' => $keepsPublishedRevision ? $existing->Current_Revision_ID : $revisionId,
                    'Draft_Revision_ID' => $status === 'DRAFT' ? $revisionId : null,
                    'Updated_At' => $now,
                    'Published_At' => $status === 'PUBLISHED' ? ($existing->Published_At ?? $now) : ($existing->Published_At ?? null),
                ];
                if ($existing) {
                    if ($keepsPublishedRevision) {
                        $base['Status'] = 'PUBLISHED';
                        foreach (['Title', 'Description', 'Instructions', 'Duration_Minutes'] as $field) {
                            $base[$field] = $existing->{$field};
                        }
                    }
                    $this->db()->table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', $id)->update($base);
                } else {
                    $this->db()->table('PRACTICE_QUIZ')->insert($base + ['Practice_Quiz_ID' => $id, 'Created_At' => $now]);
                }
                $this->db()->table('PRACTICE_QUIZ_REVISION')->insert([
                    'Revision_ID' => $revisionId, 'Practice_Quiz_ID' => $id,
                    'Revision_Number' => $revisionNumber, 'Title' => trim($data['Title']),
                    'Description' => trim((string) ($data['Description'] ?? '')) ?: null,
                    'Instructions' => trim((string) ($data['Instructions'] ?? '')) ?: null,
                    'Duration_Minutes' => (int) $data['Duration_Minutes'],
                    'Question_Count' => count($plan['questions']), 'Status' => $status,
                    'Created_At' => $now, 'Created_By' => trim($userId),
                ]);
                foreach ($plan['questions'] as $index => $question) {
                    $this->db()->table('PRACTICE_QUESTION_REVISION')->insert([
                        'Question_ID' => (string) Str::uuid(), 'Revision_ID' => $revisionId, 'Sort_Order' => $index + 1,
                        'Question_Text' => trim((string) $question['Question_Text']),
                        'Option_A' => trim((string) $question['Option_A']), 'Option_B' => trim((string) $question['Option_B']),
                        'Option_C' => trim((string) $question['Option_C']), 'Option_D' => trim((string) $question['Option_D']),
                        'Correct_Option' => strtoupper(trim((string) $question['Correct_Option'])), 'Point' => (float) $question['Point'],
                        'Explanation' => trim((string) ($question['Explanation'] ?? '')) ?: null,
                        'Image_Path' => $question['Image_Path'] ?? null, 'Image_Mime' => $question['Image_Mime'] ?? null,
                        'Image_Size_Bytes' => $question['Image_Size_Bytes'] ?? null, 'Audio_Path' => $question['Audio_Path'] ?? null,
                        'Audio_Mime' => $question['Audio_Mime'] ?? null, 'Audio_Size_Bytes' => $question['Audio_Size_Bytes'] ?? null,
                        'Audio_Duration_Milliseconds' => $question['Audio_Duration_Milliseconds'] ?? null, 'Created_At' => $now,
                    ]);
                }
                $this->recordOperation($data['Idempotency_Key'], $userId, $operation, $id, $payloadHash, $id, $now);
            }, 5);
            $this->media->commit($plan);

            return $id;
        } catch (\Throwable $exception) {
            if ($plan !== null) {
                $this->media->rollback($plan);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function archive(string $id, string $teacherId, string $userId): void
    {
        $lock = Cache::lock('practice_quiz_mutation_'.hash('sha256', trim($id)), 120);
        abort_unless($lock->get(), 423, 'Latihan sedang diproses oleh permintaan lain.');
        try {
            $this->db()->transaction(function () use ($id, $teacherId, $userId): void {
                $quiz = $this->db()->table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', trim($id))->lockForUpdate()->first();
                abort_unless($quiz && $quiz->Deleted_At === null, 404);
                abort_unless(hash_equals((string) $quiz->Teacher_ID, trim($teacherId)), 403);
                $now = now()->format('Y-m-d H:i:s.u');
                $this->db()->table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', trim($id))->update([
                    'Status' => 'ARCHIVED', 'Deleted_At' => $now,
                    'Deleted_By' => trim($userId), 'Updated_At' => $now,
                ]);
            }, 5);
        } finally {
            $lock->release();
        }
    }

    public function studentCatalog(array $student, string $search = ''): LengthAwarePaginator
    {
        $studentId = trim((string) $student['Student_ID']);
        $query = $this->db()->table('PRACTICE_QUIZ as q')
            ->join('PRACTICE_QUIZ_REVISION as r', 'r.Revision_ID', '=', 'q.Current_Revision_ID')
            ->leftJoin('MASTER_TEACHER as t', 't.Teacher_ID', '=', 'q.Teacher_ID')
            ->where(function ($query) use ($studentId): void {
                $query->where(function ($published): void {
                    $published->where('q.Status', 'PUBLISHED')->whereNull('q.Deleted_At');
                })->orWhereExists(fn ($active) => $active->from('PRACTICE_ATTEMPT as active_attempt')
                    ->selectRaw('1')->whereColumn('active_attempt.Practice_Quiz_ID', 'q.Practice_Quiz_ID')
                    ->where('active_attempt.Student_ID', $studentId)->where('active_attempt.Status', 'IN_PROGRESS'));
            })
            ->when(trim($search) !== '', fn ($q) => $q->where('q.Title', 'like', '%'.trim($search).'%'))
            ->select(['q.*', 'r.Question_Count', 't.Full_Name as Teacher_Name'])
            ->selectSub(fn ($s) => $s->from('PRACTICE_RESULT')->selectRaw('COUNT(*)')->whereColumn('Practice_Quiz_ID', 'q.Practice_Quiz_ID')->where('Student_ID', $studentId), 'Attempt_Count')
            ->selectSub(fn ($s) => $s->from('PRACTICE_RESULT')->select('Normalized_Score')->whereColumn('Practice_Quiz_ID', 'q.Practice_Quiz_ID')->where('Student_ID', $studentId)->orderByDesc('Completed_At')->limit(1), 'Last_Score')
            ->selectSub(fn ($s) => $s->from('PRACTICE_RESULT')->selectRaw('MAX(Normalized_Score)')->whereColumn('Practice_Quiz_ID', 'q.Practice_Quiz_ID')->where('Student_ID', $studentId), 'Best_Score')
            ->selectSub(fn ($s) => $s->from('PRACTICE_ATTEMPT')->select('Attempt_ID')->whereColumn('Practice_Quiz_ID', 'q.Practice_Quiz_ID')->where('Student_ID', $studentId)->where('Status', 'IN_PROGRESS')->limit(1), 'Active_Attempt_ID')
            ->orderByDesc('q.Published_At');

        return $query->paginate((int) config('practice_quiz.catalog_page_size', 12))->withQueryString();
    }

    public function start(string $quizId, array $student): array
    {
        $studentId = trim((string) $student['Student_ID']);
        $lock = Cache::lock('practice_quiz_start_'.hash('sha256', $quizId.'|'.$studentId), 30);
        abort_unless($lock->get(), 423, 'Percobaan sedang dipersiapkan.');
        try {
            return $this->db()->transaction(function () use ($quizId, $student, $studentId): array {
                $quiz = $this->db()->table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', $quizId)->lockForUpdate()->first();
                abort_unless($quiz, 404);
                $active = $this->db()->table('PRACTICE_ATTEMPT')->where('Practice_Quiz_ID', $quizId)->where('Student_ID', $studentId)->where('Status', 'IN_PROGRESS')->lockForUpdate()->first();
                if ($active) {
                    if (CarbonImmutable::parse($active->Deadline_At)->isFuture()) {
                        return (array) $active;
                    }
                    $this->finalizeLocked($active);
                }
                abort_unless($quiz->Status === 'PUBLISHED' && $quiz->Deleted_At === null, 404);
                $number = ((int) $this->db()->table('PRACTICE_ATTEMPT')->where('Practice_Quiz_ID', $quizId)->where('Student_ID', $studentId)->max('Attempt_Number')) + 1;
                $started = CarbonImmutable::now();
                $revision = $this->db()->table('PRACTICE_QUIZ_REVISION')->where('Revision_ID', $quiz->Current_Revision_ID)->first();
                abort_unless($revision && $revision->Status === 'PUBLISHED', 409, 'Revision latihan belum diterbitkan.');
                $attempt = [
                    'Attempt_ID' => (string) Str::uuid(), 'Practice_Quiz_ID' => $quizId,
                    'Revision_ID' => $revision->Revision_ID, 'Student_ID' => $studentId,
                    'Class_ID' => trim((string) ($student['Class_ID'] ?? '')) ?: null, 'Attempt_Number' => $number,
                    'Status' => 'IN_PROGRESS', 'Active_Key' => $quizId.'|'.$studentId, 'Answer_Version' => 0,
                    'Started_At' => $started->format('Y-m-d H:i:s.u'),
                    'Deadline_At' => $started->addMinutes((int) $revision->Duration_Minutes)->format('Y-m-d H:i:s.u'),
                    'Completed_At' => null, 'Created_At' => $started->format('Y-m-d H:i:s.u'), 'Updated_At' => $started->format('Y-m-d H:i:s.u'),
                ];
                $this->db()->table('PRACTICE_ATTEMPT')->insert($attempt);

                return $attempt;
            }, 5);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                $active = $this->db()->table('PRACTICE_ATTEMPT')->where('Active_Key', $quizId.'|'.$studentId)->first();
                if ($active) {
                    return (array) $active;
                }
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function resume(string $attemptId, array $student): array
    {
        $attempt = $this->ownedAttempt($attemptId, (string) $student['Student_ID']);
        if ($attempt->Status !== 'IN_PROGRESS') {
            $result = $this->db()->table('PRACTICE_RESULT')->where('Attempt_ID', $attemptId)->first();

            return ['finalized_result' => (array) $result];
        }
        if (! CarbonImmutable::parse($attempt->Deadline_At)->isFuture()) {
            return ['finalized_result' => $this->submit($attemptId, $student, [], null)];
        }
        $revision = $this->db()->table('PRACTICE_QUIZ_REVISION')->where('Revision_ID', $attempt->Revision_ID)->first();
        $questions = $this->questions($attempt->Revision_ID)->map(function ($question): array {
            $row = (array) $question;
            unset($row['Correct_Option'], $row['Point'], $row['Explanation']);

            return $row;
        });
        $answers = $this->db()->table('PRACTICE_ANSWER')->where('Attempt_ID', $attemptId)->pluck('Selected_Option', 'Question_ID');

        return compact('attempt', 'revision', 'questions', 'answers') + ['server_now' => now()->toIso8601String()];
    }

    public function autosave(string $attemptId, array $student, array $answers, int $version): array
    {
        return $this->db()->transaction(function () use ($attemptId, $student, $answers, $version): array {
            $attempt = $this->ownedAttempt($attemptId, (string) $student['Student_ID'], true);
            abort_unless($attempt->Status === 'IN_PROGRESS', 409, 'Percobaan telah selesai.');
            if (! CarbonImmutable::parse($attempt->Deadline_At)->isFuture()) {
                $result = $this->finalizeLocked($attempt);

                return ['finalized' => true, 'result_id' => $result['Result_ID']];
            }
            if ($version <= (int) $attempt->Answer_Version) {
                return ['saved' => false, 'conflict' => true, 'version' => (int) $attempt->Answer_Version];
            }
            $validIds = $this->questions($attempt->Revision_ID)->pluck('Question_ID')->map(fn ($id) => (string) $id);
            foreach ($answers as $questionId => $option) {
                abort_unless($validIds->contains((string) $questionId), 422, 'Soal tidak valid.');
                $identity = ['Attempt_ID' => $attemptId, 'Question_ID' => (string) $questionId];
                $stamp = now()->format('Y-m-d H:i:s.u');
                $this->db()->table('PRACTICE_ANSWER')->insertOrIgnore($identity + ['Answer_ID' => (string) Str::uuid(), 'Selected_Option' => strtoupper((string) $option), 'Answer_Version' => $version, 'Saved_At' => $stamp]);
                $this->db()->table('PRACTICE_ANSWER')->where($identity)->update(['Selected_Option' => strtoupper((string) $option), 'Answer_Version' => $version, 'Saved_At' => $stamp]);
            }
            $this->db()->table('PRACTICE_ATTEMPT')->where('Attempt_ID', $attemptId)->update(['Answer_Version' => $version, 'Updated_At' => now()->format('Y-m-d H:i:s.u')]);

            return ['saved' => true, 'conflict' => false, 'version' => $version];
        }, 5);
    }

    public function submit(string $attemptId, array $student, array $answers = [], ?int $answerVersion = null): array
    {
        $lock = Cache::lock('practice_quiz_submit_'.hash('sha256', $attemptId), 30);
        abort_unless($lock->get(), 423, 'Percobaan sedang dikirim.');
        try {
            return $this->db()->transaction(function () use ($attemptId, $student, $answers, $answerVersion): array {
                $attempt = $this->ownedAttempt($attemptId, (string) $student['Student_ID'], true);
                $existing = $this->db()->table('PRACTICE_RESULT')->where('Attempt_ID', $attemptId)->first();
                if ($existing) {
                    return (array) $existing;
                }
                if ($attempt->Status !== 'IN_PROGRESS') {
                    abort(409, 'Hasil percobaan tidak ditemukan.');
                }
                if ($answers !== [] && $answerVersion !== null && $answerVersion > (int) $attempt->Answer_Version && CarbonImmutable::parse($attempt->Deadline_At)->isFuture()) {
                    $valid = $this->questions($attempt->Revision_ID)->pluck('Question_ID')->map(fn ($id) => (string) $id);
                    foreach ($answers as $questionId => $option) {
                        abort_unless($valid->contains((string) $questionId), 422);
                        $identity = ['Attempt_ID' => $attemptId, 'Question_ID' => (string) $questionId];
                        $stamp = now()->format('Y-m-d H:i:s.u');
                        $this->db()->table('PRACTICE_ANSWER')->insertOrIgnore($identity + ['Answer_ID' => (string) Str::uuid(), 'Selected_Option' => strtoupper((string) $option), 'Answer_Version' => $answerVersion, 'Saved_At' => $stamp]);
                        $this->db()->table('PRACTICE_ANSWER')->where($identity)->update(['Selected_Option' => strtoupper((string) $option), 'Answer_Version' => $answerVersion, 'Saved_At' => $stamp]);
                    }
                    $attempt->Answer_Version = $answerVersion;
                }

                return $this->finalizeLocked($attempt);
            }, 5);
        } finally {
            $lock->release();
        }
    }

    public function review(string $resultId, array $student): array
    {
        $result = $this->db()->table('PRACTICE_RESULT')->where('Result_ID', $resultId)->where('Student_ID', trim((string) $student['Student_ID']))->first();
        abort_unless($result, 404);
        $questions = $this->questions($result->Revision_ID);
        $answers = $this->db()->table('PRACTICE_ANSWER')->where('Attempt_ID', $result->Attempt_ID)->pluck('Selected_Option', 'Question_ID');
        $previous = $this->db()->table('PRACTICE_RESULT')->where('Practice_Quiz_ID', $result->Practice_Quiz_ID)->where('Student_ID', $result->Student_ID)->where('Attempt_Number', '<', $result->Attempt_Number)->orderByDesc('Attempt_Number')->value('Normalized_Score');
        $best = $this->db()->table('PRACTICE_RESULT')->where('Practice_Quiz_ID', $result->Practice_Quiz_ID)->where('Student_ID', $result->Student_ID)->max('Normalized_Score');
        $canRestart = $this->db()->table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', $result->Practice_Quiz_ID)
            ->where('Status', 'PUBLISHED')->whereNull('Deleted_At')->exists();

        return compact('result', 'questions', 'answers', 'previous', 'best', 'canRestart');
    }

    public function history(array $student, array $filters = [], ?string $quizId = null): array
    {
        $studentId = trim((string) $student['Student_ID']);
        $base = $this->historyBaseQuery($studentId, $filters, $quizId);
        $aggregate = (clone $base)->selectRaw('COUNT(*) AS result_count, MIN(r.Normalized_Score) AS lowest_score, MAX(r.Normalized_Score) AS best_score, AVG(r.Normalized_Score) AS average_score')->first();
        $last = (clone $base)->orderByDesc('r.Completed_At')->value('r.Normalized_Score');
        $chartLimit = max(10, (int) config('practice_quiz.chart_max_points', 100));
        $chartRows = (clone $base)->select(['r.Attempt_Number', 'r.Normalized_Score', 'r.Quiz_Title', 'r.Completed_At'])
            ->orderByDesc('r.Completed_At')->limit($chartLimit)->get()->reverse()->values();
        $history = (clone $base)->select(['r.*', 'a.Status as Attempt_Status'])
            ->orderByDesc('r.Completed_At')->paginate((int) config('practice_quiz.history_page_size', 15))->withQueryString();
        $count = (int) ($aggregate->result_count ?? 0);
        $stats = [
            'count' => $count, 'last' => $last === null ? null : (float) $last,
            'best' => $count === 0 ? null : (float) $aggregate->best_score,
            'lowest' => $count === 0 ? null : (float) $aggregate->lowest_score,
            'average' => $count === 0 ? null : round((float) $aggregate->average_score, 2),
            'chart' => $chartRows->map(fn ($row) => ['attempt' => $row->Attempt_Number, 'score' => (float) $row->Normalized_Score, 'quiz' => $row->Quiz_Title])->values(),
            'chart_truncated' => $count > $chartRows->count(),
        ];
        $quizzes = $this->db()->table('PRACTICE_RESULT')->where('Student_ID', $studentId)->select(['Practice_Quiz_ID', 'Quiz_Title'])->distinct()->orderBy('Quiz_Title')->get();

        return compact('history', 'stats', 'quizzes', 'filters', 'quizId');
    }

    public function pdfData(array $student, array $filters = [], ?string $quizId = null): array
    {
        $payload = $this->history($student, $filters, $quizId);
        $payload['rows'] = $this->historyQuery((string) $student['Student_ID'], $filters, $quizId)->orderBy('r.Completed_At')->get();
        abort_if($quizId !== null && $payload['rows']->isEmpty(), 404);
        $payload['student'] = $student;
        $payload['generatedAt'] = now();
        $payload['title'] = $quizId && $payload['rows']->isNotEmpty() ? $payload['rows']->first()->Quiz_Title : 'Rekap Seluruh Kuis Latihan';

        return $payload;
    }

    public function mediaForTeacher(string $quizId, string $questionId, string $type, string $teacherId): array
    {
        $this->teacherQuiz($quizId, $teacherId);

        return $this->questionMedia($questionId, $type, fn ($q) => $q->where('r.Practice_Quiz_ID', $quizId));
    }

    public function mediaForAttempt(string $attemptId, string $questionId, string $type, array $student): array
    {
        $attempt = $this->ownedAttempt($attemptId, (string) $student['Student_ID']);

        return $this->questionMedia($questionId, $type, fn ($q) => $q->where('q.Revision_ID', $attempt->Revision_ID));
    }

    public function mediaForResult(string $resultId, string $questionId, string $type, array $student): array
    {
        $result = $this->db()->table('PRACTICE_RESULT')->where('Result_ID', $resultId)->where('Student_ID', trim((string) $student['Student_ID']))->first();
        abort_unless($result, 404);

        return $this->questionMedia($questionId, $type, fn ($q) => $q->where('q.Revision_ID', $result->Revision_ID));
    }

    public function finalizeExpired(int $limit = 100): int
    {
        $ids = $this->db()->table('PRACTICE_ATTEMPT')->where('Status', 'IN_PROGRESS')->where('Deadline_At', '<=', now())->orderBy('Deadline_At')->limit($limit)->pluck('Attempt_ID');
        $count = 0;
        foreach ($ids as $id) {
            $attempt = $this->db()->table('PRACTICE_ATTEMPT')->where('Attempt_ID', $id)->first();
            if (! $attempt) {
                continue;
            }
            $this->submit((string) $id, ['Student_ID' => $attempt->Student_ID]);
            $count++;
        }

        return $count;
    }

    private function finalizeLocked(object $attempt): array
    {
        $existing = $this->db()->table('PRACTICE_RESULT')->where('Attempt_ID', $attempt->Attempt_ID)->first();
        if ($existing) {
            return (array) $existing;
        }
        $questions = $this->questions($attempt->Revision_ID);
        $answers = $this->db()->table('PRACTICE_ANSWER')->where('Attempt_ID', $attempt->Attempt_ID)->pluck('Selected_Option', 'Question_ID');
        $raw = 0.0;
        $max = 0.0;
        $correct = 0;
        $incorrect = 0;
        $unanswered = 0;
        foreach ($questions as $question) {
            $point = (float) $question->Point;
            $max += $point;
            $selected = $answers[(string) $question->Question_ID] ?? null;
            if ($selected === null || $selected === '') {
                $unanswered++;
            } elseif (hash_equals((string) $question->Correct_Option, (string) $selected)) {
                $correct++;
                $raw += $point;
            } else {
                $incorrect++;
            }
        }
        $completed = CarbonImmutable::now();
        $quiz = $this->db()->table('PRACTICE_QUIZ_REVISION')->where('Revision_ID', $attempt->Revision_ID)->first();
        $result = [
            'Result_ID' => (string) Str::uuid(), 'Attempt_ID' => $attempt->Attempt_ID,
            'Practice_Quiz_ID' => $attempt->Practice_Quiz_ID, 'Revision_ID' => $attempt->Revision_ID,
            'Student_ID' => $attempt->Student_ID, 'Class_ID' => $attempt->Class_ID, 'Quiz_Title' => $quiz->Title,
            'Attempt_Number' => $attempt->Attempt_Number, 'Raw_Score' => $raw, 'Max_Score' => $max,
            'Normalized_Score' => $max > 0 ? round($raw / $max * 100, 2) : 0,
            'Correct_Count' => $correct, 'Incorrect_Count' => $incorrect, 'Unanswered_Count' => $unanswered,
            'Started_At' => $attempt->Started_At, 'Completed_At' => $completed->format('Y-m-d H:i:s.u'),
            'Duration_Seconds' => max(0, min(CarbonImmutable::parse($attempt->Started_At)->diffInSeconds($completed), CarbonImmutable::parse($attempt->Started_At)->diffInSeconds(CarbonImmutable::parse($attempt->Deadline_At)))),
            'Created_At' => $completed->format('Y-m-d H:i:s.u'),
        ];
        $this->db()->table('PRACTICE_RESULT')->insert($result);
        $this->db()->table('PRACTICE_ATTEMPT')->where('Attempt_ID', $attempt->Attempt_ID)->update([
            'Status' => CarbonImmutable::parse($attempt->Deadline_At)->isFuture() ? 'COMPLETED' : 'EXPIRED',
            'Active_Key' => null, 'Completed_At' => $result['Completed_At'],
            'Answer_Version' => (int) $attempt->Answer_Version, 'Updated_At' => $result['Completed_At'],
        ]);

        return $result;
    }

    private function historyQuery(string $studentId, array $filters, ?string $quizId)
    {
        return $this->historyBaseQuery($studentId, $filters, $quizId)
            ->select(['r.*', 'a.Status as Attempt_Status']);
    }

    private function historyBaseQuery(string $studentId, array $filters, ?string $quizId)
    {
        return $this->db()->table('PRACTICE_RESULT as r')
            ->join('PRACTICE_ATTEMPT as a', 'a.Attempt_ID', '=', 'r.Attempt_ID')
            ->where('r.Student_ID', trim($studentId))
            ->when($quizId, fn ($q) => $q->where('r.Practice_Quiz_ID', $quizId))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('r.Completed_At', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('r.Completed_At', '<=', $date));
    }

    private function questionMedia(string $questionId, string $type, callable $scope): array
    {
        abort_unless(in_array($type, ['image', 'audio'], true), 404);
        $query = $this->db()->table('PRACTICE_QUESTION_REVISION as q')->join('PRACTICE_QUIZ_REVISION as r', 'r.Revision_ID', '=', 'q.Revision_ID')->where('q.Question_ID', $questionId);
        $scope($query);
        $question = $query->first();
        abort_unless($question, 404);
        $prefix = ucfirst($type);
        $path = $question->{$prefix.'_Path'};
        abort_unless($path, 404);

        return ['path' => $path, 'mime' => $question->{$prefix.'_Mime'}];
    }

    private function quizPayload(object $quiz): array
    {
        $revisionId = $quiz->Draft_Revision_ID ?: $quiz->Current_Revision_ID;
        $revision = $this->db()->table('PRACTICE_QUIZ_REVISION')->where('Revision_ID', $revisionId)->first();

        return ['quiz' => (array) $quiz, 'revision' => $revision ? (array) $revision : null, 'questions' => $revision ? $this->questions($revision->Revision_ID) : collect()];
    }

    private function quiz(string $id): object
    {
        $quiz = $this->db()->table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', trim($id))->first();
        abort_unless($quiz, 404);

        return $quiz;
    }

    private function questions(string $revisionId)
    {
        return $this->db()->table('PRACTICE_QUESTION_REVISION')->where('Revision_ID', $revisionId)->orderBy('Sort_Order')->get();
    }

    private function ownedAttempt(string $id, string $studentId, bool $lock = false): object
    {
        $query = $this->db()->table('PRACTICE_ATTEMPT')->where('Attempt_ID', trim($id))->where('Student_ID', trim($studentId));
        if ($lock) {
            $query->lockForUpdate();
        }
        $attempt = $query->first();
        abort_unless($attempt, 404);

        return $attempt;
    }

    private function idempotentResult(string $key, string $userId, string $operation, string $hash): ?string
    {
        $row = $this->db()->table('PRACTICE_OPERATION')->where('Operation_Key', $key)->first();
        if (! $row) {
            return null;
        }
        abort_unless(hash_equals((string) $row->User_ID, trim($userId)) && $row->Operation === $operation && hash_equals((string) $row->Payload_Hash, $hash), 409, 'Kunci idempotensi sudah digunakan untuk data berbeda.');

        return json_decode((string) $row->Result_JSON, true, 512, JSON_THROW_ON_ERROR)['id'];
    }

    private function recordOperation(string $key, string $userId, string $operation, string $scopeId, string $hash, string $result, string $now): void
    {
        $this->db()->table('PRACTICE_OPERATION')->insert(['Operation_Key' => $key, 'User_ID' => trim($userId), 'Operation' => $operation, 'Scope_ID' => $scopeId, 'Payload_Hash' => $hash, 'Result_JSON' => json_encode(['id' => $result], JSON_THROW_ON_ERROR), 'Created_At' => $now]);
    }

    private function db(): ConnectionInterface
    {
        return DB::connection((string) config('wms_runtime.connection', config('database.default')));
    }
}
