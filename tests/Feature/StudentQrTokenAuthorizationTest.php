<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class StudentQrTokenAuthorizationTest extends TestCase
{
    public function test_student_cannot_generate_student_attendance_token(): void
    {
        $this->mockRole('ROLE-STUDENT', 'STUDENT');

        $qrService = Mockery::mock(StudentQRAttendanceService::class);
        $qrService->shouldNotReceive('generateStudentDynamicToken');
        $this->app->instance(StudentQRAttendanceService::class, $qrService);
        $this->app->instance(StudentRepositoryInterface::class, Mockery::mock(StudentRepositoryInterface::class));

        $this->actingAs($this->user('USR-STUDENT', 'ROLE-STUDENT'));

        $this->getJson('/attendance/student/token')->assertForbidden();
    }

    public function test_academic_can_generate_student_attendance_token(): void
    {
        $this->mockRole('ROLE-ACADEMIC', 'ACADEMIC');

        $qrService = Mockery::mock(StudentQRAttendanceService::class);
        $qrService->shouldReceive('generateStudentDynamicToken')
            ->once()
            ->andReturn([
                'token' => 'signed-student-token',
                'expires_in' => 25,
                'session' => ['Session_ID' => 'STUDENT-QRS-TEST'],
            ]);
        $this->app->instance(StudentQRAttendanceService::class, $qrService);
        $this->app->instance(StudentRepositoryInterface::class, Mockery::mock(StudentRepositoryInterface::class));

        $this->actingAs($this->user('USR-ACADEMIC', 'ROLE-ACADEMIC'));

        $this->getJson('/attendance/student/token')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token', 'signed-student-token');
    }

    private function user(string $userId, string $roleId): GenericUser
    {
        return new GenericUser([
            'id' => $userId,
            'User_ID' => $userId,
            'Role_ID' => $roleId,
        ]);
    }

    private function mockRole(string $roleId, string $roleName): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')
            ->with($roleId)
            ->andReturn([
                'Role_ID' => $roleId,
                'Role_Name' => $roleName,
                'Is_Active' => 'TRUE',
            ]);
        $this->app->instance(RoleService::class, $roleService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
