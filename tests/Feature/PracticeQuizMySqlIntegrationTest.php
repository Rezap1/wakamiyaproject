<?php

namespace Tests\Feature;

use App\Services\PracticeQuiz\PracticeQuizService;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\MySqlTestGuard;
use Tests\Support\UsesIsolatedMySqlDatabase;
use Tests\TestCase;

class PracticeQuizMySqlIntegrationTest extends TestCase
{
    use UsesIsolatedMySqlDatabase;

    private Connection $db;

    private string $quizId;

    private string $revisionId;

    protected function setUp(): void
    {
        parent::setUp();
        $connectionName = (string) config('wms_runtime.connection');
        if (! in_array((string) config("database.connections.{$connectionName}.driver"), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Practice quiz MySQL integration requires guarded wms_mysql_test.');
        }
        $this->db = MySqlTestGuard::connection();
        foreach (['PRACTICE_RESULT', 'PRACTICE_ANSWER', 'PRACTICE_ATTEMPT', 'PRACTICE_QUESTION_REVISION', 'PRACTICE_QUIZ_REVISION', 'PRACTICE_OPERATION', 'PRACTICE_QUIZ'] as $table) {
            $this->assertTrue($this->db->getSchemaBuilder()->hasTable($table), "Missing migrated table {$table}.");
            $this->db->table($table)->delete();
        }
        $this->quizId = (string) Str::uuid();
        $this->revisionId = (string) Str::uuid();
        $now = now()->format('Y-m-d H:i:s.u');
        $this->db->table('PRACTICE_QUIZ')->insert([
            'Practice_Quiz_ID' => $this->quizId, 'Teacher_ID' => 'MYSQL-TEACHER', 'Title' => 'MariaDB Practice 日本語',
            'Description' => null, 'Instructions' => null, 'Duration_Minutes' => 30, 'Status' => 'PUBLISHED',
            'Current_Revision_ID' => $this->revisionId, 'Draft_Revision_ID' => null,
            'Created_At' => $now, 'Updated_At' => $now, 'Published_At' => $now, 'Deleted_At' => null, 'Deleted_By' => null,
        ]);
        $this->db->table('PRACTICE_QUIZ_REVISION')->insert([
            'Revision_ID' => $this->revisionId, 'Practice_Quiz_ID' => $this->quizId, 'Revision_Number' => 1,
            'Title' => 'MariaDB Practice 日本語', 'Description' => null, 'Instructions' => null,
            'Duration_Minutes' => 30, 'Question_Count' => 1, 'Status' => 'PUBLISHED', 'Created_At' => $now, 'Created_By' => 'MYSQL-USER',
        ]);
        $this->db->table('PRACTICE_QUESTION_REVISION')->insert([
            'Question_ID' => (string) Str::uuid(), 'Revision_ID' => $this->revisionId, 'Sort_Order' => 1,
            'Question_Text' => 'いち は?', 'Option_A' => 'Satu', 'Option_B' => 'Dua', 'Option_C' => 'Tiga', 'Option_D' => 'Empat',
            'Correct_Option' => 'A', 'Point' => 10, 'Explanation' => 'いち berarti satu.',
            'Image_Path' => null, 'Image_Mime' => null, 'Image_Size_Bytes' => null,
            'Audio_Path' => null, 'Audio_Mime' => null, 'Audio_Size_Bytes' => null, 'Audio_Duration_Milliseconds' => null,
            'Created_At' => $now,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            foreach (['PRACTICE_RESULT', 'PRACTICE_ANSWER', 'PRACTICE_ATTEMPT', 'PRACTICE_QUESTION_REVISION', 'PRACTICE_QUIZ_REVISION', 'PRACTICE_OPERATION', 'PRACTICE_QUIZ'] as $table) {
                $this->db->table($table)->delete();
            }
        }
        parent::tearDown();
    }

    public function test_parallel_start_produces_one_active_attempt_and_submit_is_idempotent_and_isolated(): void
    {
        $regular = [
            'attempts' => $this->db->table('QUIZ_ATTEMPT')->count(),
            'results' => $this->db->table('QUIZ_RESULT')->count(),
            'scores' => $this->db->table('MASTER_SCORE')->count(),
        ];
        $worker = base_path('tests/Support/practice_quiz_concurrency_worker.php');
        $processes = [
            new Process([PHP_BINARY, $worker, $this->quizId, 'MYSQL-STUDENT']),
            new Process([PHP_BINARY, $worker, $this->quizId, 'MYSQL-STUDENT']),
        ];
        foreach ($processes as $process) {
            $process->setTimeout(30);
            $process->start();
        }
        foreach ($processes as $process) {
            $process->wait();
        }
        $successful = collect($processes)->filter(fn (Process $process) => $process->isSuccessful());
        $this->assertGreaterThanOrEqual(1, $successful->count());
        $ids = $successful->map(function (Process $process): string {
            $output = $process->getOutput();
            $json = substr($output, (int) strrpos($output, '{'));

            return json_decode($json, true, 512, JSON_THROW_ON_ERROR)['attempt_id'];
        });
        foreach (collect($processes)->reject(fn (Process $process) => $process->isSuccessful()) as $failed) {
            $this->assertStringContainsString('Percobaan sedang dipersiapkan', $failed->getErrorOutput());
        }

        $this->assertCount(1, $ids->unique());
        $this->assertSame(1, $this->db->table('PRACTICE_ATTEMPT')->where('Status', 'IN_PROGRESS')->count());
        $service = $this->app->make(PracticeQuizService::class);
        $student = ['Student_ID' => 'MYSQL-STUDENT', 'Class_ID' => 'MYSQL-CLASS'];
        $retried = $service->start($this->quizId, $student);
        $this->assertSame($ids->first(), $retried['Attempt_ID']);
        $resume = $service->resume($retried['Attempt_ID'], $student);
        $questionId = (string) $resume['questions']->first()['Question_ID'];
        $result = $service->submit($ids->first(), $student, [$questionId => 'A'], 1);
        $again = $service->submit($ids->first(), $student);
        $this->assertSame($result['Result_ID'], $again['Result_ID']);
        $this->assertSame(100.0, (float) $result['Normalized_Score']);
        $this->assertSame(1, $this->db->table('PRACTICE_RESULT')->count());
        $this->assertSame($regular['attempts'], $this->db->table('QUIZ_ATTEMPT')->count());
        $this->assertSame($regular['results'], $this->db->table('QUIZ_RESULT')->count());
        $this->assertSame($regular['scores'], $this->db->table('MASTER_SCORE')->count());
    }
}
