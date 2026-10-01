<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Auth\GenericUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class H877QuizRoleAuthorizationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[DataProvider('nonTeacherRoles')]
    public function test_non_teachers_cannot_access_teacher_quiz_crud(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('teacher.quizzes.index'))->assertForbidden();
        $this->post(route('teacher.quizzes.store'), [])->assertForbidden();
        $this->get(route('teacher.quizzes.results'))->assertForbidden();
        $this->get(route('teacher.quizzes.result', 'FORGED'))->assertForbidden();
        $this->get(route('teacher.quizzes.leaderboard', 'FORGED'))->assertForbidden();
        $this->put(route('teacher.quizzes.update', 'FORGED'), [])->assertForbidden();
        $this->delete(route('teacher.quizzes.destroy', 'FORGED'))->assertForbidden();
    }

    #[DataProvider('nonStudentRoles')]
    public function test_non_students_cannot_access_student_quiz_flow(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('student.quizzes.index'))->assertForbidden();
        $this->post(route('student.quizzes.start', 'FORGED'))->assertForbidden();
        $this->get(route('student.quizzes.play', 'FORGED'))->assertForbidden();
        $this->post(route('student.quizzes.submit', 'FORGED'))->assertForbidden();
    }

    public static function nonTeacherRoles(): array
    {
        return [['STUDENT'], ['HR'], ['FINANCE'], ['MARKETING'], ['ACADEMIC'], ['DIRECTOR']];
    }

    public static function nonStudentRoles(): array
    {
        return [['TEACHER'], ['HR'], ['FINANCE'], ['MARKETING'], ['ACADEMIC'], ['DIRECTOR']];
    }

    public function test_teacher_results_reject_forged_class_before_result_reads(): void
    {
        $this->actingAsRole('TEACHER');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $scope->shouldReceive('classesForTeacher')->once()->with('T1')->andReturn(collect([
            ['Class_ID' => 'C1', 'Class_Name' => 'Kelas A'],
            ['Class_ID' => 'C2', 'Class_Name' => 'Kelas B'],
        ]));
        $quizzes = Mockery::mock(QuizService::class);
        $quizzes->shouldNotReceive('teacherResults');
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(QuizService::class, $quizzes);

        $this->get(route('teacher.quizzes.results', ['class' => 'C3']))->assertForbidden();
    }

    private function actingAsRole(string $role): void
    {
        $roleId = 'ROLE-'.$role;
        $this->app->instance(RoleRepositoryInterface::class, new H877RoleRepository([['Role_ID' => $roleId, 'Role_Name' => $role, 'Is_Active' => 'TRUE']]));
        $this->actingAs(new GenericUser(['id' => 'U-'.$role, 'User_ID' => 'U-'.$role, 'Role_ID' => $roleId]));
    }
}

class H877RoleRepository implements RoleRepositoryInterface
{
    public function __construct(private array $rows) {}

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Role_ID', $id);
    }

    public function create(array $data)
    {
        return $data;
    }

    public function update(string $id, array $data)
    {
        return true;
    }
}
