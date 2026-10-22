<?php

namespace Tests\Feature;

use App\Exceptions\EmployeeAttendanceRejectedException;
use App\Services\Core\RoleService;
use App\Services\HR\QRAttendanceService;
use Illuminate\Auth\GenericUser;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EmployeeQrAttendanceResponseTest extends TestCase
{
    public function test_business_rejection_keeps_reason_and_http_status(): void
    {
        $this->actingAsEmployee();
        $service = Mockery::mock(QRAttendanceService::class);
        $service->shouldReceive('processScan')->once()->andThrow(
            new EmployeeAttendanceRejectedException('ALREADY_ATTENDED', 'Sudah tercatat.', 409)
        );
        $this->app->instance(QRAttendanceService::class, $service);

        $this->postJson(route('hr.attendance.qr.scan'), $this->payload())
            ->assertConflict()
            ->assertJson([
                'success' => false,
                'status' => 'rejected',
                'reason_code' => 'ALREADY_ATTENDED',
                'message' => 'Sudah tercatat.',
            ]);
    }

    public function test_unexpected_employee_qr_failure_is_safe_and_traceable(): void
    {
        $this->actingAsEmployee();
        $service = Mockery::mock(QRAttendanceService::class);
        $service->shouldReceive('processScan')->once()->andThrow(
            new RuntimeException('SQLSTATE secret employee query token=do-not-expose')
        );
        $this->app->instance(QRAttendanceService::class, $service);

        $response = $this->postJson(route('hr.attendance.qr.scan'), $this->payload())
            ->assertStatus(500)
            ->assertJsonPath('reason_code', 'SYSTEM_ERROR');

        $this->assertMatchesRegularExpression('/^EQR-[A-Z0-9]{10}$/', $response->json('report_id'));
        $response->assertHeader('X-Request-ID', $response->json('report_id'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('do-not-expose', $response->getContent());
    }

    public function test_unauthenticated_employee_scan_has_structured_response(): void
    {
        $this->postJson(route('hr.attendance.qr.scan'), $this->payload())
            ->assertUnauthorized()
            ->assertJsonPath('reason_code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_employee_prepared_csrf_http_exception_is_rejected_without_false_system_incident(): void
    {
        $roles = Mockery::mock(RoleService::class);
        $roles->shouldReceive('getRoleById')
            ->once()
            ->andThrow(new HttpException(419, 'CSRF token mismatch.'));
        $this->app->instance(RoleService::class, $roles);
        $this->actingAs(new GenericUser([
            'id' => 'USR-EMPLOYEE',
            'User_ID' => 'USR-EMPLOYEE',
            'Role_ID' => 'ROLE-EMPLOYEE',
        ]));

        $this->postJson(route('hr.attendance.qr.scan'), $this->payload())
            ->assertStatus(419)
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('reason_code', 'CSRF_TOKEN_INVALID')
            ->assertJsonMissingPath('report_id');
    }

    private function actingAsEmployee(): void
    {
        $roles = Mockery::mock(RoleService::class);
        $roles->shouldReceive('getRoleById')->with('ROLE-EMPLOYEE')->andReturn([
            'Role_ID' => 'ROLE-EMPLOYEE',
            'Role_Name' => 'EMPLOYEE',
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roles);
        $this->actingAs(new GenericUser([
            'id' => 'USR-EMPLOYEE',
            'User_ID' => 'USR-EMPLOYEE',
            'Role_ID' => 'ROLE-EMPLOYEE',
        ]));
    }

    private function payload(): array
    {
        return [
            'token' => 'opaque-token',
            'latitude' => -6.81234,
            'longitude' => 107.19451,
            'device_info' => 'Regression Test',
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
