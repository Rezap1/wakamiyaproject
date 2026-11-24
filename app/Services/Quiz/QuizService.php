<?php

namespace App\Services\Quiz;

use App\Interfaces\GoogleSheets\QuizAttemptRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizResultRepositoryInterface;
use App\Repositories\MySql\BaseMySqlRepository;
use App\Services\Core\ActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuizService
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private QuizQuestionRepositoryInterface $questions,
        private QuizAttemptRepositoryInterface $attempts,
        private QuizResultRepositoryInterface $results,
        private QuizPeriodService $periods,
        private ActivityLogService $activityLog,
        private ?QuizMediaService $media = null,
        private ?QuizMediaIdempotencyService $mediaIdempotency = null,
        private ?QuizAudienceService $audiences = null,
        private ?QuizRuntimeStateService $runtimeState = null,
    ) {}

    public function create(array $data, array $teacher, string $userId): string
    {
        [$idempotencyKey, $payloadHash] = $this->idempotencyContext($data, $userId, 'create', null);
        if ($idempotencyKey !== null) {
            $replay = $this->mediaIdempotency?->replayResult($idempotencyKey, $userId, 'create', null, $payloadHash);
            if ($replay !== null) {
                return $this->availableReplay($replay, $teacher);
            }
        }
        $quizId = 'QIZ'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 20));
        $this->assertQuestionPayload($data['questions'] ?? []);
        $plan = $this->media?->prepare($quizId, $data['questions'], [], $userId);
        if ($plan !== null) {
            $data['questions'] = $plan['questions'];
        }
        $this->assertPublishableQuestions($data);

        try {
            $created = $this->runtimeTransaction($this->quizzes, function () use ($data, $teacher, $userId, $quizId, $plan, $idempotencyKey, $payloadHash): string {
                $now = $this->stamp($this->periods->now());
                $publish = ($data['intent'] ?? 'draft') === 'publish';
                $this->quizzes->create([
                    'Quiz_ID' => $quizId,
                    'Teacher_ID' => $teacher['Teacher_ID'],
                    'Class_ID' => $data['Class_IDs'][0] ?? $data['Class_ID'],
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
                $this->audience()->replace($quizId, $data['Class_IDs'] ?? [$data['Class_ID']], $now);
                $this->state()->ensure($quizId, $now);
                $this->writeQuestions($quizId, $data['questions'], $now);
                if ($publish) {
                    $this->quizzes->update($quizId, ['Status' => 'PUBLISHED', 'Updated_At' => $now, 'Updated_By' => $userId]);
                }
                if ($idempotencyKey !== null) {
                    $this->mediaIdempotency?->recordCommitted(
                        $idempotencyKey,
                        $userId,
                        'create',
                        null,
                        $payloadHash,
                        $quizId,
                        $plan['token_uses'] ?? [],
                        $now,
                    );
                }

                return $quizId;
            });
        } catch (\Throwable $exception) {
            if ($plan !== null) {
                $this->media?->rollback($plan);
            }
            if ($idempotencyKey !== null) {
                $replay = $this->mediaIdempotency?->replayResult($idempotencyKey, $userId, 'create', null, $payloadHash);
                if ($replay !== null) {
                    return $this->availableReplay($replay, $teacher);
                }
            }
            throw $exception;
        }
        if ($plan !== null) {
            $this->media?->commit($plan);
        }

        return $created;
    }

    public function update(string $quizId, array $data, array $teacher, string $userId, ?array $allowedClassIds = null): void
    {
        $this->withCacheLock($this->quizMutationLock($quizId), 45, 15, function () use ($quizId, $data, $teacher, $userId, $allowedClassIds) {
            [$idempotencyKey, $payloadHash] = $this->idempotencyContext($data, $userId, 'update', $quizId);
            if ($idempotencyKey !== null) {
                $replay = $this->mediaIdempotency?->replayResult(
                    $idempotencyKey,
                    $userId,
                    'update',
                    $quizId,
                    $payloadHash,
                );
                if ($replay !== null) {
                    $this->availableReplay($replay, $teacher, $allowedClassIds);

                    return;
                }
            }
            $quiz = $this->requireQuizFresh($quizId);
            $this->assertTeacherOwns($quiz, $teacher['Teacher_ID']);
            $this->assertTeacherClassAllowed($quiz, $allowedClassIds);
            abort_unless($this->isEditable($quiz), 409, 'Kuis tidak dapat diubah setelah aktif atau setelah percobaan dimulai.');
            $this->assertQuestionPayload($data['questions'] ?? []);
            $existingQuestions = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $quizId)->values();
            $plan = $this->media?->prepare($quizId, $data['questions'], $existingQuestions, $userId);
            if ($plan !== null) {
                $data['questions'] = $plan['questions'];
            }
            $this->assertPublishableQuestions($data);
            $now = $this->stamp($this->periods->now());

            try {
                $this->runtimeTransaction($this->quizzes, function () use ($quizId, $data, $userId, $now, $existingQuestions, $plan, $idempotencyKey, $payloadHash): void {
                    $this->questions->hardDeleteMany($existingQuestions->pluck('Question_ID')->all());
                    $this->writeQuestions($quizId, $data['questions'], $now);
                    $this->quizzes->update($quizId, [
                        'Class_ID' => $data['Class_IDs'][0] ?? $data['Class_ID'],
                        'Title' => trim($data['Title']),
                        'Start_At' => $this->stamp($this->date($data['Start_At'])),
                        'End_At' => $this->stamp($this->date($data['End_At'])),
                        'Duration_Minutes' => (int) $data['Duration_Minutes'],
                        'Status' => ($data['intent'] ?? 'draft') === 'publish' ? 'PUBLISHED' : 'DRAFT',
                        'Updated_At' => $now,
                        'Updated_By' => $userId,
                    ]);
                    $this->audience()->replace($quizId, $data['Class_IDs'] ?? [$data['Class_ID']], $now);
                    if ($idempotencyKey !== null) {
                        $this->mediaIdempotency?->recordCommitted(
                            $idempotencyKey,
                            $userId,
                            'update',
                            $quizId,
                            $payloadHash,
                            $quizId,
                            $plan['token_uses'] ?? [],
                            $now,
                        );
                    }
                }, [[$this->quizzes, $quizId]]);
            } catch (\Throwable $exception) {
                if ($plan !== null) {
                    $this->media?->rollback($plan);
                }
                throw $exception;
            }
            if ($plan !== null) {
                $this->media?->commit($plan);
            }
        });
    }

    public function teacherIndex(string $teacherId, ?array $allowedClassIds = null): array
    {
        $questions = collect($this->questions->fetchAll())->groupBy('Quiz_ID');
        $attempts = collect($this->attempts->fetchAll())->groupBy('Quiz_ID');
        $results = collect($this->results->fetchAll())->groupBy('Quiz_ID');
        $rows = collect($this->quizzes->fetchAll())->where('Teacher_ID', $teacherId)
            ->when($allowedClassIds !== null, fn ($rows) => $rows->filter(
                fn ($quiz) => $this->quizInClasses((array) $quiz, $allowedClassIds)
            ))->map(function ($quiz) use ($questions, $attempts, $results) {
                $quiz = (array) $quiz;
                $quiz['Audience_Class_IDs'] = $this->audience()->classIds($quiz);
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

    public function teacherClassIndex(string $teacherId, iterable $classes, Collection $studentCounts): Collection
    {
        $classes = collect($classes)->values();
        if ($classes->isEmpty()) {
            return collect();
        }

        $allowedClassIds = $classes->pluck('Class_ID')->map(fn ($id) => trim((string) $id))->filter()->all();
        $quizzes = collect($this->quizzes->fetchAll())
            ->filter(fn ($quiz) => trim((string) ($quiz['Teacher_ID'] ?? '')) === trim($teacherId)
                && $this->quizInClasses((array) $quiz, $allowedClassIds))
            ->map(function ($quiz) {
                $quiz = (array) $quiz;
                $quiz['Lifecycle'] = $this->lifecycle($quiz);
                $quiz['Audience_Class_IDs'] = $this->audience()->classIds($quiz);

                return $quiz;
            })
            ->flatMap(fn ($quiz) => collect($quiz['Audience_Class_IDs'])->mapWithKeys(fn ($classId) => [
                $classId.'|'.$quiz['Quiz_ID'] => $quiz,
            ]));

        return $classes->map(function ($class) use ($quizzes, $studentCounts) {
            $class = (array) $class;
            $classId = trim((string) ($class['Class_ID'] ?? ''));
            $classQuizzes = $quizzes->filter(fn ($quiz, $key) => str_starts_with((string) $key, $classId.'|'))->values();

            return $class + [
                'Active_Quiz_Count' => $classQuizzes->where('Lifecycle', 'ACTIVE')->count(),
                'Upcoming_Quiz_Count' => $classQuizzes->whereIn('Lifecycle', ['DRAFT', 'SCHEDULED'])->count(),
                'Completed_Quiz_Count' => $classQuizzes->whereIn('Lifecycle', ['EXPIRED', 'CLOSED'])->count(),
                'Quiz_Count' => $classQuizzes->count(),
                'Student_Count' => (int) $studentCounts->get($classId, 0),
            ];
        })->values();
    }

    public function teacherClassHub(string $teacherId, string $classId, iterable $roster = []): array
    {
        $questionsByQuiz = collect($this->questions->fetchAll())->countBy('Quiz_ID');
        $allResults = collect($this->results->fetchAll());
        $resultsByQuiz = $allResults->groupBy('Quiz_ID');
        $quizzes = collect($this->quizzes->fetchAll())
            ->filter(fn ($quiz) => trim((string) ($quiz['Teacher_ID'] ?? '')) === trim($teacherId)
                && $this->audience()->includes((array) $quiz, $classId))
            ->map(function ($quiz) use ($questionsByQuiz, $resultsByQuiz, $classId) {
                $quiz = (array) $quiz;
                $quiz['Audience_Class_IDs'] = $this->audience()->classIds($quiz);
                $quizId = trim((string) ($quiz['Quiz_ID'] ?? ''));
                $quizResults = collect($resultsByQuiz->get($quizId, collect()))
                    ->filter(fn ($result) => trim((string) ($result['Class_ID'] ?? '')) === trim($classId));
                $quiz['Lifecycle'] = $this->lifecycle($quiz);
                $quiz['Question_Count'] = (int) $questionsByQuiz->get($quizId, 0);
                $quiz['Participant_Count'] = $quizResults->pluck('Student_ID')->filter()->unique()->count();
                $quiz['Completion_Count'] = $quizResults->pluck('Result_ID')->filter()->unique()->count();

                return $quiz;
            })
            ->sortByDesc('Start_At')
            ->values();
        $period = $this->periods->periodFor();

        return [
            'summary' => [
                'active' => $quizzes->where('Lifecycle', 'ACTIVE')->count(),
                'upcoming' => $quizzes->whereIn('Lifecycle', ['DRAFT', 'SCHEDULED'])->count(),
                'completed' => $quizzes->whereIn('Lifecycle', ['EXPIRED', 'CLOSED'])->count(),
                'students' => collect($roster)->count(),
            ],
            'leaderboard' => [
                'period' => $period,
                'entries' => $this->leaderboardFromResults($allResults, $classId, $period),
            ],
            'groups' => [
                'active' => $quizzes->where('Lifecycle', 'ACTIVE')->take(12)->values(),
                'upcoming' => $quizzes->whereIn('Lifecycle', ['DRAFT', 'SCHEDULED'])->take(12)->values(),
                'completed' => $quizzes->whereIn('Lifecycle', ['EXPIRED', 'CLOSED'])->take(12)->values(),
            ],
            'group_totals' => [
                'active' => $quizzes->where('Lifecycle', 'ACTIVE')->count(),
                'upcoming' => $quizzes->whereIn('Lifecycle', ['DRAFT', 'SCHEDULED'])->count(),
                'completed' => $quizzes->whereIn('Lifecycle', ['EXPIRED', 'CLOSED'])->count(),
            ],
            'quiz_count' => $quizzes->count(),
            'section_limit' => 12,
        ];
    }

    public function teacherQuiz(string $quizId, string $teacherId, ?array $allowedClassIds = null): array
    {
        $quiz = (array) ($this->quizzes->findById($quizId) ?: abort(404));
        $this->assertTeacherOwns($quiz, $teacherId);
        $this->assertTeacherClassAllowed($quiz, $allowedClassIds);
        $quiz['Audience_Class_IDs'] = $this->audience()->classIds($quiz);
        $quiz['questions'] = collect($this->questions->fetchAll())->where('Quiz_ID', $quizId)->sortBy('Sort_Order')->map(function ($question) use ($quizId) {
            $question = (array) $question;
            $question['Image_URL'] = ! empty($question['Image_Path']) ? route('teacher.quizzes.media', [$quizId, $question['Question_ID'], 'image']) : null;
            $question['Audio_URL'] = ! empty($question['Audio_Path']) ? route('teacher.quizzes.media', [$quizId, $question['Question_ID'], 'audio']) : null;

            return $question;
        })->values();
        $quiz['results'] = collect($this->results->fetchAll())->where('Quiz_ID', $quizId)->sortByDesc('Completed_At')->values();
        $quiz['Attempt_Count'] = collect($this->attempts->fetchAll())->where('Quiz_ID', $quizId)->count();
        $quiz['Result_Count'] = $quiz['results']->count();
        $quiz['Participant_Count'] = $quiz['results']->pluck('Student_ID')->filter()->unique()->count();
        $quiz['Lifecycle'] = $this->lifecycle($quiz);
        $quiz['Editable'] = $this->isEditable($quiz);
        $quiz['Deletable'] = $this->isDeletable($quiz);

        return $quiz;
    }

    public function deleteQuiz(
        string $quizId,
        string $teacherId,
        array $allowedClassIds,
        string $userId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        return $this->withCacheLock($this->quizMutationLock($quizId), 60, 20, function () use ($quizId, $teacherId, $allowedClassIds, $userId, $ipAddress, $userAgent) {
            $outcome = $this->runtimeTransaction($this->quizzes, function () use ($quizId, $teacherId, $allowedClassIds, $userId, $ipAddress, $userAgent): array {
                $quiz = $this->requireQuizFresh($quizId);
                $this->assertTeacherOwns($quiz, $teacherId);
                $this->assertTeacherClassAllowed($quiz, $allowedClassIds);

                $resultIds = collect($this->results->fetchAllFresh())->where('Quiz_ID', $quizId)->pluck('Result_ID')->filter()->values()->all();
                $attemptIds = collect($this->attempts->fetchAllFresh())->where('Quiz_ID', $quizId)->pluck('Attempt_ID')->filter()->values()->all();
                $questionRows = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $quizId);
                $questionIds = $questionRows->pluck('Question_ID')->all();

                $this->deleteChildrenAndVerify($this->results, $resultIds, $quizId, 'hasil kuis');
                $this->deleteChildrenAndVerify($this->attempts, $attemptIds, $quizId, 'percobaan kuis');
                $this->deleteChildrenAndVerify($this->questions, $questionIds, $quizId, 'soal kuis');
                $audienceIds = $this->audience()->classIds($quiz);
                $this->audience()->delete($quizId);
                $this->quizzes->hardDeleteMany([$quizId]);
                if ($this->quizzes->findByIdFresh($quizId)) {
                    throw new \RuntimeException("Kuis {$quizId} belum terhapus sepenuhnya.");
                }

                $deleted = [
                    'quiz' => 1,
                    'questions' => count($questionIds),
                    'attempts' => count($attemptIds),
                    'results' => count($resultIds),
                ];
                $deletedAt = $this->stamp($this->periods->now());
                $this->state()->tombstone($quizId, $userId, $deletedAt);
                $this->activityLog->logAction(
                    $userId,
                    'DELETE',
                    'QUIZ',
                    'Kuis dihapus permanen beserta data turunannya.',
                    $ipAddress,
                    [
                        'Title' => trim((string) ($quiz['Title'] ?? '')),
                        'Class_IDs' => $audienceIds,
                    ],
                    null,
                    $userAgent,
                    'QUIZ',
                    $quizId,
                );

                return [
                    'quiz' => $quiz,
                    'deleted' => $deleted,
                    'media_paths' => $questionRows->flatMap(fn ($row) => [$row['Image_Path'] ?? null, $row['Audio_Path'] ?? null])->filter()->values()->all(),
                ];
            }, [[$this->quizzes, $quizId]]);
            if ($this->media !== null) {
                $this->media->deleteUnreferenced($outcome['media_paths']);
            }
            unset($outcome['media_paths']);

            return $outcome;
        });
    }

    public function resetResults(
        string $quizId,
        string $teacherId,
        array $allowedClassIds,
        string $userId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        return $this->withCacheLock($this->quizMutationLock($quizId), 60, 20, function () use ($quizId, $teacherId, $allowedClassIds, $userId, $ipAddress, $userAgent): array {
            return $this->runtimeTransaction($this->quizzes, function () use ($quizId, $teacherId, $allowedClassIds, $userId, $ipAddress, $userAgent): array {
                $quiz = $this->requireQuizFresh($quizId);
                $this->assertTeacherOwns($quiz, $teacherId);
                $this->assertTeacherClassAllowed($quiz, $allowedClassIds);
                $resultIds = collect($this->results->fetchAllFresh())->where('Quiz_ID', $quizId)->pluck('Result_ID')->filter()->values()->all();
                $attemptIds = collect($this->attempts->fetchAllFresh())->where('Quiz_ID', $quizId)->pluck('Attempt_ID')->filter()->values()->all();

                $this->deleteChildrenAndVerify($this->results, $resultIds, $quizId, 'hasil kuis');
                $this->deleteChildrenAndVerify($this->attempts, $attemptIds, $quizId, 'percobaan kuis');
                $stamp = $this->stamp($this->periods->now());
                $generation = $this->state()->incrementGeneration($quizId, $stamp);
                $this->activityLog->logAction(
                    $userId,
                    'RESET',
                    'QUIZ_RESULT',
                    'Hasil dan percobaan kuis direset; definisi soal dan media dipertahankan.',
                    $ipAddress,
                    ['Attempt_Count' => count($attemptIds), 'Result_Count' => count($resultIds)],
                    ['Attempt_Generation' => $generation],
                    $userAgent,
                    'QUIZ',
                    $quizId,
                );

                return [
                    'quiz' => $quiz,
                    'deleted' => ['attempts' => count($attemptIds), 'results' => count($resultIds)],
                    'attempt_generation' => $generation,
                ];
            }, [[$this->quizzes, $quizId]]);
        });
    }

    public function teacherResults(string $teacherId, string $classId, iterable $roster = []): array
    {
        $roster = collect($roster)->keyBy(fn ($student) => trim((string) ($student['Student_ID'] ?? '')));
        $results = collect($this->results->fetchAll())
            ->filter(fn ($row) => trim((string) ($row['Teacher_ID'] ?? '')) === trim($teacherId)
                && trim((string) ($row['Class_ID'] ?? '')) === trim($classId))
            ->sortByDesc('Completed_At')
            ->values();
        $resultsByQuiz = $results->groupBy('Quiz_ID');
        $quizzes = collect($this->quizzes->fetchAll())
            ->filter(fn ($quiz) => trim((string) ($quiz['Teacher_ID'] ?? '')) === trim($teacherId)
                && $this->audience()->includes((array) $quiz, $classId))
            ->keyBy('Quiz_ID');

        foreach ($resultsByQuiz as $quizId => $quizResults) {
            if (! $quizzes->has($quizId)) {
                $first = (array) $quizResults->first();
                $quizzes->put($quizId, [
                    'Quiz_ID' => $quizId,
                    'Title' => $first['Quiz_Title'] ?? 'Kuis Historis',
                    'Class_ID' => $classId,
                    'Teacher_ID' => $teacherId,
                    'Start_At' => '',
                ]);
            }
        }

        $groups = $quizzes->map(function ($quiz) use ($resultsByQuiz, $roster) {
            $quizId = trim((string) ($quiz['Quiz_ID'] ?? ''));
            $rows = collect($resultsByQuiz->get($quizId, collect()))
                ->unique('Result_ID')
                ->sortBy(fn ($row) => mb_strtolower(trim((string) ($row['Student_Name'] ?? $row['Student_ID'] ?? ''))))
                ->values();
            $firstResult = (array) ($rows->first() ?? []);
            $completedIds = $rows->pluck('Student_ID')->map(fn ($id) => trim((string) $id))->filter()->unique();
            $notCompleted = $roster->reject(fn ($student, $studentId) => $completedIds->contains($studentId))->values();

            return [
                'quiz_id' => $quizId,
                'title' => trim((string) ($quiz['Title'] ?? $firstResult['Quiz_Title'] ?? 'Kuis')),
                'participant_count' => max($roster->count(), $completedIds->count()),
                'completed_count' => $completedIds->count(),
                'not_completed_count' => $notCompleted->count(),
                'average' => $rows->isEmpty() ? null : round((float) $rows->avg(fn ($row) => (float) ($row['Raw_Score'] ?? 0)), 2),
                'results' => $rows,
                'not_completed' => $notCompleted,
                'start_at' => $quiz['Start_At'] ?? '',
            ];
        })->sortByDesc('start_at')->values();

        return [
            'groups' => $groups,
            'result_count' => $results->count(),
            'student_count' => $roster->count(),
        ];
    }

    public function teacherResult(string $resultId, string $teacherId, array $allowedClassIds): array
    {
        $result = (array) ($this->results->findByIdFresh($resultId) ?: abort(404));
        abort_unless(
            trim((string) ($result['Teacher_ID'] ?? '')) === trim($teacherId)
            && in_array(trim((string) ($result['Class_ID'] ?? '')), $allowedClassIds, true),
            403,
            'Hasil kuis berada di luar scope kelas Anda.'
        );

        return $result;
    }

    public function studentIndex(array $student): array
    {
        $this->finalizeExpiredAttemptsForStudent($student);
        $now = $this->periods->now();
        $attempts = collect($this->attempts->fetchAll())->where('Student_ID', $student['Student_ID'])->keyBy('Quiz_ID');
        $results = collect($this->results->fetchAll())->where('Student_ID', $student['Student_ID'])->keyBy('Quiz_ID');
        $counts = collect($this->questions->fetchAll())->countBy('Quiz_ID');
        $rows = collect($this->quizzes->fetchAll())
            ->filter(fn ($quiz) => $this->audience()->includes((array) $quiz, (string) $student['Class_ID']))
            ->where('Status', 'PUBLISHED')
            ->map(function ($quiz) use ($attempts, $results, $counts, $now) {
                $quiz = (array) $quiz;
                $attempt = $attempts->get($quiz['Quiz_ID']);
                $result = $results->get($quiz['Quiz_ID']);
                $quiz['Lifecycle'] = $this->lifecycle($quiz, $now);
                $quiz['Question_Count'] = (int) $counts->get($quiz['Quiz_ID'], 0);
                $quiz['Attempt_ID'] = $attempt['Attempt_ID'] ?? null;
                $quiz['Result_ID'] = $result['Result_ID'] ?? null;
                $quiz['Raw_Score'] = $result['Raw_Score'] ?? null;
                $quiz['Maximum_Score'] = $result['Maximum_Score'] ?? null;
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
        return $this->withCacheLock($this->quizMutationLock($quizId), 45, 15, function () use ($quizId, $student) {
            $attemptId = $this->attemptId($quizId, $student['Student_ID'], $this->state()->generation($quizId));

            return $this->runtimeTransaction($this->attempts, function () use ($quizId, $student, $attemptId): array {
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
                        'Class_ID' => $student['Class_ID'],
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
            }, [[$this->quizzes, $quizId]]);
        });
    }

    public function resume(string $attemptId, array $student): array
    {
        $attempt = (array) ($this->attempts->findByIdFresh($attemptId) ?: abort(404));
        abort_unless(trim((string) ($attempt['Student_ID'] ?? '')) === trim($student['Student_ID']), 403);
        $quiz = $this->requireQuizFresh($attempt['Quiz_ID']);
        $this->assertStudentEligible($quiz, $student);
        $existingResult = collect($this->results->fetchAllFresh())->firstWhere('Attempt_ID', $attemptId);
        if ($existingResult) {
            return ['finalized_result' => (array) $existingResult];
        }
        if ($this->attemptExpired($attempt, $quiz)) {
            return ['finalized_result' => $this->submit($attemptId, $student, [])];
        }

        return $this->playerPayload($quiz, $attempt, $student);
    }

    public function autosave(string $attemptId, array $student, array $answers, int $version): array
    {
        $snapshot = (array) ($this->attempts->findByIdFresh($attemptId) ?: abort(404));
        $quizId = trim((string) ($snapshot['Quiz_ID'] ?? ''));

        return $this->withCacheLock($this->quizMutationLock($quizId), 45, 15, function () use ($attemptId, $student, $answers, $version) {
            return $this->runtimeTransaction($this->attempts, function () use ($attemptId, $student, $answers, $version): array {
                abort_if($this->results->findByIdFresh($this->resultId($attemptId)), 409, 'Kuis sudah difinalisasi.');
                $attempt = (array) ($this->attempts->findByIdFresh($attemptId) ?: abort(404));
                abort_unless(($attempt['Student_ID'] ?? '') === $student['Student_ID'], 403);
                abort_unless(($attempt['Status'] ?? '') === 'IN_PROGRESS', 409, 'Percobaan kuis sudah difinalisasi.');
                $quiz = $this->requireQuizFresh($attempt['Quiz_ID']);
                $this->assertStudentEligible($quiz, $student);
                abort_if($this->attemptExpired($attempt, $quiz), 410, 'Waktu kuis telah berakhir; jawaban baru tidak diterima.');

                $questions = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $quiz['Quiz_ID'])->sortBy('Sort_Order')->values();
                abort_if($questions->isEmpty(), 409, 'Kuis tidak memiliki soal.');
                $normalized = $this->validatedAnswers($answers, $questions);
                $state = $this->answerState($attempt);

                if ($version < $state['version'] || ($version === $state['version'] && $normalized !== $state['answers'])) {
                    return [
                        'saved' => false,
                        'conflict' => true,
                        'message' => 'Versi jawaban lebih lama dari jawaban yang sudah tersimpan.',
                        'version' => $state['version'],
                        'answers' => $state['answers'],
                    ];
                }
                if ($version === $state['version']) {
                    return [
                        'saved' => true,
                        'idempotent' => true,
                        'version' => $state['version'],
                        'answers' => $state['answers'],
                        'saved_at' => $attempt['Updated_At'] ?? null,
                    ];
                }

                $stamp = $this->stamp($this->periods->now());
                $this->attempts->update($attemptId, [
                    'Submitted_Answers_JSON' => $this->encodeAnswerState($version, $normalized),
                    'Updated_At' => $stamp,
                ]);

                return [
                    'saved' => true,
                    'idempotent' => false,
                    'version' => $version,
                    'answers' => $normalized,
                    'saved_at' => $stamp,
                ];
            }, [[$this->attempts, $attemptId]]);
        });
    }

    public function submit(string $attemptId, array $student, array $answers, ?int $answerVersion = null): array
    {
        $snapshot = (array) ($this->attempts->findByIdFresh($attemptId) ?: abort(404));
        $quizId = trim((string) ($snapshot['Quiz_ID'] ?? ''));

        return $this->withCacheLock($this->quizMutationLock($quizId), 45, 15, function () use ($attemptId, $student, $answers, $answerVersion) {
            return $this->runtimeTransaction($this->attempts, function () use ($attemptId, $student, $answers, $answerVersion): array {
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
                $state = $this->answerState($attempt);
                if ($onTime && $answers !== []) {
                    $normalized = $this->validatedAnswers($answers, $questions);
                    if ($answerVersion === null) {
                        abort_if($state['answers'] !== [] && $normalized !== $state['answers'], 409, 'Simpan jawaban terbaru sebelum finalisasi.');
                        if ($state['answers'] === []) {
                            $state = ['version' => max(1, $state['version']), 'answers' => $normalized];
                        }
                    } else {
                        abort_if($answerVersion < $state['version'], 409, 'Versi jawaban final lebih lama dari jawaban tersimpan.');
                        abort_if($answerVersion === $state['version'] && $normalized !== $state['answers'], 409, 'Versi jawaban final berkonflik dengan jawaban tersimpan.');
                        if ($answerVersion > $state['version']) {
                            $state = ['version' => $answerVersion, 'answers' => $normalized];
                        }
                    }
                }

                $accepted = $state['answers'];
                $maximum = 0.0;
                $raw = 0.0;
                foreach ($questions as $question) {
                    $questionId = (string) $question['Question_ID'];
                    $point = (float) $question['Point'];
                    $maximum += $point;
                    $selected = strtoupper(trim((string) ($accepted[$questionId] ?? '')));
                    if (in_array($selected, ['A', 'B', 'C', 'D'], true)) {
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
                    'Class_ID' => $student['Class_ID'],
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
                    'Submitted_Answers_JSON' => $this->encodeAnswerState($state['version'], $accepted),
                    'Submitted_At' => $stamp,
                    'Updated_At' => $stamp,
                ]);

                return $result;
            }, [[$this->attempts, $attemptId]]);
        });
    }

    public function review(string $resultId, array $student): array
    {
        $result = (array) ($this->results->findByIdFresh($resultId) ?: abort(404));
        abort_unless(($result['Student_ID'] ?? '') === $student['Student_ID'] && ($result['Class_ID'] ?? '') === $student['Class_ID'], 403);
        abort_unless($this->reviewAvailable($result), 410, 'Masa review 4 menit telah berakhir.');
        $attempt = (array) ($this->attempts->findByIdFresh($result['Attempt_ID']) ?: abort(410));
        abort_unless(($attempt['Student_ID'] ?? '') === $student['Student_ID'], 403);
        $answers = $this->answerState($attempt)['answers'];
        $questions = collect($this->questions->fetchAllFresh())->where('Quiz_ID', $result['Quiz_ID'])->sortBy('Sort_Order')->map(fn ($question) => [
            'Question_ID' => $question['Question_ID'],
            'Question_Text' => $question['Question_Text'],
            'Option_A' => $question['Option_A'], 'Option_B' => $question['Option_B'],
            'Option_C' => $question['Option_C'], 'Option_D' => $question['Option_D'],
            'Selected_Option' => $answers[$question['Question_ID']] ?? null,
            'Image_URL' => ! empty($question['Image_Path']) ? route('student.quizzes.result-media', [$resultId, $question['Question_ID'], 'image']) : null,
            'Audio_URL' => ! empty($question['Audio_Path']) ? route('student.quizzes.result-media', [$resultId, $question['Question_ID'], 'audio']) : null,
            'Audio_Duration_Milliseconds' => $question['Audio_Duration_Milliseconds'] ?? null,
        ])->values();

        return compact('result', 'questions');
    }

    /** @return array{path:string,mime:string} */
    public function teacherMedia(string $quizId, string $questionId, string $type, string $teacherId, array $allowedClassIds): array
    {
        $quiz = $this->requireQuizFresh($quizId);
        $this->assertTeacherOwns($quiz, $teacherId);
        $this->assertTeacherClassAllowed($quiz, $allowedClassIds);
        $question = (array) ($this->questions->findByIdFresh($questionId) ?: abort(404));

        return $this->mediaFromQuestion($question, $quizId, $type);
    }

    /** @return array{path:string,mime:string} */
    public function attemptMedia(string $attemptId, string $questionId, string $type, array $student): array
    {
        $attempt = (array) ($this->attempts->findByIdFresh($attemptId) ?: abort(404));
        abort_unless(trim((string) ($attempt['Student_ID'] ?? '')) === trim((string) $student['Student_ID']), 403);
        abort_unless(($attempt['Status'] ?? '') === 'IN_PROGRESS', 410, 'Percobaan kuis sudah berakhir.');
        $quiz = $this->requireQuizFresh((string) $attempt['Quiz_ID']);
        $this->assertStudentEligible($quiz, $student);
        abort_unless(($quiz['Status'] ?? '') === 'PUBLISHED', 403);
        abort_if($this->attemptExpired($attempt, $quiz), 410, 'Waktu kuis telah berakhir.');
        $question = (array) ($this->questions->findByIdFresh($questionId) ?: abort(404));

        return $this->mediaFromQuestion($question, (string) $quiz['Quiz_ID'], $type);
    }

    /** @return array{path:string,mime:string} */
    public function resultMedia(string $resultId, string $questionId, string $type, array $student): array
    {
        $result = (array) ($this->results->findByIdFresh($resultId) ?: abort(404));
        abort_unless(($result['Student_ID'] ?? '') === $student['Student_ID'] && ($result['Class_ID'] ?? '') === $student['Class_ID'], 403);
        abort_unless($this->reviewAvailable($result), 410, 'Masa review 4 menit telah berakhir.');
        $question = (array) ($this->questions->findByIdFresh($questionId) ?: abort(404));

        return $this->mediaFromQuestion($question, (string) $result['Quiz_ID'], $type);
    }

    public function leaderboard(string $classId, ?CarbonImmutable $at = null): array
    {
        $period = $this->periods->periodFor($at);

        return [
            'period' => $period,
            'entries' => $this->leaderboardFromResults(collect($this->results->fetchAll()), $classId, $period),
        ];
    }

    public function studentDashboard(array $student): array
    {
        $this->finalizeExpiredAttemptsForStudent($student);
        $now = $this->periods->now();
        $studentId = trim((string) ($student['Student_ID'] ?? ''));
        $classId = trim((string) ($student['Class_ID'] ?? ''));
        $results = collect($this->results->fetchAll());
        $studentResults = $results->where('Student_ID', $studentId)->keyBy('Quiz_ID');
        $studentAttempts = collect($this->attempts->fetchAll())->where('Student_ID', $studentId)->keyBy('Quiz_ID');
        $quizzes = collect($this->quizzes->fetchAll())
            ->filter(fn ($quiz) => $this->audience()->includes((array) $quiz, $classId)
                && strtoupper(trim((string) ($quiz['Status'] ?? ''))) === 'PUBLISHED')
            ->map(function ($quiz) use ($now, $studentResults, $studentAttempts) {
                $quiz = (array) $quiz;
                $quiz['Lifecycle'] = $this->lifecycle($quiz, $now);
                $quiz['Result_ID'] = data_get($studentResults->get($quiz['Quiz_ID']), 'Result_ID');
                $quiz['Attempt_ID'] = data_get($studentAttempts->get($quiz['Quiz_ID']), 'Attempt_ID');

                return $quiz;
            })
            ->filter(fn ($quiz) => empty($quiz['Result_ID']))
            ->values();
        $period = $this->periods->periodFor($now);
        $entries = $this->leaderboardFromResults($results, $classId, $period);
        $current = $entries->firstWhere('Student_ID', $studentId);

        return [
            'student_id' => $studentId,
            'available' => $quizzes->where('Lifecycle', 'ACTIVE')->sortBy('End_At')->take(3)->values(),
            'upcoming' => $quizzes->where('Lifecycle', 'SCHEDULED')->sortBy('Start_At')->take(3)->values(),
            'leaderboard' => [
                'period' => $period,
                'top' => $entries->take(3)->values(),
                'current' => $current && (int) $current['Rank'] > 3 ? $current : null,
            ],
        ];
    }

    private function leaderboardFromResults($results, string $classId, array $period)
    {
        $groups = collect($results)
            ->where('Class_ID', $classId)
            ->filter(fn ($row) => ! empty($row['Completed_At']) && $this->periods->contains($period, $row['Completed_At']))
            ->groupBy('Student_ID')->map(function ($rows) {
                $first = (array) $rows->first();

                return [
                    'Student_ID' => $first['Student_ID'],
                    'Student_Name' => $first['Student_Name'] ?: 'Siswa',
                    'Points' => round((float) $rows->sum(fn ($row) => (float) ($row['Raw_Score'] ?? 0)), 4),
                    'Quiz_Count' => $rows->pluck('Quiz_ID')->filter()->unique()->count(),
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

        return $ranked;
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
            'Image_URL' => ! empty($question['Image_Path']) ? route('student.quizzes.attempt-media', [$attempt['Attempt_ID'], $question['Question_ID'], 'image']) : null,
            'Audio_URL' => ! empty($question['Audio_Path']) ? route('student.quizzes.attempt-media', [$attempt['Attempt_ID'], $question['Question_ID'], 'audio']) : null,
            'Audio_Duration_Milliseconds' => $question['Audio_Duration_Milliseconds'] ?? null,
        ])->values();
        abort_if($questions->isEmpty(), 409, 'Kuis tidak memiliki soal.');

        $state = $this->answerState($attempt);

        return [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'questions' => $questions,
            'savedAnswers' => $state['answers'],
            'answerVersion' => $state['version'],
        ];
    }

    private function writeQuestions(string $quizId, array $questions, string $now): void
    {
        $expected = [];
        foreach (array_values($questions) as $index => $question) {
            $row = [
                'Question_ID' => $this->questionId($quizId, $index),
                'Quiz_ID' => $quizId,
                'Question_Text' => trim($question['Question_Text']),
                'Option_A' => trim($question['Option_A']), 'Option_B' => trim($question['Option_B']),
                'Option_C' => trim($question['Option_C']), 'Option_D' => trim($question['Option_D']),
                'Correct_Option' => strtoupper($question['Correct_Option']),
                'Point' => (float) $question['Point'],
                'Sort_Order' => $index + 1,
                'Created_At' => $now, 'Updated_At' => $now,
                'Image_Path' => $question['Image_Path'] ?? null,
                'Image_Mime' => $question['Image_Mime'] ?? null,
                'Image_Size_Bytes' => $question['Image_Size_Bytes'] ?? null,
                'Audio_Path' => $question['Audio_Path'] ?? null,
                'Audio_Mime' => $question['Audio_Mime'] ?? null,
                'Audio_Size_Bytes' => $question['Audio_Size_Bytes'] ?? null,
                'Audio_Duration_Milliseconds' => $question['Audio_Duration_Milliseconds'] ?? null,
            ];
            $this->questions->create($row);
            $expected[] = $row;
        }

        $persisted = collect($this->questions->fetchAllFresh())
            ->where('Quiz_ID', $quizId)
            ->sortBy('Sort_Order')
            ->values();
        if ($persisted->count() !== count($expected)) {
            throw new \RuntimeException("Soal kuis {$quizId} tidak tersimpan lengkap.");
        }
        foreach ($expected as $index => $row) {
            foreach (['Question_ID', 'Question_Text', 'Option_A', 'Option_B', 'Option_C', 'Option_D', 'Correct_Option', 'Point', 'Sort_Order', 'Image_Path', 'Image_Mime', 'Image_Size_Bytes', 'Audio_Path', 'Audio_Mime', 'Audio_Size_Bytes', 'Audio_Duration_Milliseconds'] as $field) {
                $persistedValue = $persisted[$index][$field] ?? null;
                $expectedValue = $row[$field];

                $matches = match ($field) {
                    'Point' => abs((float) $persistedValue - (float) $expectedValue) < 0.00001,
                    'Sort_Order' => (int) $persistedValue === (int) $expectedValue,
                    default => (string) $persistedValue === (string) $expectedValue,
                };

                if (! $matches) {
                    throw new \RuntimeException("Soal kuis {$quizId} gagal diverifikasi setelah penyimpanan pada field {$field}.");
                }
            }
        }
    }

    private function assertQuestionPayload(array $questions): void
    {
        $maximum = (int) config('quiz.max_questions', 100);
        $count = count($questions);
        if ($count < 1 || $count > $maximum) {
            throw new \InvalidArgumentException("Jumlah soal harus antara 1 dan {$maximum}.");
        }
        if (! array_is_list($questions)) {
            throw new \InvalidArgumentException('Urutan soal harus berurutan mulai dari 0.');
        }
    }

    /** @return array{0:?string,1:string} */
    private function idempotencyContext(array $data, string $userId, string $operation, ?string $targetQuizId): array
    {
        $key = trim((string) ($data['Idempotency_Key'] ?? ''));
        if ($this->mediaIdempotency === null) {
            return [null, ''];
        }
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['Idempotency_Key' => 'Kunci idempotensi kuis tidak valid. Muat ulang form dan coba lagi.']);
        }

        $payload = $data;
        unset($payload['Idempotency_Key']);
        $canonical = $this->canonicalize($payload);
        $hash = hash('sha256', json_encode([
            'user_id' => trim($userId),
            'operation' => $operation,
            'target_quiz_id' => $targetQuizId,
            'payload' => $canonical,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return [$key, $hash];
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }

    private function assertPublishableQuestions(array $data): void
    {
        if (($data['intent'] ?? 'draft') !== 'publish') {
            return;
        }
        foreach ($data['questions'] ?? [] as $index => $question) {
            $hasPrompt = trim((string) ($question['Question_Text'] ?? '')) !== ''
                || trim((string) ($question['Image_Path'] ?? '')) !== ''
                || trim((string) ($question['Audio_Path'] ?? '')) !== '';
            if (! $hasPrompt) {
                throw ValidationException::withMessages([
                    "questions.{$index}.Question_Text" => 'Isi teks pertanyaan atau unggah gambar/audio sebelum kuis diterbitkan.',
                ]);
            }
        }
    }

    private function finalizeExpiredAttemptsForStudent(array $student): void
    {
        $studentId = trim((string) ($student['Student_ID'] ?? ''));
        $classId = trim((string) ($student['Class_ID'] ?? ''));
        if ($studentId === '' || $classId === '') {
            return;
        }

        $completedAttemptIds = collect($this->results->fetchAllFresh())->pluck('Attempt_ID')->filter()->flip();
        $quizzes = collect($this->quizzes->fetchAllFresh())->keyBy('Quiz_ID');
        $attempts = collect($this->attempts->fetchAllFresh())
            ->where('Student_ID', $studentId)
            ->filter(fn ($attempt) => ! $completedAttemptIds->has($attempt['Attempt_ID'] ?? null));

        foreach ($attempts as $attempt) {
            $quiz = (array) ($quizzes->get($attempt['Quiz_ID'] ?? '') ?? []);
            if ($quiz === [] || ! $this->audience()->includes($quiz, $classId)) {
                continue;
            }
            if ($this->attemptExpired((array) $attempt, $quiz)) {
                $this->submit((string) $attempt['Attempt_ID'], $student, []);
            }
        }
    }

    private function attemptExpired(array $attempt, array $quiz): bool
    {
        $now = $this->periods->now();
        $deadline = trim((string) ($attempt['Deadline_At'] ?? ''));
        $quizEnd = trim((string) ($quiz['End_At'] ?? ''));

        return ($deadline !== '' && $now->greaterThanOrEqualTo($this->date($deadline)))
            || ($quizEnd !== '' && $now->greaterThanOrEqualTo($this->date($quizEnd)));
    }

    private function validatedAnswers(array $answers, Collection $questions): array
    {
        $allowed = $questions->keyBy(fn ($question) => (string) $question['Question_ID']);
        $normalized = [];
        foreach ($answers as $questionId => $answer) {
            $questionId = trim((string) $questionId);
            abort_unless($questionId !== '' && $allowed->has($questionId), 422, 'Jawaban memuat soal yang bukan bagian dari kuis ini.');
            $answer = strtoupper(trim((string) $answer));
            abort_unless(in_array($answer, ['A', 'B', 'C', 'D'], true), 422, 'Pilihan jawaban harus A, B, C, atau D.');
            $normalized[$questionId] = $answer;
        }
        ksort($normalized);

        return $normalized;
    }

    /** @return array{version: int, answers: array<string, string>} */
    private function answerState(array $attempt): array
    {
        $decoded = json_decode((string) ($attempt['Submitted_Answers_JSON'] ?? ''), true);
        if (! is_array($decoded)) {
            return ['version' => 0, 'answers' => []];
        }

        $version = max(0, (int) ($decoded['__quiz_answer_version'] ?? 0));
        $answers = $decoded;
        if (isset($decoded['answers']) && is_array($decoded['answers'])) {
            $version = max(0, (int) ($decoded['version'] ?? 0));
            $answers = $decoded['answers'];
        } else {
            unset($answers['__quiz_answer_version']);
        }

        $answers = collect($answers)->mapWithKeys(function ($answer, $questionId) {
            $answer = strtoupper(trim((string) $answer));

            return in_array($answer, ['A', 'B', 'C', 'D'], true)
                ? [trim((string) $questionId) => $answer]
                : [];
        })->filter(fn ($answer, $questionId) => $questionId !== '')->all();
        ksort($answers);

        return ['version' => $version, 'answers' => $answers];
    }

    private function encodeAnswerState(int $version, array $answers): string
    {
        ksort($answers);

        $answers['__quiz_answer_version'] = max(0, $version);

        return json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function withCacheLock(string $key, int $seconds, int $waitSeconds, callable $callback)
    {
        try {
            return Cache::lock($key, $seconds)->block($waitSeconds, $callback);
        } catch (LockTimeoutException) {
            abort(423, 'Operasi kuis lain masih diproses. Silakan coba kembali.');
        }
    }

    private function deleteChildrenAndVerify(object $repository, array $ids, string $quizId, string $label): void
    {
        if ($ids !== []) {
            $repository->hardDeleteMany($ids);
        }
        if (collect($repository->fetchAllFresh())->contains(
            fn ($row) => trim((string) ($row['Quiz_ID'] ?? '')) === trim($quizId)
        )) {
            throw new \RuntimeException(ucfirst($label)." {$quizId} belum terhapus sepenuhnya.");
        }
    }

    private function isEditable(array $quiz, ?bool $hasAttempt = null): bool
    {
        $hasAttempt ??= collect($this->attempts->fetchAll())->contains('Quiz_ID', $quiz['Quiz_ID']);

        return ! $hasAttempt && $this->periods->now()->lessThan($this->date($quiz['Start_At'])) && $this->lifecycle($quiz) !== 'ACTIVE';
    }

    private function isDeletable(array $quiz): bool
    {
        return trim((string) ($quiz['Quiz_ID'] ?? '')) !== '';
    }

    private function requireQuizFresh(string $id): array
    {
        $quiz = $this->quizzes->findByIdFresh($id);
        if (! $quiz && $this->state()->isTombstoned($id)) {
            abort(410, 'Kuis sudah dihapus permanen.');
        }

        return (array) ($quiz ?: abort(404, 'Kuis tidak ditemukan.'));
    }

    private function availableReplay(string $quizId, array $teacher, ?array $allowedClassIds = null): string
    {
        $quiz = $this->quizzes->findByIdFresh($quizId);
        if (! is_array($quiz) || $quiz === []) {
            throw new HttpException(410, 'Kuis dari operasi idempotensi ini sudah tidak tersedia.');
        }
        $this->assertTeacherOwns($quiz, (string) ($teacher['Teacher_ID'] ?? ''));
        $this->assertTeacherClassAllowed($quiz, $allowedClassIds);

        return $quizId;
    }

    private function assertTeacherOwns(array $quiz, string $teacherId): void
    {
        abort_unless(trim((string) ($quiz['Teacher_ID'] ?? '')) === trim($teacherId), 403);
    }

    private function assertTeacherClassAllowed(array $quiz, ?array $allowedClassIds): void
    {
        if ($allowedClassIds === null) {
            return;
        }

        abort_unless(
            $this->quizInClasses($quiz, $allowedClassIds),
            403,
            'Kuis berada di luar scope kelas Anda.'
        );
    }

    private function assertStudentEligible(array $quiz, array $student): void
    {
        abort_unless($this->audience()->includes($quiz, (string) ($student['Class_ID'] ?? '')), 403);
    }

    /** @param list<string> $classIds */
    private function quizInClasses(array $quiz, array $classIds): bool
    {
        $audience = collect($this->audience()->classIds($quiz))->filter()->unique();
        $allowed = collect($classIds)->map(fn ($id) => trim((string) $id))->filter()->unique();

        return $audience->isNotEmpty() && $audience->diff($allowed)->isEmpty();
    }

    private function audience(): QuizAudienceService
    {
        return $this->audiences ??= app(QuizAudienceService::class);
    }

    private function state(): QuizRuntimeStateService
    {
        return $this->runtimeState ??= app(QuizRuntimeStateService::class);
    }

    private function quizMutationLock(string $quizId): string
    {
        abort_if(trim($quizId) === '', 404, 'Kuis tidak ditemukan.');

        return 'quiz_mutation_'.hash('sha256', trim($quizId));
    }

    /** @return array{path:string,mime:string} */
    private function mediaFromQuestion(array $question, string $quizId, string $type): array
    {
        abort_unless(trim((string) ($question['Quiz_ID'] ?? '')) === trim($quizId), 404);
        abort_unless(in_array($type, ['image', 'audio'], true), 404);
        $prefix = ucfirst($type);
        $path = trim((string) ($question[$prefix.'_Path'] ?? ''));
        $mime = trim((string) ($question[$prefix.'_Mime'] ?? ''));
        abort_if($path === '' || $mime === '', 404, 'Media tidak ditemukan.');

        return compact('path', 'mime');
    }

    /**
     * Keep unit repository doubles lightweight while making production
     * multi-table quiz mutations atomic on the shared MariaDB connection.
     *
     * @param  list<array{0: object, 1: string}>  $locks
     */
    private function runtimeTransaction(object $coordinator, callable $callback, array $locks = [])
    {
        if (! $coordinator instanceof BaseMySqlRepository) {
            return $callback();
        }

        return $coordinator->transaction(function () use ($callback, $locks) {
            foreach ($locks as [$repository, $id]) {
                if ($repository instanceof BaseMySqlRepository && trim($id) !== '') {
                    $repository->lockById($id);
                }
            }

            return $callback();
        });
    }

    private function attemptId(string $quizId, string $studentId, int $generation = 0): string
    {
        $seed = max(0, $generation) === 0
            ? $quizId.'|'.$studentId
            : $quizId.'|'.$studentId.'|'.$generation;

        return 'QAT'.strtoupper(substr(hash('sha256', $seed), 0, 24));
    }

    private function questionId(string $quizId, int $index): string
    {
        return 'QQN'.strtoupper(substr(hash('sha256', $quizId.'|'.$index), 0, 20));
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
