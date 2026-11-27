<?php

namespace Tests\Feature;

use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Support\InteractsWithPdf;
use Tests\TestCase;

class DirectorAcademicMonitoringTest extends TestCase
{
    use InteractsWithPdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->seedAcademicData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_director_can_follow_hierarchy_and_refresh_json_without_mutating_academic_data(): void
    {
        $this->asRole('DIRECTOR');
        $sentinels = $this->sentinels();

        $this->get(route('director.academic-monitoring.index', $this->period()))
            ->assertOk()->assertSee('Monitoring Akademik')->assertSee('Angkatan Dua Belas')
            ->assertSee('Kuis Reguler')->assertDontSee('Latihan Rahasia');
        $this->get(route('director.academic-monitoring.batch', ['B1'] + $this->period()))
            ->assertOk()->assertSee('Kelas A')->assertDontSee('Kelas B');
        $this->get(route('director.academic-monitoring.class', ['B1', 'C1'] + $this->period()))
            ->assertOk()->assertSee('Siswa Satu')->assertSee('Siswa Dua')->assertDontSee('Siswa Rahasia');
        $this->get(route('director.academic-monitoring.student', ['B1', 'C1', 'S1'] + $this->period()))
            ->assertOk()->assertSee('Perkembangan Ujian Bab')->assertSee('Riwayat nilai');

        $json = $this->getJson(route('director.academic-monitoring.class.data', ['B1', 'C1'] + $this->period()))
            ->assertOk()->assertJsonStructure(['updated_at', 'trend', 'distribution', 'html']);
        $this->assertLessThan(500_000, strlen($json->getContent()));
        $this->assertSame($sentinels, $this->sentinels(), 'Read-only monitoring must not mutate academic or quiz records.');
    }

    public function test_averages_are_student_weighted_zero_is_valid_dates_are_historical_and_chapters_sort_numerically(): void
    {
        $this->asRole('DIRECTOR');
        $payload = $this->getJson(route('director.academic-monitoring.class.data', ['B1', 'C1'] + $this->period()))
            ->assertOk()->json();

        $this->assertStringContainsString('65', $payload['html']); // mean(mean(0,100), mean(80)) = 65, not record-weighted 60
        $this->assertStringContainsString('80', $payload['html']); // regular quiz: mean(90,70)
        $this->assertSame(['Bab 2', 'Bab 2', 'Bab 10'], $payload['chapter_trend']['labels']);
        $this->assertContains('Minggu 28 Sep', $payload['trend']['labels']); // assessment_date, not Updated_At

        DB::table('MASTER_SCORE')->where('Score_ID', 'SC3')->update(['Score' => 90]);
        $updated = $this->getJson(route('director.academic-monitoring.class.data', ['B1', 'C1'] + $this->period()))->json();
        $this->assertStringContainsString('70', $updated['html']); // mean(50,90)
    }

    public function test_cross_parent_identifiers_fail_closed_and_all_non_director_roles_are_denied(): void
    {
        $this->asRole('DIRECTOR');
        $this->get(route('director.academic-monitoring.class', ['B2', 'C1'] + $this->period()))->assertNotFound();
        $this->get(route('director.academic-monitoring.student', ['B1', 'C1', 'S3'] + $this->period()))->assertNotFound();

        foreach (['TEACHER', 'ADMINISTRATOR', 'MASTER', 'ACADEMIC', 'FINANCE', 'MARKETING', 'EMPLOYEE', 'STUDENT'] as $role) {
            $this->asRole($role);
            $this->get(route('director.academic-monitoring.index'))->assertForbidden();
            $this->getJson(route('director.academic-monitoring.data'))->assertForbidden();
            $this->get(route('director.academic-monitoring.pdf'))->assertForbidden();
        }
        auth()->logout();
        $this->get(route('director.academic-monitoring.index'))->assertRedirect(route('login'));
    }

    public function test_revoked_director_authorization_stops_refresh_and_responses_are_never_cacheable(): void
    {
        $user = new GenericUser(['id' => 'U-DIRECTOR', 'User_ID' => 'U-DIRECTOR', 'Role_ID' => 'R-DIRECTOR', 'Role' => 'DIRECTOR', 'Username' => 'director', 'remember_token' => null]);
        $this->actingAs($user);
        $service = Mockery::mock(RoleService::class);
        $service->shouldReceive('getRoleById')->with('R-DIRECTOR')->andReturn(
            ['Role_ID' => 'R-DIRECTOR', 'Role_Name' => 'DIRECTOR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'R-DIRECTOR', 'Role_Name' => 'DIRECTOR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'R-DIRECTOR', 'Role_Name' => 'DIRECTOR', 'Is_Active' => 'FALSE'],
        );
        $this->app->instance(RoleService::class, $service);

        $this->getJson(route('director.academic-monitoring.data', $this->period()))
            ->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->getJson(route('director.academic-monitoring.data', $this->period()))->assertForbidden();
    }

    public function test_director_gets_no_score_mutation_route_and_pdf_uses_the_same_authorized_calculation(): void
    {
        $this->asRole('DIRECTOR');
        $this->post('/director/academic-monitoring/scores', ['Score' => 100])->assertNotFound();

        $pdf = $this->get(route('director.academic-monitoring.class.pdf', ['B1', 'C1'] + $this->period()));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_monitoring_queries_never_read_practice_quiz_tables_and_query_count_is_bounded(): void
    {
        $this->asRole('DIRECTOR');
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->getJson(route('director.academic-monitoring.data', $this->period()))->assertOk();

        $academicQueries = array_values(array_filter($queries, fn (string $sql) => str_contains(strtoupper($sql), 'MASTER_') || str_contains(strtoupper($sql), 'QUIZ')));
        $this->assertSame(9, count($academicQueries), 'Dashboard refresh should use seven catalog reads plus two bounded observation reads.');
        $this->assertFalse(collect($academicQueries)->contains(fn (string $sql) => str_contains(strtoupper($sql), 'PRACTICE_QUIZ')));
    }

    public function test_polling_contract_pauses_for_hidden_tabs_and_prevents_overlapping_requests(): void
    {
        $view = file_get_contents(resource_path('views/director/academic-monitoring/index.blade.php'));
        $this->assertStringContainsString('state.busy||document.hidden', $view);
        $this->assertStringContainsString('visibilitychange', $view);
        $this->assertStringContainsString('state.controller?.abort()', $view);
        $this->assertStringContainsString('Data terakhir yang berhasil dimuat tetap ditampilkan', $view);
        $this->assertStringContainsString('setInterval(()=>refresh(),60000)', $view);
        $this->assertStringContainsString('res.redirected', $view);
        $this->assertStringContainsString("document.getElementById('monitoring-dynamic').innerHTML=''", $view);
    }

    public function test_dynamic_hierarchy_refreshes_without_scores_or_deployment_and_preserves_history_on_transfer(): void
    {
        $this->asRole('DIRECTOR');

        DB::table('MASTER_BATCH')->insert(['Batch_ID' => 'B3', 'Batch_Name' => 'Angkatan Tanpa Kelas', 'Start_Date' => '2028-01-01', 'Is_Active' => 1]);
        $this->getJson(route('director.academic-monitoring.data', $this->period()))
            ->assertOk()->assertSee('Angkatan Tanpa Kelas')->assertSee('0 kelas');

        DB::table('MASTER_CLASS')->insert(['Class_ID' => 'C3', 'Class_Name' => 'Kelas Tanpa Siswa', 'Batch_ID' => 'B1', 'Class_Status' => 'ACTIVE', 'Is_Active' => 1]);
        $batch = $this->getJson(route('director.academic-monitoring.batch.data', ['B1'] + $this->period()))
            ->assertOk()->assertSee('Kelas Tanpa Siswa')->assertSee('0 siswa');
        $this->assertStringContainsString('65', $batch->json('html'), 'An empty class must not lower an academic average.');

        DB::table('MASTER_CLASS')->where('Class_ID', 'C3')->update(['Class_Name' => 'Kelas Dinamis Baru']);
        DB::table('MASTER_STUDENT')->insert([
            'Student_ID' => 'S5', 'Student_Number' => '005', 'Full_Name' => 'Siswa Baru Tanpa Nilai',
            'Class_ID' => 'C3', 'Batch_ID' => 'B1', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1,
        ]);
        $this->getJson(route('director.academic-monitoring.batch.data', ['B1'] + $this->period()))
            ->assertOk()->assertSee('Kelas Dinamis Baru');
        $this->getJson(route('director.academic-monitoring.class.data', ['B1', 'C3'] + $this->period()))
            ->assertOk()->assertSee('Siswa Baru Tanpa Nilai')->assertSee('Belum dinilai');

        DB::table('MASTER_STUDENT')->where('Student_ID', 'S2')->update(['Class_ID' => 'C3']);
        $this->getJson(route('director.academic-monitoring.class.data', ['B1', 'C1'] + $this->period()))
            ->assertOk()->assertDontSee('Siswa Dua');
        $this->getJson(route('director.academic-monitoring.class.data', ['B1', 'C3'] + $this->period()))
            ->assertOk()->assertSee('Siswa Dua')->assertSee('80');
        $this->get(route('director.academic-monitoring.student', ['B1', 'C3', 'S2'] + $this->period()))
            ->assertOk()->assertSee('Riwayat nilai')->assertSee('80');

        DB::table('MASTER_BATCH')->insert(['Batch_ID' => 'B4', 'Batch_Name' => 'Angkatan Live', 'Start_Date' => '2029-01-01', 'Is_Active' => 1]);
        DB::table('MASTER_CLASS')->insert(['Class_ID' => 'C4', 'Class_Name' => 'Kelas Live', 'Batch_ID' => 'B4', 'Class_Status' => 'ACTIVE', 'Is_Active' => 1]);
        $this->getJson(route('director.academic-monitoring.data', $this->period()))
            ->assertOk()->assertSee('Angkatan Live');
        $this->getJson(route('director.academic-monitoring.batch.data', ['B4'] + $this->period()))
            ->assertOk()->assertSee('Kelas Live');
    }

    public function test_polled_pagination_links_continue_to_html_pages_and_pdf_discloses_truncation(): void
    {
        $this->asRole('DIRECTOR');
        foreach (range(10, 219) as $number) {
            DB::table('MASTER_STUDENT')->insert([
                'Student_ID' => 'SP'.$number, 'Student_Number' => (string) $number, 'Full_Name' => 'Siswa Pagination '.$number,
                'Class_ID' => 'C1', 'Batch_ID' => 'B1', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1,
            ]);
        }

        $html = $this->getJson(route('director.academic-monitoring.class.data', ['B1', 'C1'] + $this->period() + ['per_page' => 10]))
            ->assertOk()->json('html');
        $this->assertStringContainsString('/director/academic-monitoring/batches/B1/classes/C1?', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringNotContainsString('/classes/C1/data?', $html);

        $pdf = $this->get(route('director.academic-monitoring.class.pdf', ['B1', 'C1'] + $this->period()))->assertOk();
        $text = $this->pdfText($pdf, 'director-academic-monitoring-truncated-class');
        $this->assertStringContainsString('200 dari 212 siswa', $text);
        $this->assertStringContainsString('Catatan batas', $text);
        $this->assertSame(212, DB::table('MASTER_STUDENT')->where('Class_ID', 'C1')->where('Is_Active', 1)->count());
    }

    private function asRole(string $role): void
    {
        $user = new GenericUser(['id' => 'U-'.$role, 'User_ID' => 'U-'.$role, 'Role_ID' => 'R-'.$role, 'Role' => $role, 'Username' => strtolower($role), 'remember_token' => null]);
        $this->actingAs($user);
        $service = Mockery::mock(RoleService::class);
        $service->shouldReceive('getRoleById')->zeroOrMoreTimes()->with('R-'.$role)->andReturn([
            'Role_ID' => 'R-'.$role, 'Role_Name' => $role, 'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $service);
    }

    private function period(): array
    {
        return ['period' => 'weekly', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'];
    }

    private function sentinels(): array
    {
        return [
            'scores' => DB::table('MASTER_SCORE')->orderBy('Score_ID')->get()->map(fn ($row) => (array) $row)->all(),
            'results' => DB::table('QUIZ_RESULT')->orderBy('Result_ID')->get()->map(fn ($row) => (array) $row)->all(),
            'practice' => DB::table('PRACTICE_QUIZ_RESULT')->orderBy('Result_ID')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function createSchema(): void
    {
        Schema::create('MASTER_BATCH', function (Blueprint $t): void {
            $t->string('Batch_ID')->primary();
            $t->string('Batch_Code')->nullable();
            $t->string('Batch_Name');
            $t->string('Program_ID')->nullable();
            $t->string('Batch_Status')->nullable();
            $t->date('Start_Date')->nullable();
            $t->date('End_Date')->nullable();
            $t->boolean('Is_Active')->nullable();
        });
        Schema::create('MASTER_CLASS', function (Blueprint $t): void {
            $t->string('Class_ID')->primary();
            $t->string('Class_Code')->nullable();
            $t->string('Class_Name');
            $t->string('Batch_ID');
            $t->string('Program_ID')->nullable();
            $t->string('Homeroom_Teacher_ID')->nullable();
            $t->string('Class_Status')->nullable();
            $t->boolean('Is_Active')->nullable();
        });
        Schema::create('MASTER_STUDENT', function (Blueprint $t): void {
            $t->string('Student_ID')->primary();
            $t->string('Student_Number')->nullable();
            $t->string('Full_Name');
            $t->string('Class_ID');
            $t->string('Batch_ID');
            $t->string('Program_ID')->nullable();
            $t->string('Enrollment_Status')->nullable();
            $t->string('Graduation_Status')->nullable();
            $t->boolean('Is_Active')->nullable();
        });
        Schema::create('MASTER_TEACHER', function (Blueprint $t): void {
            $t->string('Teacher_ID')->primary();
            $t->string('Full_Name');
            $t->string('Teacher_Code')->nullable();
            $t->string('Teaching_Status')->nullable();
            $t->boolean('Is_Active')->nullable();
        });
        Schema::create('MASTER_SUBJECT', function (Blueprint $t): void {
            $t->string('Subject_ID')->primary();
            $t->string('Subject_Name');
            $t->string('Subject_Code')->nullable();
            $t->boolean('Is_Active')->nullable();
        });
        Schema::create('MASTER_SCHEDULE', function (Blueprint $t): void {
            $t->string('Schedule_ID')->primary();
            $t->string('Class_ID');
            $t->string('Subject_ID')->nullable();
            $t->string('Teacher_ID')->nullable();
            $t->boolean('Is_Active')->nullable();
        });
        Schema::create('MASTER_ASSIGNMENT', function (Blueprint $t): void {
            $t->string('Assignment_ID')->primary();
            $t->string('Title')->nullable();
        });
        Schema::create('MASTER_USER', function (Blueprint $t): void {
            $t->string('User_ID')->primary();
            $t->string('Full_Name')->nullable();
        });
        Schema::create('MASTER_ASSESSMENT_CONFIG', function (Blueprint $t): void {
            $t->string('Category_ID')->primary();
            $t->string('Category_Name');
            $t->text('Aspects_JSON')->nullable();
            $t->boolean('Is_Active')->nullable();
        });
        Schema::create('MASTER_SCORE', function (Blueprint $t): void {
            $t->string('Score_ID')->primary();
            $t->string('Student_ID');
            $t->string('Subject_ID')->nullable();
            $t->string('Exam_Type')->nullable();
            $t->decimal('Score', 8, 2)->nullable();
            $t->dateTime('Created_At')->nullable();
            $t->dateTime('Updated_At')->nullable();
            $t->string('Created_By')->nullable();
            $t->string('Schedule_ID')->nullable();
            $t->string('Assignment_ID')->nullable();
            $t->string('Assessment_Category')->nullable();
            $t->text('Evaluation_Details')->nullable();
        });
        Schema::create('QUIZ', function (Blueprint $t): void {
            $t->string('Quiz_ID')->primary();
            $t->string('Teacher_ID')->nullable();
            $t->string('Class_ID');
            $t->string('Title');
            $t->string('Status')->nullable();
        });
        Schema::create('QUIZ_RESULT', function (Blueprint $t): void {
            $t->string('Result_ID')->primary();
            $t->string('Quiz_ID');
            $t->string('Quiz_Title')->nullable();
            $t->string('Student_ID');
            $t->string('Class_ID');
            $t->string('Teacher_ID')->nullable();
            $t->decimal('Normalized_Score', 8, 2)->nullable();
            $t->dateTime('Completed_At')->nullable();
        });
        Schema::create('PRACTICE_QUIZ_RESULT', function (Blueprint $t): void {
            $t->string('Result_ID')->primary();
            $t->string('Student_ID');
            $t->decimal('Score', 8, 2);
        });
    }

    private function seedAcademicData(): void
    {
        DB::table('MASTER_BATCH')->insert([
            ['Batch_ID' => 'B1', 'Batch_Code' => '12', 'Batch_Name' => 'Angkatan Dua Belas', 'Start_Date' => '2026-01-01', 'Is_Active' => 1],
            ['Batch_ID' => 'B2', 'Batch_Code' => '13', 'Batch_Name' => 'Angkatan Rahasia', 'Start_Date' => '2027-01-01', 'Is_Active' => 1],
        ]);
        DB::table('MASTER_CLASS')->insert([
            ['Class_ID' => 'C1', 'Class_Name' => 'Kelas A', 'Batch_ID' => 'B1', 'Homeroom_Teacher_ID' => 'T1', 'Class_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Class_ID' => 'C2', 'Class_Name' => 'Kelas B', 'Batch_ID' => 'B2', 'Homeroom_Teacher_ID' => 'T2', 'Class_Status' => 'ACTIVE', 'Is_Active' => 1],
        ]);
        DB::table('MASTER_STUDENT')->insert([
            ['Student_ID' => 'S1', 'Student_Number' => '001', 'Full_Name' => 'Siswa Satu', 'Class_ID' => 'C1', 'Batch_ID' => 'B1', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Student_ID' => 'S2', 'Student_Number' => '002', 'Full_Name' => 'Siswa Dua', 'Class_ID' => 'C1', 'Batch_ID' => 'B1', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Student_ID' => 'S3', 'Student_Number' => '003', 'Full_Name' => 'Siswa Rahasia', 'Class_ID' => 'C2', 'Batch_ID' => 'B2', 'Enrollment_Status' => 'ACTIVE', 'Is_Active' => 1],
            ['Student_ID' => 'S4', 'Student_Number' => '004', 'Full_Name' => 'Siswa Nonaktif', 'Class_ID' => 'C1', 'Batch_ID' => 'B1', 'Enrollment_Status' => 'INACTIVE', 'Is_Active' => 0],
        ]);
        DB::table('MASTER_TEACHER')->insert([['Teacher_ID' => 'T1', 'Full_Name' => 'Sensei Satu', 'Is_Active' => 1], ['Teacher_ID' => 'T2', 'Full_Name' => 'Sensei Dua', 'Is_Active' => 1]]);
        DB::table('MASTER_SUBJECT')->insert(['Subject_ID' => 'SUB1', 'Subject_Name' => 'Bahasa Jepang', 'Is_Active' => 1]);
        DB::table('MASTER_SCHEDULE')->insert(['Schedule_ID' => 'SH1', 'Class_ID' => 'C1', 'Subject_ID' => 'SUB1', 'Teacher_ID' => 'T1', 'Is_Active' => 1]);
        DB::table('MASTER_ASSIGNMENT')->insert(['Assignment_ID' => 'A1', 'Title' => 'Ujian Bab']);
        DB::table('MASTER_USER')->insert(['User_ID' => 'UT1', 'Full_Name' => 'Sensei Satu']);
        DB::table('MASTER_ASSESSMENT_CONFIG')->insert(['Category_ID' => 'UJIAN_BAB', 'Category_Name' => 'Ujian Bab', 'Is_Active' => 1]);
        DB::table('MASTER_SCORE')->insert([
            ['Score_ID' => 'SC1', 'Student_ID' => 'S1', 'Subject_ID' => 'SUB1', 'Score' => 0, 'Created_At' => '2026-10-01 08:00:00', 'Updated_At' => '2026-11-20 08:00:00', 'Created_By' => 'UT1', 'Schedule_ID' => 'SH1', 'Assignment_ID' => 'A1', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => '{"assessment_date":"2026-10-01","class_id":"C1","bab":"Bab 2"}'],
            ['Score_ID' => 'SC2', 'Student_ID' => 'S1', 'Subject_ID' => 'SUB1', 'Score' => 100, 'Created_At' => '2026-10-08 08:00:00', 'Updated_At' => null, 'Created_By' => 'UT1', 'Schedule_ID' => 'SH1', 'Assignment_ID' => 'A1', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => '{"assessment_date":"2026-10-08","class_id":"C1","bab":"Bab 10"}'],
            ['Score_ID' => 'SC3', 'Student_ID' => 'S2', 'Subject_ID' => 'SUB1', 'Score' => 80, 'Created_At' => '2026-10-15 08:00:00', 'Updated_At' => null, 'Created_By' => 'UT1', 'Schedule_ID' => 'SH1', 'Assignment_ID' => 'A1', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => '{"assessment_date":"2026-10-15","class_id":"C1","bab":"Bab 2"}'],
            ['Score_ID' => 'SC4', 'Student_ID' => 'S3', 'Subject_ID' => 'SUB1', 'Score' => 99, 'Created_At' => '2026-10-15 08:00:00', 'Updated_At' => null, 'Created_By' => 'UT1', 'Schedule_ID' => null, 'Assignment_ID' => null, 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => '{"assessment_date":"2026-10-15","class_id":"C2","bab":"Bab 1"}'],
            ['Score_ID' => 'SC5', 'Student_ID' => 'S4', 'Subject_ID' => 'SUB1', 'Score' => 99, 'Created_At' => '2026-10-15 08:00:00', 'Updated_At' => null, 'Created_By' => 'UT1', 'Schedule_ID' => 'SH1', 'Assignment_ID' => 'A1', 'Assessment_Category' => 'UJIAN_BAB', 'Evaluation_Details' => '{"assessment_date":"2026-10-15","class_id":"C1","bab":"Bab 99"}'],
        ]);
        DB::table('QUIZ')->insert([['Quiz_ID' => 'Q1', 'Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Title' => 'Kuis Kana', 'Status' => 'PUBLISHED'], ['Quiz_ID' => 'Q2', 'Teacher_ID' => 'T2', 'Class_ID' => 'C2', 'Title' => 'Kuis Rahasia', 'Status' => 'PUBLISHED']]);
        DB::table('QUIZ_RESULT')->insert([
            ['Result_ID' => 'R1', 'Quiz_ID' => 'Q1', 'Quiz_Title' => 'Kuis Kana', 'Student_ID' => 'S1', 'Class_ID' => 'C1', 'Teacher_ID' => 'T1', 'Normalized_Score' => 90, 'Completed_At' => '2026-10-20 09:00:00'],
            ['Result_ID' => 'R2', 'Quiz_ID' => 'Q1', 'Quiz_Title' => 'Kuis Kana', 'Student_ID' => 'S2', 'Class_ID' => 'C1', 'Teacher_ID' => 'T1', 'Normalized_Score' => 70, 'Completed_At' => '2026-10-20 09:05:00'],
            ['Result_ID' => 'R3', 'Quiz_ID' => 'Q2', 'Quiz_Title' => 'Kuis Rahasia', 'Student_ID' => 'S3', 'Class_ID' => 'C2', 'Teacher_ID' => 'T2', 'Normalized_Score' => 99, 'Completed_At' => '2026-10-20 09:05:00'],
        ]);
        DB::table('PRACTICE_QUIZ_RESULT')->insert(['Result_ID' => 'P1', 'Student_ID' => 'S1', 'Score' => 100]);
    }
}
