<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Services\Academic\AssessmentConfigService;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\SubjectService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\AssignmentService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Support\Academic\TeacherScopeResolver;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class TeacherScoreScheduleIndependentTest extends TestCase
{
    private array $scope = [
        'managed_class_ids' => ['CLS-A'],
        'managed_student_ids' => ['STU-A'],
        'managed_students_by_class' => ['CLS-A' => ['STU-A']],
        'students_by_class' => ['CLS-A' => ['STU-A']],
        'teaching_student_ids' => [],
        'schedule_ids' => [],
        'assessment_ids' => [],
        'schedules' => [],
        'students' => [
            ['Student_ID' => 'STU-A', 'Full_Name' => 'Siswa Scope', 'Class_ID' => 'CLS-A'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(RoleMiddleware::class);
        $this->actingAs(new GenericUser([
            'id' => 'USR-A',
            'User_ID' => 'USR-A',
            'Role' => 'TEACHER',
            'Full_Name' => 'Guru Scope',
        ]));
    }

    public function test_teacher_can_select_rostered_student_without_any_schedule(): void
    {
        $score = Mockery::mock(ScoreService::class);
        $this->bindWorkspace($score);

        $response = $this->get(route('teacher.workspace.scores.create'));

        $response->assertOk()
            ->assertSee('Pilih Siswa')
            ->assertSee('Siswa Scope')
            ->assertDontSee('Pilih jadwal terlebih dahulu')
            ->assertDontSee('score-schedule');
    }

    public function test_valid_score_is_stored_without_schedule_with_server_owned_class_and_creator(): void
    {
        $score = Mockery::mock(ScoreService::class);
        $score->shouldReceive('getTeacherScoreScope')->once()->with('TCH-A')->andReturn($this->scope);
        $score->shouldReceive('create')->once()->with(Mockery::on(function (array $data): bool {
            return $data['Student_ID'] === 'STU-A'
                && $data['Class_ID'] === 'CLS-A'
                && $data['Schedule_ID'] === null
                && $data['Teacher_ID'] === 'TCH-A'
                && $data['Created_By'] === 'USR-A'
                && $data['Assessment_Date'] === '2026-10-31'
                && $data['Score'] === '88';
        }));
        $this->bindWorkspace($score, $this->numericConfig());

        $response = $this->post(route('teacher.workspace.scores.store'), [
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-A',
            'Assessment_Category' => 'UJIAN_BAB',
            'Date' => '2026-10-31',
            'Score' => '88',
        ]);

        $response->assertRedirect(route('teacher.workspace.scores'))
            ->assertSessionHas('success', 'Penilaian berhasil disimpan.');
    }

    public function test_manipulated_student_or_class_outside_teacher_roster_is_rejected(): void
    {
        $score = Mockery::mock(ScoreService::class);
        $score->shouldReceive('getTeacherScoreScope')->twice()->with('TCH-A')->andReturn($this->scope);
        $score->shouldNotReceive('create');
        $this->bindWorkspace($score, $this->numericConfig());

        $this->post(route('teacher.workspace.scores.store'), [
            'Student_ID' => 'STU-B',
            'Class_ID' => 'CLS-A',
            'Assessment_Category' => 'UJIAN_BAB',
            'Date' => '2026-10-31',
            'Score' => '88',
        ])->assertForbidden();

        $this->post(route('teacher.workspace.scores.store'), [
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-B',
            'Assessment_Category' => 'UJIAN_BAB',
            'Date' => '2026-10-31',
            'Score' => '88',
        ])->assertForbidden();
    }

    public function test_unscheduled_existing_score_can_be_edited_without_moving_its_owner(): void
    {
        $existing = [
            'Score_ID' => 'SCR-OLD',
            'Student_ID' => 'STU-A',
            'Schedule_ID' => null,
            'Created_By' => 'USR-A',
            'Assessment_Category' => 'UJIAN_BAB',
            'Assessment_Date' => '2026-10-30',
            'Evaluation_Details' => '{"category":"UJIAN_BAB","assessment_date":"2026-10-30"}',
        ];
        $score = Mockery::mock(ScoreService::class);
        $score->shouldReceive('getTeacherScoreScope')->once()->with('TCH-A')->andReturn($this->scope);
        $score->shouldReceive('getById')->once()->with('SCR-OLD')->andReturn($existing);
        $score->shouldReceive('isScoreInTeacherScope')->once()->with($existing, 'TCH-A', Mockery::type('array'))->andReturn(true);
        $score->shouldReceive('update')->once()->with('SCR-OLD', Mockery::on(fn (array $data): bool => $data['Student_ID'] === 'STU-A'
            && ! array_key_exists('Schedule_ID', $data)
            && $data['Assessment_Date'] === '2026-10-31'
            && $data['Class_ID'] === 'CLS-A'
        ));
        $this->bindWorkspace($score, $this->numericConfig());

        $this->put(route('teacher.workspace.scores.update', 'SCR-OLD'), [
            'Assessment_Category' => 'UJIAN_BAB',
            'Date' => '2026-10-31',
            'Score' => '91',
        ])->assertRedirect(route('teacher.workspace.scores'))
            ->assertSessionHas('success', 'Penilaian berhasil diperbarui.');
    }

    public function test_saved_unscheduled_score_is_visible_in_teacher_list_and_class_summary(): void
    {
        $row = [
            'Score_ID' => 'SCR-DIRECT',
            'Student_ID' => 'STU-A',
            'Created_By' => 'USR-A',
            'Schedule_ID' => null,
            'Assessment_Category' => 'UJIAN_BAB',
            'Assessment_Date' => '2026-10-31',
            'Score' => '88',
            'Grade' => 'A',
            'Evaluation_Details' => '{"category":"ujian_bab","assessment_date":"2026-10-31"}',
        ];
        $score = Mockery::mock(ScoreService::class);
        $score->shouldReceive('getTeacherScoreScope')->once()->with('TCH-A')->andReturn($this->scope);
        $score->shouldReceive('getAll')->once()->andReturn(collect([$row]));
        $this->bindWorkspace($score, $this->numericConfig());

        $this->get(route('teacher.workspace.scores'))
            ->assertOk()
            ->assertSee('Siswa Scope')
            ->assertSee('31 Okt 2026')
            ->assertSee('88')
            ->assertSee('assessment');
    }

    public function test_invalid_score_range_is_rejected_before_persistence(): void
    {
        $score = Mockery::mock(ScoreService::class);
        $score->shouldReceive('getTeacherScoreScope')->never();
        $score->shouldNotReceive('create');
        $this->bindWorkspace($score, $this->numericConfig());

        $this->from(route('teacher.workspace.scores.create'))
            ->post(route('teacher.workspace.scores.store'), [
                'Student_ID' => 'STU-A',
                'Class_ID' => 'CLS-A',
                'Assessment_Category' => 'UJIAN_BAB',
                'Date' => '2026-10-31',
                'Score' => '101',
            ])
            ->assertRedirect(route('teacher.workspace.scores.create'))
            ->assertSessionHasErrors('Score');
    }

    private function numericConfig(): AssessmentConfigService
    {
        $config = Mockery::mock(AssessmentConfigService::class);
        $config->shouldReceive('getCategoryConfig')->with('UJIAN_BAB')->andReturn([
            'Category_ID' => 'UJIAN_BAB',
            'Category_Name' => 'Ujian Bab',
            'Is_Active' => 'TRUE',
        ]);
        $config->shouldReceive('isNumericCategory')->with('UJIAN_BAB')->zeroOrMoreTimes()->andReturnTrue();

        return $config;
    }

    private function bindWorkspace(ScoreService $score, ?AssessmentConfigService $config = null): void
    {
        $resolver = Mockery::mock(TeacherScopeResolver::class);
        $resolver->shouldReceive('resolveForUser')->with(Mockery::type(GenericUser::class))->andReturn(['teacher_id' => 'TCH-A']);
        $resolver->shouldReceive('resolveForTeacherId')->with('TCH-A')->zeroOrMoreTimes()->andReturn($this->scope);
        $this->app->instance(TeacherScopeResolver::class, $resolver);
        $this->app->instance(TeacherService::class, Mockery::mock(TeacherService::class));

        $classes = Mockery::mock(ClassService::class);
        $classes->shouldReceive('getAllClasses')->zeroOrMoreTimes()->andReturn([
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A'],
            ['Class_ID' => 'CLS-B', 'Class_Name' => 'Kelas B'],
        ]);
        $this->app->instance(ClassService::class, $classes);
        $schedules = Mockery::mock(ScheduleService::class);
        $schedules->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(ScheduleService::class, $schedules);

        $students = Mockery::mock(StudentService::class);
        $students->shouldReceive('getAllStudents')->zeroOrMoreTimes()->andReturn([
            ['Student_ID' => 'STU-A', 'Full_Name' => 'Siswa Scope', 'Class_ID' => 'CLS-A'],
            ['Student_ID' => 'STU-B', 'Full_Name' => 'Siswa Luar', 'Class_ID' => 'CLS-B'],
        ]);
        $this->app->instance(StudentService::class, $students);
        $this->app->instance(AttendanceService::class, Mockery::mock(AttendanceService::class));
        $this->app->instance(AttendanceRequestService::class, Mockery::mock(AttendanceRequestService::class));
        $subjects = Mockery::mock(SubjectService::class);
        $subjects->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(SubjectService::class, $subjects);
        $this->app->instance(BatchService::class, Mockery::mock(BatchService::class));
        $assignments = Mockery::mock(AssignmentService::class);
        $assignments->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(AssignmentService::class, $assignments);
        $assessments = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessments->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(AssessmentRepositoryInterface::class, $assessments);
        $this->app->instance(ScoreService::class, $score);

        $config ??= Mockery::mock(AssessmentConfigService::class);
        $config->shouldReceive('getActiveCategories')->zeroOrMoreTimes()->andReturn([
            ['Category_ID' => 'UJIAN_BAB', 'Category_Name' => 'Ujian Bab', 'Score_Type' => 'NUMERIC'],
        ]);
        $this->app->instance(AssessmentConfigService::class, $config);
    }
}
