<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\QuizAttemptRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizResultRepositoryInterface;
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
        $this->service = new QuizService($this->quizzes, $this->questions, $this->attempts, $this->results, $this->periods);
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

    public function test_server_scoring_is_normalized_submission_is_idempotent_and_key_never_enters_player(): void
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

    public function test_leaderboard_uses_normalized_results_class_period_and_competition_ties(): void
    {
        $this->results->rows = [
            $this->resultRow('R1', 'S1', 'Aiko', 'C1', 40, 50, 80, '2026-10-02 10:00:00'),
            $this->resultRow('R2', 'S1', 'Aiko', 'C1', 160, 200, 80, '2026-10-03 10:00:00'),
            $this->resultRow('R3', 'S2', 'Budi', 'C1', 160, 200, 160, '2026-10-04 10:00:00'),
            $this->resultRow('R4', 'S3', 'Cici', 'C1', 90, 100, 90, '2026-10-04 10:00:00'),
            $this->resultRow('R5', 'S4', 'Luar', 'C2', 100, 100, 100, '2026-10-04 10:00:00'),
            $this->resultRow('R6', 'S1', 'Aiko', 'C1', 100, 100, 100, '2026-10-15 00:00:00'),
        ];
        $board = $this->service->leaderboard('C1', CarbonImmutable::parse('2026-10-10', 'Asia/Jakarta'));
        $this->assertSame([1, 1, 3], $board['entries']->pluck('Rank')->all());
        $this->assertSame([160.0, 160.0, 90.0], $board['entries']->pluck('Points')->all());
        $this->assertNotContains('S4', $board['entries']->pluck('Student_ID')->all());
        $next = $this->service->leaderboard('C1', CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
        $this->assertSame(100.0, $next['entries'][0]['Points']);
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
        $this->assertSame(80.0, $overview['groups'][0]['average']);

        $classB = $this->service->leaderboard('C2', CarbonImmutable::parse('2026-10-10', 'Asia/Jakarta'));
        $this->assertSame(['SB'], $classB['entries']->pluck('Student_ID')->all());
        $this->assertSame(90.0, $classB['entries'][0]['Points']);
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

    protected string $key;

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function fetchAllFresh()
    {
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
