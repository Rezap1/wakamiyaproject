<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class H877QuizRoleAuthorizationTest extends TestCase
{
    #[DataProvider('nonTeacherRoles')]
    public function test_non_teachers_cannot_access_teacher_quiz_crud(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('teacher.quizzes.index'))->assertForbidden();
        $this->post(route('teacher.quizzes.store'), [])->assertForbidden();
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
