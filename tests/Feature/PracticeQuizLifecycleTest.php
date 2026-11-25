<?php

namespace Tests\Feature;

use App\Services\PracticeQuiz\PracticeQuizMediaService;
use App\Services\PracticeQuiz\PracticeQuizService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PracticeQuizLifecycleTest extends TestCase
{
    private PracticeQuizService $service;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-24 08:00:00', 'Asia/Jakarta'));
        $migration = require database_path('migrations/wms_mysql/2026_11_24_000001_create_practice_quiz_module.php');
        $migration->up();
        Schema::create('MASTER_TEACHER', function (Blueprint $blueprint): void {
            $blueprint->string('Teacher_ID')->primary();
            $blueprint->string('Full_Name');
        });
        DB::table('MASTER_TEACHER')->insert(['Teacher_ID' => 'T1', 'Full_Name' => 'Sensei Satu']);

        foreach (['QUIZ_ATTEMPT', 'QUIZ_RESULT', 'MASTER_SCORE', 'QUIZ_RANKING'] as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->string('ID')->primary();
                $blueprint->string('Value');
            });
            DB::table($table)->insert(['ID' => 'SENTINEL', 'Value' => 'UNCHANGED']);
        }

        $media = Mockery::mock(PracticeQuizMediaService::class);
        $media->shouldReceive('prepare')->andReturnUsing(function (string $quiz, string $revision, array $questions): array {
            return [
                'questions' => collect($questions)->map(fn (array $question): array => $question + [
                    'Image_Path' => null, 'Image_Mime' => null, 'Image_Size_Bytes' => null,
                    'Audio_Path' => null, 'Audio_Mime' => null, 'Audio_Size_Bytes' => null,
                    'Audio_Duration_Milliseconds' => null,
                ])->all(),
                'new_paths' => [], 'tokens' => [], 'locks' => [],
            ];
        });
        $media->shouldReceive('commit')->andReturnNull();
        $media->shouldReceive('rollback')->zeroOrMoreTimes()->andReturnNull();
        $this->service = new PracticeQuizService($media);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_unlimited_attempts_are_distinct_while_start_and_submit_are_idempotent(): void
    {
        $quizId = $this->publish();
        $student = $this->student();

        $first = $this->service->start($quizId, $student);
        $same = $this->service->start($quizId, $student);
        $this->assertSame($first['Attempt_ID'], $same['Attempt_ID']);

        $resume = $this->service->resume($first['Attempt_ID'], $student);
        $serialized = json_encode($resume, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Correct_Option', $serialized);
        $this->assertStringNotContainsString('Explanation', $serialized);
        $questionIds = $resume['questions']->pluck('Question_ID')->all();
        $this->service->autosave($first['Attempt_ID'], $student, [$questionIds[0] => 'A', $questionIds[1] => 'A'], 1);
        $result = $this->service->submit($first['Attempt_ID'], $student);
        $sameResult = $this->service->submit($first['Attempt_ID'], $student);

        $this->assertSame($result['Result_ID'], $sameResult['Result_ID']);
        $this->assertSame(40.0, (float) $result['Raw_Score']);
        $this->assertSame(100.0, (float) $result['Normalized_Score']);
        $second = $this->service->start($quizId, $student);
        $this->assertNotSame($first['Attempt_ID'], $second['Attempt_ID']);
        $this->assertSame(2, $second['Attempt_Number']);
        $this->assertDatabaseCount('PRACTICE_ATTEMPT', 2);
        $this->assertDatabaseCount('PRACTICE_RESULT', 1);
    }

    public function test_published_edit_uses_new_revision_without_mutating_active_attempt(): void
    {
        $quizId = $this->publish();
        $student = $this->student();
        $oldAttempt = $this->service->start($quizId, $student);

        $edited = $this->payload('publish');
        $edited['Title'] = 'Latihan 日本語 Revision 2';
        $edited['questions'][0]['Correct_Option'] = 'B';
        $this->service->save($quizId, $edited, 'T1', 'U1');
        $quiz = DB::table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', $quizId)->first();
        $this->assertNotSame($oldAttempt['Revision_ID'], $quiz->Current_Revision_ID);

        $oldResume = $this->service->resume($oldAttempt['Attempt_ID'], $student);
        $oldQuestion = $oldResume['questions']->first();
        $oldResult = $this->service->submit($oldAttempt['Attempt_ID'], $student, [$oldQuestion['Question_ID'] => 'A'], 1);
        $this->assertSame(15.0, (float) $oldResult['Raw_Score']);
        $this->assertSame(37.5, (float) $oldResult['Normalized_Score']);

        $newAttempt = $this->service->start($quizId, $student);
        $this->assertSame($quiz->Current_Revision_ID, $newAttempt['Revision_ID']);
        $this->assertNotSame($oldAttempt['Revision_ID'], $newAttempt['Revision_ID']);
    }

    public function test_draft_edit_does_not_replace_current_published_revision(): void
    {
        $quizId = $this->publish();
        $before = DB::table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', $quizId)->first();
        $draft = $this->payload('draft');
        $draft['Title'] = 'Draft belum terbit';
        $this->service->save($quizId, $draft, 'T1', 'U1');
        $after = DB::table('PRACTICE_QUIZ')->where('Practice_Quiz_ID', $quizId)->first();

        $this->assertSame('PUBLISHED', $after->Status);
        $this->assertSame($before->Current_Revision_ID, $after->Current_Revision_ID);
        $this->assertNotNull($after->Draft_Revision_ID);
        $this->assertSame($before->Title, $after->Title);
    }

    public function test_archive_blocks_new_attempt_but_active_attempt_history_and_pdf_data_survive(): void
    {
        $quizId = $this->publish();
        $student = $this->student();
        $attempt = $this->service->start($quizId, $student);
        $this->service->archive($quizId, 'T1', 'U1');
        $catalog = $this->service->studentCatalog($student);
        $this->assertSame($quizId, $catalog->first()->Practice_Quiz_ID);
        $this->assertSame($attempt['Attempt_ID'], $catalog->first()->Active_Attempt_ID);
        $this->assertSame($attempt['Attempt_ID'], $this->service->start($quizId, $student)['Attempt_ID']);
        $question = $this->service->resume($attempt['Attempt_ID'], $student)['questions']->first();
        $result = $this->service->submit($attempt['Attempt_ID'], $student, [$question['Question_ID'] => 'A'], 1);
        $this->assertTrue($this->service->studentCatalog($student)->isEmpty());
        $this->assertFalse($this->service->review($result['Result_ID'], $student)['canRestart']);

        try {
            $this->service->start($quizId, $student);
            $this->fail('Archived practice accepted a new attempt.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $history = $this->service->history($student, [], $quizId);
        $pdf = $this->service->pdfData($student, [], $quizId);
        $this->assertSame($result['Result_ID'], $history['history']->first()->Result_ID);
        $this->assertCount(1, $pdf['rows']);
        $this->assertStringStartsWith('%PDF-', Pdf::loadView('practice-quiz.pdf.history', $pdf)->output());
    }

    public function test_expiry_is_server_authoritative_and_uses_only_saved_answers(): void
    {
        $quizId = $this->publish(duration: 1);
        $student = $this->student();
        $attempt = $this->service->start($quizId, $student);
        $question = $this->service->resume($attempt['Attempt_ID'], $student)['questions']->first();
        $this->service->autosave($attempt['Attempt_ID'], $student, [$question['Question_ID'] => 'A'], 1);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-24 08:02:00', 'Asia/Jakarta'));

        $this->assertSame(1, $this->service->finalizeExpired());
        $result = DB::table('PRACTICE_RESULT')->where('Attempt_ID', $attempt['Attempt_ID'])->first();
        $this->assertSame(1, (int) $result->Correct_Count);
        $this->assertSame(1, (int) $result->Unanswered_Count);
        $this->assertSame('EXPIRED', DB::table('PRACTICE_ATTEMPT')->where('Attempt_ID', $attempt['Attempt_ID'])->value('Status'));
    }

    public function test_practice_operations_do_not_touch_regular_quiz_or_academic_score_tables(): void
    {
        $quizId = $this->publish();
        $student = $this->student();
        $attempt = $this->service->start($quizId, $student);
        $this->service->submit($attempt['Attempt_ID'], $student);
        $this->service->archive($quizId, 'T1', 'U1');

        foreach (['QUIZ_ATTEMPT', 'QUIZ_RESULT', 'MASTER_SCORE', 'QUIZ_RANKING'] as $table) {
            $this->assertSame([['ID' => 'SENTINEL', 'Value' => 'UNCHANGED']], DB::table($table)->get()->map(fn ($row) => (array) $row)->all());
        }
    }

    public function test_students_from_different_classes_receive_independent_attempts_without_class_filtering(): void
    {
        $quizId = $this->publish();
        $first = $this->student();
        $second = ['Student_ID' => 'S2', 'Full_Name' => 'Siswa 二', 'Class_ID' => 'C9'];

        $this->assertSame($quizId, $this->service->studentCatalog($first)->first()->Practice_Quiz_ID);
        $this->assertSame($quizId, $this->service->studentCatalog($second)->first()->Practice_Quiz_ID);
        $attemptOne = $this->service->start($quizId, $first);
        $attemptTwo = $this->service->start($quizId, $second);

        $this->assertNotSame($attemptOne['Attempt_ID'], $attemptTwo['Attempt_ID']);
        $this->assertSame('C1', $attemptOne['Class_ID']);
        $this->assertSame('C9', $attemptTwo['Class_ID']);
        $this->assertDatabaseCount('PRACTICE_ATTEMPT', 2);
    }

    public function test_stale_multitab_autosave_cannot_overwrite_newer_answer_version(): void
    {
        $quizId = $this->publish();
        $student = $this->student();
        $attempt = $this->service->start($quizId, $student);
        $question = $this->service->resume($attempt['Attempt_ID'], $student)['questions']->first();

        $saved = $this->service->autosave($attempt['Attempt_ID'], $student, [$question['Question_ID'] => 'B'], 2);
        $stale = $this->service->autosave($attempt['Attempt_ID'], $student, [$question['Question_ID'] => 'A'], 1);

        $this->assertTrue($saved['saved']);
        $this->assertTrue($stale['conflict']);
        $this->assertSame(2, $stale['version']);
        $this->assertSame('B', DB::table('PRACTICE_ANSWER')->where('Attempt_ID', $attempt['Attempt_ID'])->where('Question_ID', $question['Question_ID'])->value('Selected_Option'));
    }

    public function test_exact_deadline_is_closed_and_finalized_as_expired(): void
    {
        $quizId = $this->publish(duration: 1);
        $student = $this->student();
        $attempt = $this->service->start($quizId, $student);
        CarbonImmutable::setTestNow(CarbonImmutable::parse($attempt['Deadline_At'], 'Asia/Jakarta'));
        $resume = $this->service->resume($attempt['Attempt_ID'], $student);

        $this->assertNotEmpty($resume['finalized_result']['Result_ID']);
        $this->assertSame('EXPIRED', DB::table('PRACTICE_ATTEMPT')->where('Attempt_ID', $attempt['Attempt_ID'])->value('Status'));
    }

    public function test_result_and_specific_pdf_are_private_to_student_owner(): void
    {
        $quizId = $this->publish();
        $owner = $this->student();
        $attempt = $this->service->start($quizId, $owner);
        $result = $this->service->submit($attempt['Attempt_ID'], $owner);

        $questionId = DB::table('PRACTICE_QUESTION_REVISION')->where('Revision_ID', $result['Revision_ID'])->value('Question_ID');
        foreach ([
            fn () => $this->service->review($result['Result_ID'], ['Student_ID' => 'S2']),
            fn () => $this->service->pdfData(['Student_ID' => 'S2'], [], $quizId),
            fn () => $this->service->mediaForAttempt($attempt['Attempt_ID'], $questionId, 'audio', ['Student_ID' => 'S2']),
            fn () => $this->service->mediaForResult($result['Result_ID'], $questionId, 'audio', ['Student_ID' => 'S2']),
        ] as $action) {
            try {
                $action();
                $this->fail('Student data leaked across owner boundary.');
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
    }

    public function test_personal_pdf_renders_multiple_pages_from_server_side_history(): void
    {
        config(['practice_quiz.chart_max_points' => 10]);
        $quizId = $this->publish();
        $student = $this->student();
        for ($number = 1; $number <= 24; $number++) {
            $attempt = $this->service->start($quizId, $student);
            $this->service->submit($attempt['Attempt_ID'], $student);
            CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinute());
        }

        $data = $this->service->pdfData($student, [], $quizId);
        $pdf = Pdf::loadView('practice-quiz.pdf.history', $data);
        $pdf->render();

        $this->assertCount(24, $data['rows']);
        $this->assertSame(24, $data['stats']['count']);
        $this->assertCount(10, $data['stats']['chart']);
        $this->assertTrue($data['stats']['chart_truncated']);
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    private function publish(int $duration = 30): string
    {
        $payload = $this->payload('publish');
        $payload['Duration_Minutes'] = $duration;

        return $this->service->save(null, $payload, 'T1', 'U1');
    }

    private function payload(string $intent): array
    {
        return [
            'Title' => 'Latihan 日本語 Dasar', 'Description' => 'Deskripsi', 'Instructions' => 'Pilih jawaban terbaik.',
            'Duration_Minutes' => 30, 'Idempotency_Key' => (string) Str::uuid(), 'intent' => $intent,
            'questions' => [
                ['Question_Text' => 'いち は?', 'Option_A' => 'Satu', 'Option_B' => 'Dua', 'Option_C' => 'Tiga', 'Option_D' => 'Empat', 'Correct_Option' => 'A', 'Point' => 15, 'Explanation' => 'いち berarti satu.'],
                ['Question_Text' => 'に は?', 'Option_A' => 'Dua', 'Option_B' => 'Satu', 'Option_C' => 'Tiga', 'Option_D' => 'Empat', 'Correct_Option' => 'A', 'Point' => 25, 'Explanation' => 'に berarti dua.'],
            ],
        ];
    }

    private function student(): array
    {
        return ['Student_ID' => 'S1', 'Full_Name' => 'Siswa 一', 'Class_ID' => 'C1'];
    }
}
