<?php

namespace Tests\Feature;

use App\Contracts\AcademicMonitoringRepository as AcademicMonitoringRepositoryContract;
use App\Services\Academic\AcademicMonitoringService;
use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\Support\MySqlTestGuard;
use Tests\Support\UsesIsolatedMySqlDatabase;
use Tests\TestCase;

final class DirectorAcademicMonitoringMySqlIntegrationTest extends TestCase
{
    use UsesIsolatedMySqlDatabase;

    private Connection $db;

    private array $temporaryTables = [
        'QUIZ_RESULT', 'QUIZ', 'MASTER_SCORE', 'MASTER_USER', 'MASTER_ASSIGNMENT',
        'MASTER_ASSESSMENT_CONFIG', 'MASTER_SCHEDULE', 'MASTER_SUBJECT', 'MASTER_TEACHER',
        'MASTER_STUDENT', 'MASTER_CLASS', 'MASTER_BATCH',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $connectionName = (string) config('wms_runtime.connection');
        if (! in_array((string) config("database.connections.{$connectionName}.driver"), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Director monitoring MariaDB integration requires guarded wms_mysql_test.');
        }

        $this->db = MySqlTestGuard::connection();
        $this->createTemporarySchema();
        $this->seedCorrectnessFixture();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            foreach ($this->temporaryTables as $table) {
                $this->db->statement("DROP TEMPORARY TABLE IF EXISTS {$table}");
            }
        }
        Mockery::close();
        parent::tearDown();
    }

    public function test_real_mariadb_correctness_hierarchy_pagination_authorization_and_read_only_contract(): void
    {
        $this->asDirector();
        $before = $this->sentinels();
        $filters = $this->filters();
        $service = app(AcademicMonitoringService::class);

        $class = $service->build(['type' => 'class', 'batch_id' => 'AM-B1', 'class_id' => 'AM-C1'], $filters);
        $chapter = $class['category_stats']->firstWhere('category', 'UJIAN_BAB');
        $quiz = $class['category_stats']->firstWhere('category', 'KUIS_REGULER');
        $this->assertSame(65.0, $chapter['average']);
        $this->assertSame(3, $chapter['observations']);
        $this->assertSame(80.0, $quiz['average']);
        $this->assertSame(['Bab 2', 'Bab 3', 'Bab 10'], $class['chapter_trend']['labels']);
        $this->assertSame(25, $class['summary']['students']);
        $this->assertSame(23, $class['summary']['without_scores']);
        $this->assertSame(10, $class['student_paginator']->perPage());
        $this->assertSame(25, $class['student_paginator']->total());
        $this->assertSame(5, $class['summary']['observations'], 'Invalid, NULL, out-of-range, and out-of-period records must not enter aggregates.');

        $this->assertTrue(collect($class['trend']['labels'])->contains(fn (string $label) => str_contains($label, '28 Sep')));
        $this->assertSame($before, $this->sentinels());

        DB::table('MASTER_BATCH')->insert(['Batch_ID' => 'AM-B3', 'Batch_Name' => 'MariaDB Angkatan Live', 'Start_Date' => '2029-01-01', 'Is_Active' => 1]);
        $afterBatch = $service->build(['type' => 'index'], $filters);
        $this->assertTrue($afterBatch['entities']->contains(fn (array $row) => $row['Batch_ID'] === 'AM-B3' && $row['classes'] === 0));

        DB::table('MASTER_CLASS')->insert(['Class_ID' => 'AM-C3', 'Class_Name' => 'MariaDB Kelas Live', 'Batch_ID' => 'AM-B3', 'Class_Status' => 'ACTIVE', 'Is_Active' => 1]);
        DB::table('MASTER_STUDENT')->insert(['Student_ID' => 'AM-S-LIVE', 'Student_Number' => 'LIVE', 'Full_Name' => 'Siswa MariaDB Tanpa Nilai', 'Class_ID' => 'AM-C3', 'Batch_ID' => 'AM-B3', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1]);
        $afterClass = $service->build(['type' => 'batch', 'batch_id' => 'AM-B3'], $filters);
        $this->assertTrue($afterClass['entities']->contains(fn (array $row) => $row['Class_ID'] === 'AM-C3' && $row['students'] === 1 && $row['monitoring'] === []));

        $this->get(route('director.academic-monitoring.class', ['AM-B2', 'AM-C1'] + $filters))->assertNotFound();
        $this->get(route('director.academic-monitoring.student', ['AM-B1', 'AM-C1', 'AM-S4'] + $filters))->assertNotFound();
        $this->getJson(route('director.academic-monitoring.class.data', ['AM-B1', 'AM-C1'] + $filters))
            ->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertSame($before, $this->sentinels(), 'GET monitoring endpoints must not mutate the MariaDB fixture.');

        try {
            DB::table('MASTER_SCORE')->insert([
                'Score_ID' => 'AM-MALFORMED', 'Student_ID' => 'AM-S1', 'Score' => 'not-numeric',
                'Assessment_Category' => 'UJIAN_BAB', 'Created_At' => '2026-10-01 00:00:00',
            ]);
            $this->fail('MariaDB strict DECIMAL schema must reject malformed score text.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_representative_mariadb_dataset_explain_and_performance_contract(): void
    {
        $this->seedPerformanceFixture();
        $this->asDirector();
        $service = app(AcademicMonitoringService::class);
        $scenarios = [
            'institution_weekly' => [['type' => 'index'], $this->filters()],
            'institution_monthly' => [['type' => 'index'], $this->filters(['period' => 'monthly'])],
            'institution_all' => [['type' => 'index'], $this->filters(['period' => 'all'])],
            'batch_weekly' => [['type' => 'batch', 'batch_id' => 'AM-PB1'], $this->filters()],
            'class_weekly' => [['type' => 'class', 'batch_id' => 'AM-PB1', 'class_id' => 'AM-PC01'], $this->filters()],
            'student_weekly' => [['type' => 'student', 'batch_id' => 'AM-PB1', 'class_id' => 'AM-PC01', 'student_id' => 'AM-PS0001'], $this->filters()],
        ];
        $metrics = [];

        foreach ($scenarios as $name => [$scope, $filters]) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $start = hrtime(true);
            $result = $service->build($scope, $filters);
            $milliseconds = round((hrtime(true) - $start) / 1_000_000, 2);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $metrics[$name] = [
                'milliseconds' => $milliseconds,
                'queries' => count($queries),
                'observations' => $result['summary']['observations'],
                'memory_peak_bytes' => memory_get_peak_usage(true),
            ];
            $this->assertLessThan(3000, $milliseconds, "{$name} exceeded the local integration budget.");
            $this->assertLessThanOrEqual($scope['type'] === 'class' ? 11 : 9, count($queries));
        }

        $dataStart = hrtime(true);
        $data = $this->getJson(route('director.academic-monitoring.data', $this->filters()))->assertOk();
        $metrics['auto_refresh'] = [
            'milliseconds' => round((hrtime(true) - $dataStart) / 1_000_000, 2),
            'response_bytes' => strlen($data->getContent()),
        ];
        $this->assertLessThan(500_000, $metrics['auto_refresh']['response_bytes']);

        $pdfStart = hrtime(true);
        $pdf = $this->get(route('director.academic-monitoring.class.pdf', ['AM-PB1', 'AM-PC01'] + $this->filters()))->assertOk();
        $metrics['class_pdf'] = [
            'milliseconds' => round((hrtime(true) - $pdfStart) / 1_000_000, 2),
            'response_bytes' => strlen($pdf->getContent()),
        ];
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $filters = ['from' => '2026-10-01', 'to' => '2026-10-31', 'batch_id' => null, 'class_id' => null, 'student_id' => null];
        $metrics['explain'] = [
            'master_score' => $this->explain('scoreQuery', $filters),
            'quiz_result' => $this->explain('quizQuery', $filters),
        ];
        $metrics['fixture'] = [
            'batches' => DB::table('MASTER_BATCH')->count(),
            'classes' => DB::table('MASTER_CLASS')->count(),
            'students' => DB::table('MASTER_STUDENT')->count(),
            'scores' => DB::table('MASTER_SCORE')->count(),
            'quiz_results' => DB::table('QUIZ_RESULT')->count(),
        ];
        $metrics['server'] = (array) $this->db->selectOne('SELECT VERSION() AS version, @@port AS port, DATABASE() AS db');

        fwrite(STDOUT, "\nACADEMIC_MONITORING_MARIADB_EVIDENCE=".json_encode($metrics, JSON_UNESCAPED_SLASHES)."\n");
        $this->assertSame(300, DB::table('MASTER_STUDENT')->where('Student_ID', 'like', 'AM-PS%')->count());
        $this->assertSame(3000, DB::table('MASTER_SCORE')->where('Score_ID', 'like', 'AM-PSC%')->count());
        $this->assertSame(600, DB::table('QUIZ_RESULT')->where('Result_ID', 'like', 'AM-PR%')->count());
    }

    private function createTemporarySchema(): void
    {
        $statements = [
            'CREATE TEMPORARY TABLE MASTER_BATCH (Batch_ID VARCHAR(64) PRIMARY KEY, Batch_Code VARCHAR(64) NULL, Batch_Name VARCHAR(255) NOT NULL, Program_ID VARCHAR(64) NULL, Batch_Status VARCHAR(32) NULL, Start_Date DATE NULL, End_Date DATE NULL, Is_Active TINYINT NULL) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_CLASS (Class_ID VARCHAR(64) PRIMARY KEY, Class_Code VARCHAR(64) NULL, Class_Name VARCHAR(255) NOT NULL, Batch_ID VARCHAR(64) NOT NULL, Program_ID VARCHAR(64) NULL, Homeroom_Teacher_ID VARCHAR(64) NULL, Class_Status VARCHAR(32) NULL, Is_Active TINYINT NULL, INDEX idx_class_batch (Batch_ID)) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_STUDENT (Student_ID VARCHAR(64) PRIMARY KEY, Student_Number VARCHAR(64) NULL, Full_Name VARCHAR(255) NOT NULL, Class_ID VARCHAR(64) NULL, Batch_ID VARCHAR(64) NULL, Program_ID VARCHAR(64) NULL, Enrollment_Status VARCHAR(32) NULL, Graduation_Status VARCHAR(32) NULL, Is_Active TINYINT NULL, INDEX idx_student_class (Class_ID), INDEX idx_student_batch (Batch_ID)) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_TEACHER (Teacher_ID VARCHAR(64) PRIMARY KEY, Full_Name VARCHAR(255) NULL, Teacher_Code VARCHAR(64) NULL, Teaching_Status VARCHAR(32) NULL, Is_Active TINYINT NULL) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_SUBJECT (Subject_ID VARCHAR(64) PRIMARY KEY, Subject_Name VARCHAR(255) NULL, Subject_Code VARCHAR(64) NULL, Is_Active TINYINT NULL) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_SCHEDULE (Schedule_ID VARCHAR(64) PRIMARY KEY, Class_ID VARCHAR(64) NULL, Subject_ID VARCHAR(64) NULL, Teacher_ID VARCHAR(64) NULL, Is_Active TINYINT NULL, INDEX idx_schedule_class (Class_ID)) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_ASSESSMENT_CONFIG (Category_ID VARCHAR(80) PRIMARY KEY, Category_Name VARCHAR(255) NULL, Aspects_JSON LONGTEXT NULL, Is_Active TINYINT NULL) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_ASSIGNMENT (Assignment_ID VARCHAR(64) PRIMARY KEY, Title VARCHAR(255) NULL) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_USER (User_ID VARCHAR(64) PRIMARY KEY, Full_Name VARCHAR(255) NULL) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE MASTER_SCORE (Score_ID VARCHAR(64) PRIMARY KEY, Student_ID VARCHAR(64) NULL, Subject_ID VARCHAR(64) NULL, Score DECIMAL(8,2) NULL, Created_At DATETIME NULL, Updated_At DATETIME NULL, Created_By VARCHAR(64) NULL, Schedule_ID VARCHAR(64) NULL, Assignment_ID VARCHAR(64) NULL, Assessment_Category VARCHAR(80) NULL, Exam_Type VARCHAR(80) NULL, Evaluation_Details LONGTEXT NULL, INDEX idx_score_student (Student_ID), INDEX idx_score_created (Created_At), INDEX idx_score_schedule (Schedule_ID), INDEX idx_score_assignment (Assignment_ID)) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE QUIZ (Quiz_ID VARCHAR(64) PRIMARY KEY, Teacher_ID VARCHAR(64) NULL, Class_ID VARCHAR(64) NULL, Title VARCHAR(255) NULL, Status VARCHAR(32) NULL) ENGINE=InnoDB',
            'CREATE TEMPORARY TABLE QUIZ_RESULT (Result_ID VARCHAR(64) PRIMARY KEY, Quiz_ID VARCHAR(64) NULL, Quiz_Title VARCHAR(255) NULL, Student_ID VARCHAR(64) NULL, Class_ID VARCHAR(64) NULL, Teacher_ID VARCHAR(64) NULL, Normalized_Score DECIMAL(8,2) NULL, Completed_At DATETIME NULL, INDEX idx_result_student (Student_ID), INDEX idx_result_class_completed (Class_ID, Completed_At)) ENGINE=InnoDB',
        ];
        foreach ($statements as $statement) {
            $this->db->statement($statement);
        }
    }

    private function seedCorrectnessFixture(): void
    {
        DB::table('MASTER_BATCH')->insert([
            ['Batch_ID' => 'AM-B1', 'Batch_Name' => 'MariaDB Angkatan Satu', 'Start_Date' => '2026-01-01', 'Is_Active' => 1],
            ['Batch_ID' => 'AM-B2', 'Batch_Name' => 'MariaDB Angkatan Dua', 'Start_Date' => '2027-01-01', 'Is_Active' => 1],
        ]);
        DB::table('MASTER_CLASS')->insert([
            ['Class_ID' => 'AM-C1', 'Class_Name' => 'MariaDB Kelas Satu', 'Batch_ID' => 'AM-B1', 'Homeroom_Teacher_ID' => 'AM-T1', 'Class_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Class_ID' => 'AM-C2', 'Class_Name' => 'MariaDB Kelas Dua', 'Batch_ID' => 'AM-B2', 'Homeroom_Teacher_ID' => 'AM-T1', 'Class_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Class_ID' => 'AM-CEMPTY', 'Class_Name' => 'MariaDB Kelas Kosong', 'Batch_ID' => 'AM-B1', 'Homeroom_Teacher_ID' => null, 'Class_Status' => 'ACTIVE', 'Is_Active' => 1],
        ]);
        $students = [
            ['Student_ID' => 'AM-S1', 'Student_Number' => '001', 'Full_Name' => 'MariaDB Siswa Satu', 'Class_ID' => 'AM-C1', 'Batch_ID' => 'AM-B1', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Student_ID' => 'AM-S2', 'Student_Number' => '002', 'Full_Name' => 'MariaDB Siswa Dua', 'Class_ID' => 'AM-C1', 'Batch_ID' => 'AM-B1', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Student_ID' => 'AM-S3', 'Student_Number' => '003', 'Full_Name' => 'MariaDB Siswa Tanpa Nilai', 'Class_ID' => 'AM-C1', 'Batch_ID' => 'AM-B1', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Student_ID' => 'AM-S4', 'Student_Number' => '004', 'Full_Name' => 'MariaDB Siswa Lain', 'Class_ID' => 'AM-C2', 'Batch_ID' => 'AM-B2', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1],
        ];
        foreach (range(5, 26) as $number) {
            $students[] = ['Student_ID' => 'AM-S'.$number, 'Student_Number' => str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'Full_Name' => 'MariaDB Siswa '.$number, 'Class_ID' => 'AM-C1', 'Batch_ID' => 'AM-B1', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1];
        }
        DB::table('MASTER_STUDENT')->insert($students);
        DB::table('MASTER_TEACHER')->insert(['Teacher_ID' => 'AM-T1', 'Full_Name' => 'MariaDB Sensei', 'Teaching_Status' => 'ACTIVE', 'Is_Active' => 1]);
        DB::table('MASTER_SUBJECT')->insert(['Subject_ID' => 'AM-SUB', 'Subject_Name' => 'Bahasa Jepang', 'Is_Active' => 1]);
        DB::table('MASTER_SCHEDULE')->insert(['Schedule_ID' => 'AM-SCH', 'Class_ID' => 'AM-C1', 'Subject_ID' => 'AM-SUB', 'Teacher_ID' => 'AM-T1', 'Is_Active' => 1]);
        DB::table('MASTER_ASSIGNMENT')->insert(['Assignment_ID' => 'AM-ASG', 'Title' => 'Ujian Bab']);
        DB::table('MASTER_USER')->insert(['User_ID' => 'AM-U1', 'Full_Name' => 'MariaDB Sensei']);
        DB::table('MASTER_ASSESSMENT_CONFIG')->insert(['Category_ID' => 'UJIAN_BAB', 'Category_Name' => 'Ujian Bab', 'Is_Active' => 1]);
        $scoreDefaults = ['Subject_ID' => null, 'Updated_At' => null, 'Created_By' => null, 'Schedule_ID' => null, 'Assignment_ID' => null, 'Exam_Type' => null, 'Evaluation_Details' => null];
        $scores = [
            ['Score_ID' => 'AM-SC1', 'Student_ID' => 'AM-S1', 'Subject_ID' => 'AM-SUB', 'Score' => 0, 'Created_At' => '2026-11-20 08:00:00', 'Updated_At' => '2026-11-21 08:00:00', 'Created_By' => 'AM-U1', 'Schedule_ID' => 'AM-SCH', 'Assignment_ID' => 'AM-ASG', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => '{"assessment_date":"2026-10-01","class_id":"AM-C1","bab":"Bab 2"}'],
            ['Score_ID' => 'AM-SC2', 'Student_ID' => 'AM-S1', 'Subject_ID' => 'AM-SUB', 'Score' => 100, 'Created_At' => '2026-10-08 08:00:00', 'Created_By' => 'AM-U1', 'Schedule_ID' => 'AM-SCH', 'Assignment_ID' => 'AM-ASG', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => '{"assessment_date":"2026-10-08","class_id":"AM-C1","bab":"Bab 10"}'],
            ['Score_ID' => 'AM-SC3', 'Student_ID' => 'AM-S2', 'Subject_ID' => 'AM-SUB', 'Score' => 80, 'Created_At' => '2026-10-15 08:00:00', 'Created_By' => 'AM-U1', 'Schedule_ID' => 'AM-SCH', 'Assignment_ID' => 'AM-ASG', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => '{"assessment_date":"2026-10-15","class_id":"AM-C1","bab":"Bab 3"}'],
            ['Score_ID' => 'AM-SC-NULL', 'Student_ID' => 'AM-S3', 'Score' => null, 'Created_At' => '2026-10-12 08:00:00', 'Assessment_Category' => 'UJIAN_BAB'],
            ['Score_ID' => 'AM-SC-HIGH', 'Student_ID' => 'AM-S3', 'Score' => 120, 'Created_At' => '2026-10-12 08:00:00', 'Assessment_Category' => 'UJIAN_BAB'],
            ['Score_ID' => 'AM-SC-LOW', 'Student_ID' => 'AM-S3', 'Score' => -5, 'Created_At' => '2026-10-12 08:00:00', 'Assessment_Category' => 'UJIAN_BAB'],
            ['Score_ID' => 'AM-SC-OUTSIDE', 'Student_ID' => 'AM-S3', 'Score' => 55, 'Created_At' => '2026-09-01 08:00:00', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => 'not-json'],
        ];
        DB::table('MASTER_SCORE')->insert(array_map(fn (array $row) => array_replace($scoreDefaults, $row), $scores));
        DB::table('QUIZ')->insert(['Quiz_ID' => 'AM-Q1', 'Teacher_ID' => 'AM-T1', 'Class_ID' => 'AM-C1', 'Title' => 'Kuis Reguler MariaDB', 'Status' => 'PUBLISHED']);
        $resultDefaults = ['Quiz_Title' => null, 'Teacher_ID' => null, 'Normalized_Score' => null];
        $results = [
            ['Result_ID' => 'AM-R1', 'Quiz_ID' => 'AM-Q1', 'Quiz_Title' => 'Kuis Reguler MariaDB', 'Student_ID' => 'AM-S1', 'Class_ID' => 'AM-C1', 'Teacher_ID' => 'AM-T1', 'Normalized_Score' => 90, 'Completed_At' => '2026-10-20 09:00:00'],
            ['Result_ID' => 'AM-R2', 'Quiz_ID' => 'AM-Q1', 'Quiz_Title' => 'Kuis Reguler MariaDB', 'Student_ID' => 'AM-S2', 'Class_ID' => 'AM-C1', 'Teacher_ID' => 'AM-T1', 'Normalized_Score' => 70, 'Completed_At' => '2026-10-20 09:05:00'],
            ['Result_ID' => 'AM-R-NULL', 'Quiz_ID' => 'AM-Q1', 'Student_ID' => 'AM-S3', 'Class_ID' => 'AM-C1', 'Normalized_Score' => null, 'Completed_At' => '2026-10-20 09:10:00'],
        ];
        DB::table('QUIZ_RESULT')->insert(array_map(fn (array $row) => array_replace($resultDefaults, $row), $results));
    }

    private function seedPerformanceFixture(): void
    {
        $batches = $classes = $students = $scores = $results = [];
        for ($batch = 1; $batch <= 3; $batch++) {
            $batches[] = ['Batch_ID' => 'AM-PB'.$batch, 'Batch_Name' => 'Performance Angkatan '.$batch, 'Start_Date' => '202'.(5 + $batch).'-01-01', 'Is_Active' => 1];
        }
        for ($class = 1; $class <= 12; $class++) {
            $batch = (int) ceil($class / 4);
            $classId = 'AM-PC'.str_pad((string) $class, 2, '0', STR_PAD_LEFT);
            $classes[] = ['Class_ID' => $classId, 'Class_Name' => 'Performance Kelas '.$class, 'Batch_ID' => 'AM-PB'.$batch, 'Class_Status' => 'ACTIVE', 'Is_Active' => 1];
        }
        DB::table('QUIZ')->insert(['Quiz_ID' => 'AM-PQ', 'Teacher_ID' => 'AM-T1', 'Class_ID' => 'AM-PC01', 'Title' => 'Performance Quiz', 'Status' => 'PUBLISHED']);
        for ($student = 1; $student <= 300; $student++) {
            $class = (int) ceil($student / 25);
            $batch = (int) ceil($class / 4);
            $studentId = 'AM-PS'.str_pad((string) $student, 4, '0', STR_PAD_LEFT);
            $classId = 'AM-PC'.str_pad((string) $class, 2, '0', STR_PAD_LEFT);
            $students[] = ['Student_ID' => $studentId, 'Student_Number' => 'P'.$student, 'Full_Name' => 'Performance Siswa '.$student, 'Class_ID' => $classId, 'Batch_ID' => 'AM-PB'.$batch, 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1];
            for ($attempt = 1; $attempt <= 10; $attempt++) {
                $scores[] = ['Score_ID' => 'AM-PSC'.$student.'-'.$attempt, 'Student_ID' => $studentId, 'Subject_ID' => 'AM-SUB', 'Score' => ($student + $attempt) % 101, 'Created_At' => '2026-10-'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT).' 08:00:00', 'Created_By' => 'AM-U1', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => json_encode(['assessment_date' => '2026-10-'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT), 'class_id' => $classId, 'bab' => 'Bab '.$attempt])];
            }
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $results[] = ['Result_ID' => 'AM-PR'.$student.'-'.$attempt, 'Quiz_ID' => 'AM-PQ', 'Quiz_Title' => 'Performance Quiz', 'Student_ID' => $studentId, 'Class_ID' => $classId, 'Teacher_ID' => 'AM-T1', 'Normalized_Score' => ($student + $attempt * 7) % 101, 'Completed_At' => '2026-10-'.str_pad((string) (20 + $attempt), 2, '0', STR_PAD_LEFT).' 09:00:00'];
            }
        }
        DB::table('MASTER_BATCH')->insert($batches);
        DB::table('MASTER_CLASS')->insert($classes);
        foreach (array_chunk($students, 250) as $chunk) {
            DB::table('MASTER_STUDENT')->insert($chunk);
        }
        foreach (array_chunk($scores, 400) as $chunk) {
            DB::table('MASTER_SCORE')->insert($chunk);
        }
        foreach (array_chunk($results, 400) as $chunk) {
            DB::table('QUIZ_RESULT')->insert($chunk);
        }
    }

    private function explain(string $method, array $filters): array
    {
        $repository = app(AcademicMonitoringRepositoryContract::class);
        $reflection = new ReflectionMethod($repository, $method);
        $query = $reflection->invoke($repository, $filters);

        return $query->explain()->map(fn (object $row) => (array) $row)->all();
    }

    private function asDirector(): void
    {
        $user = new GenericUser(['id' => 'AM-DIRECTOR', 'User_ID' => 'AM-DIRECTOR', 'Role_ID' => 'AM-R-DIRECTOR', 'Role' => 'DIRECTOR', 'Username' => 'director', 'remember_token' => null]);
        $this->actingAs($user);
        $roles = Mockery::mock(RoleService::class);
        $roles->shouldReceive('getRoleById')->zeroOrMoreTimes()->with('AM-R-DIRECTOR')->andReturn(['Role_ID' => 'AM-R-DIRECTOR', 'Role_Name' => 'DIRECTOR', 'Is_Active' => 'TRUE']);
        $this->app->instance(RoleService::class, $roles);
    }

    private function filters(array $overrides = []): array
    {
        return $overrides + [
            'period' => 'weekly', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
            'comparison_from' => '2026-08-31', 'category' => '', 'search' => '', 'sort' => 'name',
            'direction' => 'asc', 'per_page' => 10, 'page' => 1,
        ];
    }

    private function sentinels(): array
    {
        return [
            'scores' => DB::table('MASTER_SCORE')->orderBy('Score_ID')->get()->map(fn ($row) => (array) $row)->all(),
            'results' => DB::table('QUIZ_RESULT')->orderBy('Result_ID')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }
}
