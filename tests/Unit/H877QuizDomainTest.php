<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\QuizAttemptRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizResultRepositoryInterface;
use App\Services\Core\ActivityLogService;
use App\Services\Quiz\QuizCleanupService;
use App\Services\Quiz\QuizPeriodService;
use App\Services\Quiz\QuizService;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class H877QuizDomainTest extends TestCase
{
    private MemoryQuizRepository $quizzes;

    private MemoryQuestionRepository $questions;

    private MemoryAttemptRepository $attempts;

    private MemoryResultRepository $results;

    private QuizPeriodService $periods;

    private QuizService $service;

    private ActivityLogService $activityLog;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('quiz.period_anchor', '2026-10-01 00:00:00');
        config()->set('quiz.timezone', 'Asia/Jakarta');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 08:00:00', 'Asia/Jakarta'));
        $this->quizzes = new MemoryQuizRepository;
        $this->questions = new MemoryQuestionRepository;
        $this->attempts = new MemoryAttemptRepository;
        $this->results = new MemoryResultRepository;
        $this->periods = new QuizPeriodService;
        $this->activityLog = \Mockery::spy(ActivityLogService::class);
        $this->service = new QuizService($this->quizzes, $this->questions, $this->attempts, $this->results, $this->periods, $this->activityLog);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_global_fourteen_day_period_boundaries_are_half_open(): void
    {
        $p1 = $this->periods->periodFor('2026-10-01 00:00:00');
        $this->assertSame('2026-10-01 00:00:00', $p1['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 00:00:00', $p1['end']->format('Y-m-d H:i:s'));
        $this->assertTrue($this->periods->contains($p1, '2026-10-14 23:59:59'));
        $this->assertFalse($this->periods->contains($p1, '2026-10-15 00:00:00'));
        $this->assertSame('2026-10-15 00:00:00', $this->periods->periodFor('2026-10-15 00:00:00')['start']->format('Y-m-d H:i:s'));
    }

    public function test_exact_start_allowed_exact_end_closed_and_deadline_is_capped(): void
    {
        $this->seedQuiz('Q1', '2026-10-09 08:00:00', '2026-10-09 15:00:00', 30);
        $student = $this->student();
        $first = $this->service->start('Q1', $student);
        $second = $this->service->start('Q1', $student);
        $this->assertSame($first['attempt']['Attempt_ID'], $second['attempt']['Attempt_ID']);
        $this->assertCount(1, $this->attempts->rows);
        $this->assertSame('2026-10-09 08:30:00', $first['attempt']['Deadline_At']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 14:50:00', 'Asia/Jakarta'));
        $this->seedQuiz('Q2', '2026-10-09 08:00:00', '2026-10-09 15:00:00', 30);
        $this->assertSame('2026-10-09 15:00:00', $this->service->start('Q2', $student)['attempt']['Deadline_At']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 15:00:00', 'Asia/Jakarta'));
        $this->expectException(HttpException::class);
        $this->service->start('Q1', $student);
    }

    public function test_server_scoring_keeps_raw_points_and_compatibility_normalization_without_exposing_key(): void
    {
        $this->seedQuiz('Q1', points: [10, 15, 20, 5]);
        $payload = $this->service->start('Q1', $this->student());
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Correct_Option', $serialized);
        $ids = $payload['questions']->pluck('Question_ID')->all();
        $answers = [$ids[0] => 'A', $ids[1] => 'A', $ids[2] => 'B', $ids[3] => 'A'];
        $result = $this->service->submit($payload['attempt']['Attempt_ID'], $this->student(), $answers);
        $again = $this->service->submit($payload['attempt']['Attempt_ID'], $this->student(), $answers);
        $this->assertSame(30.0, (float) $result['Raw_Score']);
        $this->assertSame(50.0, (float) $result['Maximum_Score']);
        $this->assertSame(60.0, (float) $result['Normalized_Score']);
        $this->assertSame($result['Result_ID'], $again['Result_ID']);
        $this->assertCount(1, $this->results->rows);
    }

    public function test_one_question_and_multi_question_scores_are_exact_raw_points(): void
    {
        $this->seedQuiz('ONE-CORRECT', points: [15]);
        $correct = $this->service->start('ONE-CORRECT', $this->student());
        $correctResult = $this->service->submit($correct['attempt']['Attempt_ID'], $this->student(), [$correct['questions'][0]['Question_ID'] => 'A']);
        $this->assertSame(15.0, (float) $correctResult['Raw_Score']);
        $this->assertSame(15.0, (float) $correctResult['Maximum_Score']);

        $this->seedQuiz('ONE-WRONG', points: [15]);
        $wrong = $this->service->start('ONE-WRONG', $this->student());
        $wrongResult = $this->service->submit($wrong['attempt']['Attempt_ID'], $this->student(), [$wrong['questions'][0]['Question_ID'] => 'B']);
        $this->assertSame(0.0, (float) $wrongResult['Raw_Score']);
        $this->assertSame(15.0, (float) $wrongResult['Maximum_Score']);

        $multiStudent = ['Student_ID' => 'S2', 'Full_Name' => 'Budi', 'Class_ID' => 'C1'];
        $this->seedQuiz('MULTI', points: [15, 20, 25]);
        $multi = $this->service->start('MULTI', $multiStudent);
        $multiResult = $this->service->submit($multi['attempt']['Attempt_ID'], $multiStudent, [
            $multi['questions'][0]['Question_ID'] => 'A',
            $multi['questions'][1]['Question_ID'] => 'B',
            $multi['questions'][2]['Question_ID'] => 'A',
        ]);
        $this->assertSame(40.0, (float) $multiResult['Raw_Score']);
        $this->assertSame(60.0, (float) $multiResult['Maximum_Score']);
        $this->assertSame(66.6667, (float) $multiResult['Normalized_Score']);
    }

    public function test_student_result_renders_one_question_raw_points_as_primary_score(): void
    {
        $base = [
            'Quiz_Title' => 'Kanji Dasar',
            'Maximum_Score' => 15,
            'Normalized_Score' => 100,
        ];
        $viewData = [
            'questions' => collect(),
            'entries' => collect(),
            'period' => ['label' => '1–14 Oktober 2026'],
            'currentStudentId' => 'S1',
            'userRole' => 'STUDENT',
        ];

        $correct = view('quiz.student.result', ['result' => $base + ['Raw_Score' => 15]] + $viewData)->render();
        $wrong = view('quiz.student.result', ['result' => $base + ['Raw_Score' => 0]] + $viewData)->render();

        $this->assertStringContainsString('15 Poin', $correct);
        $this->assertStringContainsString('15 dari 15 poin', $correct);
        $this->assertStringContainsString('0 Poin', $wrong);
        $this->assertStringContainsString('0 dari 15 poin', $wrong);
        $this->assertStringNotContainsString('>100<', $correct);
        $this->assertStringNotContainsString('>100<', $wrong);
    }

    public function test_quiz_attempt_and_result_idor_fail_closed(): void
    {
        $this->seedQuiz('Q1', points: [10]);
        try {
            $this->service->start('Q1', ['Student_ID' => 'S2', 'Full_Name' => 'Luar', 'Class_ID' => 'C2']);
            $this->fail('Cross-class quiz access should be forbidden.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $attempt = $this->service->start('Q1', $this->student())['attempt'];
        try {
            $this->service->resume($attempt['Attempt_ID'], ['Student_ID' => 'S2', 'Class_ID' => 'C1']);
            $this->fail('Attempt IDOR should be forbidden.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $result = $this->service->submit($attempt['Attempt_ID'], $this->student(), []);
        $this->expectException(HttpException::class);
        $this->service->review($result['Result_ID'], ['Student_ID' => 'S2', 'Class_ID' => 'C1']);
    }

    public function test_other_teacher_cannot_read_or_mutate_quiz_and_started_quiz_is_locked(): void
    {
        $this->seedQuiz('Q1', '2026-10-10 08:00:00', '2026-10-10 09:00:00', 30, [10]);
        try {
            $this->service->teacherQuiz('Q1', 'T2');
            $this->fail('Another teacher must not read the quiz.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 08:00:00', 'Asia/Jakarta'));
        try {
            $this->service->update('Q1', ['Class_ID' => 'C1', 'Title' => 'Forged', 'Start_At' => '2026-10-10 08:00:00', 'End_At' => '2026-10-10 09:00:00', 'Duration_Minutes' => 30, 'intent' => 'publish', 'questions' => []], ['Teacher_ID' => 'T1'], 'U1');
            $this->fail('Active quiz content must be locked.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame('Kuis Q1', $this->quizzes->rows[0]['Title']);
    }

    public function test_rendered_student_player_contains_no_answer_key_metadata(): void
    {
        $this->seedQuiz('Q1', points: [10]);
        $payload = $this->service->start('Q1', $this->student());
        $html = view('quiz.student.player', $payload + ['userRole' => 'STUDENT'])->render();
        $this->assertStringNotContainsString('Correct_Option', $html);
        $this->assertStringNotContainsString('correct_option', strtolower($html));
        $this->assertStringNotContainsString('is_correct', strtolower($html));
    }

    public function test_client_clock_or_late_payload_cannot_earn_points(): void
    {
        $this->seedQuiz('Q1', duration: 30, points: [10]);
        $attempt = $this->service->start('Q1', $this->student())['attempt'];
        CarbonImmutable::setTestNow(CarbonImmutable::parse($attempt['Deadline_At'], 'Asia/Jakarta'));
        $id = $this->questions->rows[0]['Question_ID'];
        $result = $this->service->submit($attempt['Attempt_ID'], $this->student(), [$id => 'A']);
        $this->assertSame(0.0, (float) $result['Raw_Score']);
        $this->assertSame('EXPIRED', $this->attempts->rows[0]['Status']);
    }

    public function test_review_is_available_before_but_not_at_exact_four_minute_boundary(): void
    {
        $this->seedQuiz('Q1', points: [10]);
        $attempt = $this->service->start('Q1', $this->student())['attempt'];
        $result = $this->service->submit($attempt['Attempt_ID'], $this->student(), []);
        CarbonImmutable::setTestNow(CarbonImmutable::parse($result['Completed_At'], 'Asia/Jakarta')->addMinutes(4)->subSecond());
        $review = $this->service->review($result['Result_ID'], $this->student());
        $this->assertArrayNotHasKey('Correct_Option', $review['questions'][0]);
        $this->assertArrayNotHasKey('is_correct', $review['questions'][0]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse($result['Completed_At'], 'Asia/Jakarta')->addMinutes(4));
        $this->expectException(HttpException::class);
        $this->service->review($result['Result_ID'], $this->student());
    }

    public function test_leaderboard_uses_raw_points_class_period_and_competition_ties(): void
    {
        $this->results->rows = [
            $this->resultRow('R1', 'S1', 'Aiko', 'C1', 15, 20, 75, '2026-10-02 10:00:00'),
            $this->resultRow('R2', 'S1', 'Aiko', 'C1', 40, 100, 40, '2026-10-03 10:00:00'),
            $this->resultRow('R3', 'S2', 'Budi', 'C1', 40, 50, 80, '2026-10-04 10:00:00'),
            $this->resultRow('R4', 'S3', 'Cici', 'C1', 40, 200, 20, '2026-10-04 10:00:00'),
            $this->resultRow('R7', 'S5', 'Dedi', 'C1', 10, 10, 100, '2026-10-04 10:00:00'),
            $this->resultRow('R5', 'S4', 'Luar', 'C2', 100, 100, 100, '2026-10-04 10:00:00'),
            $this->resultRow('R6', 'S1', 'Aiko', 'C1', 100, 100, 100, '2026-10-15 00:00:00'),
        ];
        $board = $this->service->leaderboard('C1', CarbonImmutable::parse('2026-10-10', 'Asia/Jakarta'));
        $this->assertSame([1, 2, 2, 4], $board['entries']->pluck('Rank')->all());
        $this->assertSame([55.0, 40.0, 40.0, 10.0], $board['entries']->pluck('Points')->all());
        $this->assertNotContains('S4', $board['entries']->pluck('Student_ID')->all());
        $next = $this->service->leaderboard('C1', CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
        $this->assertSame(100.0, $next['entries'][0]['Points']);
    }

    public function test_dashboard_uses_bulk_metadata_only_and_shared_raw_point_ranking(): void
    {
        $this->quizzes->rows = [
            ['Quiz_ID' => 'ACTIVE', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Kuis Aktif', 'Start_At' => '2026-10-09 07:00:00', 'End_At' => '2026-10-09 09:00:00', 'Duration_Minutes' => 30, 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'UPCOMING', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Kuis Besok', 'Start_At' => '2026-10-10 07:00:00', 'End_At' => '2026-10-10 09:00:00', 'Duration_Minutes' => 30, 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'EXPIRED', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Kuis Lama', 'Start_At' => '2026-10-08 07:00:00', 'End_At' => '2026-10-08 09:00:00', 'Duration_Minutes' => 30, 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'DONE', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Sudah Selesai', 'Start_At' => '2026-10-09 07:00:00', 'End_At' => '2026-10-09 09:00:00', 'Duration_Minutes' => 30, 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'FOREIGN', 'Teacher_ID' => 'T1', 'Class_ID' => 'C2', 'Title' => 'Kelas Lain', 'Start_At' => '2026-10-09 07:00:00', 'End_At' => '2026-10-09 09:00:00', 'Duration_Minutes' => 30, 'Status' => 'PUBLISHED'],
        ];
        $this->attempts->rows[] = ['Attempt_ID' => 'A-ACTIVE', 'Quiz_ID' => 'ACTIVE', 'Student_ID' => 'S1'];
        $this->results->rows = [
            ['Quiz_ID' => 'DONE'] + $this->resultRow('R1', 'S1', 'Aiko', 'C1', 10, 10, 100, '2026-10-09 07:30:00'),
            $this->resultRow('R2', 'S2', 'Budi', 'C1', 30, 100, 30, '2026-10-08 07:30:00'),
            $this->resultRow('R3', 'S3', 'Cici', 'C1', 20, 20, 100, '2026-10-08 07:30:00'),
            $this->resultRow('R4', 'S4', 'Dedi', 'C1', 15, 100, 15, '2026-10-08 07:30:00'),
        ];

        $payload = $this->service->studentDashboard($this->student());

        $this->assertSame(['ACTIVE'], $payload['available']->pluck('Quiz_ID')->all());
        $this->assertSame('A-ACTIVE', $payload['available'][0]['Attempt_ID']);
        $this->assertSame(['UPCOMING'], $payload['upcoming']->pluck('Quiz_ID')->all());
        $this->assertSame([30.0, 20.0, 15.0], $payload['leaderboard']['top']->pluck('Points')->all());
        $this->assertSame(4, $payload['leaderboard']['current']['Rank']);
        $this->assertSame(10.0, $payload['leaderboard']['current']['Points']);
        $this->assertSame(0, $this->questions->fetchAllCalls + $this->questions->fetchAllFreshCalls);
        $this->assertSame(1, $this->quizzes->fetchAllCalls);
        $this->assertSame(1, $this->attempts->fetchAllCalls);
        $this->assertSame(1, $this->results->fetchAllCalls);
        $this->assertStringNotContainsString('Correct_Option', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_teacher_class_landing_uses_only_quiz_snapshot_and_keeps_empty_authorized_class(): void
    {
        $this->quizzes->rows = [
            ['Quiz_ID' => 'ACTIVE', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Start_At' => '2026-10-09 07:00:00', 'End_At' => '2026-10-09 09:00:00', 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'UPCOMING', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Start_At' => '2026-10-10 07:00:00', 'End_At' => '2026-10-10 09:00:00', 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'COMPLETED', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Start_At' => '2026-10-08 07:00:00', 'End_At' => '2026-10-08 09:00:00', 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'FOREIGN-CLASS', 'Teacher_ID' => 'T1', 'Class_ID' => 'C3', 'Start_At' => '2026-10-09 07:00:00', 'End_At' => '2026-10-09 09:00:00', 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'FOREIGN-TEACHER', 'Teacher_ID' => 'T2', 'Class_ID' => 'C1', 'Start_At' => '2026-10-09 07:00:00', 'End_At' => '2026-10-09 09:00:00', 'Status' => 'PUBLISHED'],
        ];

        $cards = $this->service->teacherClassIndex('T1', [
            ['Class_ID' => 'C1', 'Class_Name' => 'Kelas A'],
            ['Class_ID' => 'C2', 'Class_Name' => 'Kelas B'],
        ], collect(['C1' => 28, 'C2' => 17]));

        $this->assertSame(['C1', 'C2'], $cards->pluck('Class_ID')->all());
        $this->assertSame([1, 1, 1, 3, 28], array_values(collect($cards[0])->only(['Active_Quiz_Count', 'Upcoming_Quiz_Count', 'Completed_Quiz_Count', 'Quiz_Count', 'Student_Count'])->all()));
        $this->assertSame(0, $cards[1]['Quiz_Count']);
        $this->assertSame(17, $cards[1]['Student_Count']);
        $this->assertSame(1, $this->quizzes->fetchAllCalls);
        $this->assertSame(0, $this->questions->fetchAllCalls);
        $this->assertSame(0, $this->attempts->fetchAllCalls);
        $this->assertSame(0, $this->results->fetchAllCalls);
    }

    public function test_teacher_class_hub_is_class_isolated_bounded_and_has_no_n_plus_one_reads(): void
    {
        for ($index = 1; $index <= 14; $index++) {
            $this->quizzes->rows[] = ['Quiz_ID' => 'DONE-'.$index, 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Kuis '.$index, 'Start_At' => '2026-10-08 07:00:00', 'End_At' => '2026-10-08 09:00:00', 'Duration_Minutes' => 30, 'Status' => 'PUBLISHED'];
            $this->questions->rows[] = ['Question_ID' => 'QUESTION-'.$index, 'Quiz_ID' => 'DONE-'.$index];
        }
        $this->quizzes->rows[] = ['Quiz_ID' => 'CLASS-B', 'Teacher_ID' => 'T1', 'Class_ID' => 'C2', 'Title' => 'Rahasia Kelas B', 'Start_At' => '2026-10-08 07:00:00', 'End_At' => '2026-10-08 09:00:00', 'Duration_Minutes' => 30, 'Status' => 'PUBLISHED'];
        $this->results->rows = [
            ['Quiz_ID' => 'DONE-1'] + $this->resultRow('R1', 'S1', 'Aiko', 'C1', 55, 100, 55, '2026-10-08 08:00:00'),
            ['Quiz_ID' => 'CLASS-B'] + $this->resultRow('R2', 'S2', 'Budi', 'C2', 99, 100, 99, '2026-10-08 08:00:00'),
        ];

        $hub = $this->service->teacherClassHub('T1', 'C1', [
            ['Student_ID' => 'S1'], ['Student_ID' => 'S3'],
        ]);

        $this->assertSame(14, $hub['quiz_count']);
        $this->assertSame(14, $hub['group_totals']['completed']);
        $this->assertCount(12, $hub['groups']['completed']);
        $this->assertSame(2, $hub['summary']['students']);
        $this->assertSame(['S1'], $hub['leaderboard']['entries']->pluck('Student_ID')->all());
        $this->assertStringNotContainsString('Rahasia Kelas B', json_encode($hub, JSON_THROW_ON_ERROR));
        $this->assertSame(1, $this->quizzes->fetchAllCalls);
        $this->assertSame(1, $this->questions->fetchAllCalls);
        $this->assertSame(1, $this->results->fetchAllCalls);
        $this->assertSame(0, $this->attempts->fetchAllCalls);
    }

    public function test_completed_quiz_delete_cascades_exact_graph_and_updates_leaderboard(): void
    {
        $this->seedQuiz('Q1', '2026-10-08 08:00:00', '2026-10-08 09:00:00', 30, [5, 5, 5]);
        $this->seedQuiz('Q2', '2026-10-08 08:00:00', '2026-10-08 09:00:00', 30, [40]);
        $this->attempts->rows = [
            ['Attempt_ID' => 'A1', 'Quiz_ID' => 'Q1', 'Student_ID' => 'S1'],
            ['Attempt_ID' => 'A2', 'Quiz_ID' => 'Q1', 'Student_ID' => 'S2'],
            ['Attempt_ID' => 'A3', 'Quiz_ID' => 'Q2', 'Student_ID' => 'S1'],
        ];
        $this->results->rows = [
            ['Quiz_ID' => 'Q1', 'Attempt_ID' => 'A1'] + $this->resultRow('R1', 'S1', 'Aiko', 'C1', 15, 15, 100, '2026-10-08 08:30:00'),
            ['Quiz_ID' => 'Q1', 'Attempt_ID' => 'A2'] + $this->resultRow('R2', 'S2', 'Budi', 'C1', 5, 15, 33.3333, '2026-10-08 08:30:00'),
            ['Quiz_ID' => 'Q2', 'Attempt_ID' => 'A3'] + $this->resultRow('R3', 'S1', 'Aiko', 'C1', 40, 40, 100, '2026-10-08 08:30:00'),
        ];
        $this->assertSame(55.0, $this->service->leaderboard('C1')['entries']->firstWhere('Student_ID', 'S1')['Points']);

        $deleted = $this->service->deleteQuiz('Q1', 'T1', ['C1'], 'U1');

        $this->assertSame(['quiz' => 1, 'questions' => 3, 'attempts' => 2, 'results' => 2], $deleted['deleted']);
        $this->assertFalse(collect($this->quizzes->rows)->contains('Quiz_ID', 'Q1'));
        $this->assertFalse(collect($this->questions->rows)->contains('Quiz_ID', 'Q1'));
        $this->assertFalse(collect($this->attempts->rows)->contains('Quiz_ID', 'Q1'));
        $this->assertFalse(collect($this->results->rows)->contains('Quiz_ID', 'Q1'));
        $this->assertTrue(collect($this->quizzes->rows)->contains('Quiz_ID', 'Q2'));
        $this->assertSame(40.0, $this->service->leaderboard('C1')['entries']->firstWhere('Student_ID', 'S1')['Points']);
        $this->activityLog->shouldHaveReceived('logAction')->once()->withArgs(function (...$arguments) {
            $serialized = json_encode($arguments, JSON_THROW_ON_ERROR);

            return $arguments[1] === 'DELETE'
                && $arguments[2] === 'QUIZ'
                && $arguments[5] === ['Title' => 'Kuis Q1', 'Class_ID' => 'C1']
                && ! str_contains($serialized, 'Correct_Option')
                && ! str_contains($serialized, 'Raw_Score')
                && ! str_contains($serialized, 'Student_Name');
        });

        try {
            $this->service->deleteQuiz('Q1', 'T1', ['C1'], 'U1');
            $this->fail('Deleting an already deleted quiz must return not found.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertTrue(collect($this->quizzes->rows)->contains('Quiz_ID', 'Q2'));
    }

    public function test_quiz_delete_revalidates_owner_and_class_under_lock(): void
    {
        $this->seedQuiz('Q1', points: [10]);
        foreach ([['T2', ['C1']], ['T1', ['C2']]] as [$teacherId, $classes]) {
            try {
                $this->service->deleteQuiz('Q1', $teacherId, $classes, 'U1');
                $this->fail('Unauthorized quiz deletion must be forbidden.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertTrue(collect($this->quizzes->rows)->contains('Quiz_ID', 'Q1'));
        }
    }

    public function test_failed_child_verification_stops_before_attempt_question_and_parent_delete(): void
    {
        $quizzes = new MemoryQuizRepository;
        $questions = new MemoryQuestionRepository;
        $attempts = new MemoryAttemptRepository;
        $results = new FailingDeleteResultRepository;
        $quizzes->rows[] = ['Quiz_ID' => 'Q1', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Protected'];
        $questions->rows[] = ['Question_ID' => 'QQ1', 'Quiz_ID' => 'Q1'];
        $attempts->rows[] = ['Attempt_ID' => 'QA1', 'Quiz_ID' => 'Q1'];
        $results->rows[] = ['Result_ID' => 'QR1', 'Quiz_ID' => 'Q1'];
        $activityLog = \Mockery::mock(ActivityLogService::class);
        $activityLog->shouldReceive('logAction')->never();
        $service = new QuizService($quizzes, $questions, $attempts, $results, $this->periods, $activityLog);

        try {
            $service->deleteQuiz('Q1', 'T1', ['C1'], 'U1');
            $this->fail('A failed child verification must stop deletion.');
        } catch (\RuntimeException) {
            $this->assertTrue(collect($quizzes->rows)->contains('Quiz_ID', 'Q1'));
            $this->assertTrue(collect($questions->rows)->contains('Quiz_ID', 'Q1'));
            $this->assertTrue(collect($attempts->rows)->contains('Quiz_ID', 'Q1'));
        }
    }

    public function test_closed_period_cleanup_removes_content_and_attempt_but_preserves_result_and_is_idempotent(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 00:10:00', 'Asia/Jakarta'));
        $this->seedQuiz('OLD', '2026-10-02 08:00:00', '2026-10-02 09:00:00', 30, [10]);
        $this->attempts->rows[] = ['Attempt_ID' => 'A1', 'Quiz_ID' => 'OLD', 'Student_ID' => 'S1', 'Started_At' => '2026-10-02 08:00:00', 'Deadline_At' => '2026-10-02 08:30:00', 'Status' => 'SUBMITTED', 'Submitted_Answers_JSON' => '{}', 'Submitted_At' => '2026-10-02 08:10:00', 'Created_At' => '', 'Updated_At' => ''];
        $this->results->rows[] = ['Quiz_ID' => 'OLD', 'Attempt_ID' => 'A1'] + $this->resultRow('R1', 'S1', 'Aiko', 'C1', 10, 10, 100, '2026-10-02 08:10:00');
        $cleanup = new QuizCleanupService($this->quizzes, $this->questions, $this->attempts, $this->results, $this->periods);
        $first = $cleanup->run();
        $second = $cleanup->run();
        $this->assertSame(['quizzes' => 1, 'questions' => 1, 'attempts' => 1, 'results_created' => 0], $first);
        $this->assertSame(0, $second['quizzes']);
        $this->assertCount(1, $this->results->rows);
        $this->assertSame('Aiko', $this->results->rows[0]['Student_Name']);
        $this->assertSame(100.0, $this->results->rows[0]['Normalized_Score']);
    }

    public function test_cleanup_protects_current_future_and_unexpired_review_content(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 00:02:00', 'Asia/Jakarta'));
        $this->seedQuiz('CURRENT', '2026-10-15 08:00:00', '2026-10-15 09:00:00', 30, [10]);
        $this->seedQuiz('REVIEW', '2026-10-02 08:00:00', '2026-10-02 09:00:00', 30, [10]);
        $this->results->rows[] = ['Quiz_ID' => 'REVIEW', 'Attempt_ID' => 'AR'] + $this->resultRow('RR', 'S1', 'Aiko', 'C1', 10, 10, 100, '2026-10-14 23:59:00');
        $cleanup = new QuizCleanupService($this->quizzes, $this->questions, $this->attempts, $this->results, $this->periods);
        $this->assertSame(0, $cleanup->run()['quizzes']);
        $this->assertCount(2, $this->quizzes->rows);
    }

    public function test_three_questions_persist_all_options_and_edit_keeps_each_question_independent(): void
    {
        $quizId = $this->service->create([
            'Class_ID' => 'C1',
            'Title' => 'Kosakata',
            'Start_At' => '2026-10-10 08:00:00',
            'End_At' => '2026-10-10 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'draft',
            'questions' => [
                $this->question('Satu', 'A1', 'B1', 'C1', 'D1', 'A', 10),
                $this->question('Dua', 'A2', 'B2', 'C2', 'D2', 'B', 15),
                $this->question('Tiga', 'A3', 'B3', 'C3', 'D3', 'C', 20),
            ],
        ], ['Teacher_ID' => 'T1'], 'U1');

        $persisted = collect($this->questions->rows)->where('Quiz_ID', $quizId)->sortBy('Sort_Order')->values();
        $this->assertSame(['A1', 'A2', 'A3'], $persisted->pluck('Option_A')->all());
        $this->assertSame(['B1', 'B2', 'B3'], $persisted->pluck('Option_B')->all());
        $this->assertSame(['C1', 'C2', 'C3'], $persisted->pluck('Option_C')->all());
        $this->assertSame(['D1', 'D2', 'D3'], $persisted->pluck('Option_D')->all());
        $this->assertSame(['A', 'B', 'C'], $persisted->pluck('Correct_Option')->all());
        $this->assertSame([10.0, 15.0, 20.0], $persisted->pluck('Point')->all());

        $this->service->update($quizId, [
            'Class_ID' => 'C1',
            'Title' => 'Kosakata Edit',
            'Start_At' => '2026-10-10 08:00:00',
            'End_At' => '2026-10-10 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'draft',
            'questions' => [
                $this->question('Satu', 'A1', 'B1', 'C1', 'D1', 'A', 10),
                $this->question('Tiga', 'A3', 'B3', 'C3', 'D3', 'C', 20),
            ],
        ], ['Teacher_ID' => 'T1'], 'U1', ['C1']);

        $edited = collect($this->questions->rows)->where('Quiz_ID', $quizId)->sortBy('Sort_Order')->values();
        $this->assertSame(['Satu', 'Tiga'], $edited->pluck('Question_Text')->all());
        $this->assertSame(['A1', 'A3'], $edited->pluck('Option_A')->all());
        $this->assertSame(['D1', 'D3'], $edited->pluck('Option_D')->all());
    }

    public function test_service_rejects_empty_unbounded_and_nonsequential_question_payloads_before_write(): void
    {
        $base = [
            'Class_ID' => 'C1',
            'Title' => 'Payload Guard',
            'Start_At' => '2026-10-10 08:00:00',
            'End_At' => '2026-10-10 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'draft',
        ];
        $question = $this->question('Satu', 'A', 'B', 'C', 'D', 'A', 10);
        $invalidPayloads = [
            'empty' => [],
            'above maximum' => array_fill(0, 101, $question),
            'nonsequential' => [1 => $question],
        ];

        foreach ($invalidPayloads as $label => $questions) {
            try {
                $this->service->create($base + ['questions' => $questions], ['Teacher_ID' => 'T1'], 'U1');
                $this->fail("{$label} question payload must be rejected.");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertCount(0, $this->quizzes->rows);
        $this->assertCount(0, $this->questions->rows);
    }

    public function test_draft_and_publish_keep_question_count_order_and_unique_identity(): void
    {
        $payload = fn (string $intent, int $count) => [
            'Class_ID' => 'C1',
            'Title' => ucfirst($intent).' Count',
            'Start_At' => '2026-10-10 08:00:00',
            'End_At' => '2026-10-10 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => $intent,
            'questions' => collect(range(1, $count))->map(fn (int $index) => $this->question(
                'Pertanyaan '.$index,
                'A'.$index,
                'B'.$index,
                'C'.$index,
                'D'.$index,
                'A',
                10,
            ))->all(),
        ];

        $draftId = $this->service->create($payload('draft', 1), ['Teacher_ID' => 'T1'], 'U1');
        $publishedId = $this->service->create($payload('publish', 20), ['Teacher_ID' => 'T1'], 'U1');
        $publishedQuestions = collect($this->questions->rows)
            ->where('Quiz_ID', $publishedId)
            ->sortBy('Sort_Order')
            ->values();

        $this->assertSame('DRAFT', collect($this->quizzes->rows)->firstWhere('Quiz_ID', $draftId)['Status']);
        $this->assertSame('PUBLISHED', collect($this->quizzes->rows)->firstWhere('Quiz_ID', $publishedId)['Status']);
        $this->assertCount(1, collect($this->questions->rows)->where('Quiz_ID', $draftId));
        $this->assertCount(20, $publishedQuestions);
        $this->assertSame(range(1, 20), $publishedQuestions->pluck('Sort_Order')->all());
        $this->assertSame(range(1, 20), $publishedQuestions->pluck('Question_Text')->map(
            fn (string $text) => (int) str_replace('Pertanyaan ', '', $text)
        )->all());
        $this->assertCount(20, $publishedQuestions->pluck('Question_ID')->unique());
    }

    public function test_teacher_results_are_grouped_by_quiz_and_strictly_isolated_by_class(): void
    {
        $this->quizzes->rows = [
            ['Quiz_ID' => 'QA', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Quiz A', 'Start_At' => '2026-10-02 08:00:00'],
            ['Quiz_ID' => 'QB', 'Teacher_ID' => 'T1', 'Class_ID' => 'C2', 'Title' => 'Quiz B', 'Start_At' => '2026-10-02 08:00:00'],
        ];
        $this->results->rows = [
            ['Quiz_ID' => 'QA'] + $this->resultRow('RA', 'SA', 'Andi', 'C1', 8, 10, 80, '2026-10-02 09:00:00'),
            ['Quiz_ID' => 'QB'] + $this->resultRow('RB', 'SB', 'Budi', 'C2', 9, 10, 90, '2026-10-02 09:00:00'),
        ];
        $roster = [
            ['Student_ID' => 'SA', 'Full_Name' => 'Andi'],
            ['Student_ID' => 'SC', 'Full_Name' => 'Citra'],
        ];

        $overview = $this->service->teacherResults('T1', 'C1', $roster);

        $this->assertCount(1, $overview['groups']);
        $this->assertSame('QA', $overview['groups'][0]['quiz_id']);
        $this->assertSame(['SA'], $overview['groups'][0]['results']->pluck('Student_ID')->all());
        $this->assertSame(['SC'], $overview['groups'][0]['not_completed']->pluck('Student_ID')->all());
        $this->assertSame(1, $overview['groups'][0]['completed_count']);
        $this->assertSame(1, $overview['groups'][0]['not_completed_count']);
        $this->assertSame(8.0, $overview['groups'][0]['average']);

        $classB = $this->service->leaderboard('C2', CarbonImmutable::parse('2026-10-10', 'Asia/Jakarta'));
        $this->assertSame(['SB'], $classB['entries']->pluck('Student_ID')->all());
        $this->assertSame(9.0, $classB['entries'][0]['Points']);
    }

    public function test_teacher_result_detail_rejects_result_from_unauthorized_class(): void
    {
        $this->results->rows[] = $this->resultRow('R-FOREIGN', 'S2', 'Budi', 'C2', 10, 10, 100, '2026-10-02 09:00:00');

        try {
            $this->service->teacherResult('R-FOREIGN', 'T1', ['C1']);
            $this->fail('Foreign class result must be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_teacher_quiz_detail_rejects_quiz_from_no_longer_authorized_class(): void
    {
        $this->seedQuiz('Q-FOREIGN', points: [10]);

        try {
            $this->service->teacherQuiz('Q-FOREIGN', 'T1', ['C2']);
            $this->fail('Quiz outside active schedule classes must be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function question(string $text, string $a, string $b, string $c, string $d, string $correct, float $point): array
    {
        return [
            'Question_Text' => $text,
            'Option_A' => $a,
            'Option_B' => $b,
            'Option_C' => $c,
            'Option_D' => $d,
            'Correct_Option' => $correct,
            'Point' => $point,
        ];
    }

    private function seedQuiz(string $id, string $start = '2026-10-09 08:00:00', string $end = '2026-10-09 15:00:00', int $duration = 30, array $points = [10]): void
    {
        $this->quizzes->rows[] = ['Quiz_ID' => $id, 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Kuis '.$id, 'Start_At' => $start, 'End_At' => $end, 'Duration_Minutes' => $duration, 'Status' => 'PUBLISHED'];
        foreach ($points as $i => $point) {
            $this->questions->rows[] = ['Question_ID' => $id.'Q'.$i, 'Quiz_ID' => $id, 'Question_Text' => 'Pertanyaan '.$i, 'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D', 'Correct_Option' => 'A', 'Point' => $point, 'Sort_Order' => $i + 1];
        }
    }

    private function student(): array
    {
        return ['Student_ID' => 'S1', 'Full_Name' => 'Aiko', 'Class_ID' => 'C1', 'Is_Active' => 'TRUE'];
    }

    private function resultRow(string $id, string $student, string $name, string $class, float $raw, float $max, float $normalized, string $completed): array
    {
        return ['Result_ID' => $id, 'Quiz_ID' => 'Q', 'Quiz_Title' => 'Kuis', 'Attempt_ID' => 'A'.$id, 'Student_ID' => $student, 'Student_Name' => $name, 'Class_ID' => $class, 'Teacher_ID' => 'T1', 'Raw_Score' => $raw, 'Maximum_Score' => $max, 'Normalized_Score' => $normalized, 'Started_At' => $completed, 'Completed_At' => $completed, 'Created_At' => $completed];
    }
}

abstract class MemoryRows
{
    public array $rows = [];

    public int $fetchAllCalls = 0;

    public int $fetchAllFreshCalls = 0;

    protected string $key;

    public function fetchAll()
    {
        $this->fetchAllCalls++;

        return collect($this->rows);
    }

    public function fetchAllFresh()
    {
        $this->fetchAllFreshCalls++;

        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere($this->key, $id);
    }

    public function findByIdFresh($id)
    {
        return $this->findById((string) $id);
    }

    public function create(array $data)
    {
        if ($this->findById((string) $data[$this->key])) {
            return true;
        } $this->rows[] = $data;

        return $data;
    }

    public function update(string $id, array $data)
    {
        foreach ($this->rows as &$row) {
            if ($row[$this->key] === $id) {
                $row = array_merge($row, $data);

                return true;
            }
        }

        return false;
    }

    public function hardDeleteMany(array $ids): int
    {
        $before = count($this->rows);
        $this->rows = array_values(array_filter($this->rows, fn ($r) => ! in_array($r[$this->key], $ids, true)));

        return $before - count($this->rows);
    }
}
class MemoryQuizRepository extends MemoryRows implements QuizRepositoryInterface
{
    protected string $key = 'Quiz_ID';
}
class MemoryQuestionRepository extends MemoryRows implements QuizQuestionRepositoryInterface
{
    protected string $key = 'Question_ID';
}
class MemoryAttemptRepository extends MemoryRows implements QuizAttemptRepositoryInterface
{
    protected string $key = 'Attempt_ID';
}
class MemoryResultRepository extends MemoryRows implements QuizResultRepositoryInterface
{
    protected string $key = 'Result_ID';
}
class FailingDeleteResultRepository extends MemoryResultRepository
{
    public function hardDeleteMany(array $ids): int
    {
        return 0;
    }
}
