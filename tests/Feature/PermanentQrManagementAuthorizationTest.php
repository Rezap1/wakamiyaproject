<?php

namespace Tests\Feature;

use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\RoleService;
use App\Services\HR\QRAttendanceService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class PermanentQrManagementAuthorizationTest extends TestCase
{
    public function test_hr_cannot_create_student_qr(): void
    {
        $this->bindControllerDependencies('ROLE-HR', 'HR');
        $this->actingAs($this->user('USR-HR', 'ROLE-HR'));

        $this->post('/attendance/qr', $this->validPayload('STUDENT'))->assertForbidden();
    }

    public function test_academic_cannot_create_employee_qr(): void
    {
        $this->bindControllerDependencies('ROLE-ACADEMIC', 'ACADEMIC');
        $this->actingAs($this->user('USR-ACADEMIC', 'ROLE-ACADEMIC'));

        $this->post('/attendance/qr', $this->validPayload('EMPLOYEE'))->assertForbidden();
    }

    public function test_student_cannot_access_qr_management(): void
    {
        $this->bindControllerDependencies('ROLE-STUDENT', 'STUDENT');
        $this->actingAs($this->user('USR-STUDENT', 'ROLE-STUDENT'));

        $this->get('/attendance/qr')->assertForbidden();
    }

    public function test_academic_can_create_student_qr(): void
    {
        $qrService = $this->bindControllerDependencies('ROLE-ACADEMIC', 'ACADEMIC');
        $qrService->shouldReceive('createQr')
            ->once()
            ->with(Mockery::on(fn (array $data) => $data['QR_TYPE'] === 'STUDENT'))
            ->andReturn(['QR_ID' => 'QR00001']);
        $this->actingAs($this->user('USR-ACADEMIC', 'ROLE-ACADEMIC'));

        $this->post('/attendance/qr', $this->validPayload('STUDENT'))
            ->assertRedirect(route('attendance.qr.index'));
    }

    private function bindControllerDependencies(string $roleId, string $roleName): PermanentQrService
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->with($roleId)->andReturn([
            'Role_ID' => $roleId,
            'Role_Name' => $roleName,
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roleService);

        $qrService = Mockery::mock(PermanentQrService::class);
        $this->app->instance(PermanentQrService::class, $qrService);
        $this->app->instance(StudentQRAttendanceService::class, Mockery::mock(StudentQRAttendanceService::class));
        $this->app->instance(QRAttendanceService::class, Mockery::mock(QRAttendanceService::class));
        $this->app->instance(ActivityLogService::class, Mockery::mock(ActivityLogService::class));

        return $qrService;
    }

    private function validPayload(string $type): array
    {
        return [
            'QR_TYPE' => $type,
            'LABEL' => 'QR Test',
            'ACTIVE_FROM' => '2026-08-24 07:00:00',
            'ACTIVE_UNTIL' => '2026-08-24 18:00:00',
        ];
    }

    private function user(string $userId, string $roleId): GenericUser
    {
        return new GenericUser(['id' => $userId, 'User_ID' => $userId, 'Role_ID' => $roleId]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
