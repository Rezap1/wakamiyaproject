<?php

namespace Tests\Feature;

use App\Exceptions\StudentAttendanceRejectedException;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Attendance\AttendanceWindowException;
use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StudentQrAttendanceResponseTest extends TestCase
{
    public function test_success_response_uses_stable_contract(): void
    {
        $this->actingAsStudent();
        $this->mockScannerService()->shouldReceive('processStudentScan')
            ->once()
            ->andReturn([
                'action' => 'CHECK_IN',
                'status' => 'PRESENT',
                'check_in_time' => '07:15:00',
                'distance_meters' => 4.2,
            ]);

        $this->postJson(route('attendances.student.scan'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'recorded')
            ->assertJsonPath('reason_code', null)
            ->assertJsonPath('data.status', 'PRESENT');
    }

    #[DataProvider('knownRejectionProvider')]
    public function test_known_rejection_returns_exact_reason_and_message(
        string $reasonCode,
        string $message,
        int $httpStatus
    ): void {
        $this->actingAsStudent();
        $this->mockScannerService()->shouldReceive('processStudentScan')
            ->once()
            ->andThrow(new StudentAttendanceRejectedException($reasonCode, $message, $httpStatus));

        $response = $this->postJson(route('attendances.student.scan'), $this->validPayload())
            ->assertStatus($httpStatus);

        $expectedMessage = $httpStatus >= 500
            ? $message.' Kode laporan: '.$response->json('report_id').'.'
            : $message;
        $response->assertJson([
            'success' => false,
            'status' => 'rejected',
            'reason_code' => $reasonCode,
            'message' => $expectedMessage,
            'data' => null,
        ]);
        if ($httpStatus >= 500) {
            $this->assertMatchesRegularExpression('/^SQR-[A-Z0-9]{10}$/', $response->json('report_id'));
            $response->assertHeader('X-Request-ID', $response->json('report_id'));
        }
    }

    public static function knownRejectionProvider(): array
    {
        return [
            'expired QR' => [
                'QR_EXPIRED',
                'Presensi ditolak: QR sudah kedaluwarsa. Silakan minta QR presensi yang masih berlaku.',
                422,
            ],
            'invalid signature' => [
                'QR_INVALID',
                'Presensi ditolak: QR tidak valid atau tidak dapat diverifikasi.',
                422,
            ],
            'unmapped student' => [
                'STUDENT_PROFILE_NOT_FOUND',
                'Presensi ditolak: profil siswa belum terhubung dengan akun Anda. Silakan hubungi administrator.',
                422,
            ],
            'duplicate retry' => [
                'ALREADY_ATTENDED',
                'Presensi tidak dicatat ulang karena Anda sudah melakukan presensi pada sesi ini.',
                409,
            ],
            'database write rejected' => [
                'ATTENDANCE_WRITE_FAILED',
                'Presensi belum dapat disimpan karena terjadi gangguan sistem. Silakan coba kembali nanti.',
                503,
            ],
        ];
    }

    public function test_time_window_rejection_preserves_reason_code(): void
    {
        $this->actingAsStudent();
        $this->mockScannerService()->shouldReceive('processStudentScan')
            ->once()
            ->andThrow(new AttendanceWindowException('TOO_LATE', 'Waktu absensi telah berakhir pada pukul 08:30.'));

        $this->postJson(route('attendances.student.scan'), $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonPath('reason_code', 'TOO_LATE')
            ->assertJsonPath('message', 'Presensi ditolak: Waktu absensi telah berakhir pada pukul 08:30.');
    }

    public function test_validation_failure_is_clear_and_does_not_echo_payload(): void
    {
        $this->actingAsStudent();
        $this->mockScannerService()->shouldNotReceive('processStudentScan');

        $response = $this->postJson(route('attendances.student.scan'), [
            'token' => 'secret-token-must-not-be-returned',
            'longitude' => 107.1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('reason_code', 'REQUEST_VALIDATION_FAILED');
        $this->assertStringNotContainsString('secret-token-must-not-be-returned', $response->getContent());
    }

    public function test_unexpected_failure_returns_safe_report_id_and_logs_technical_context(): void
    {
        Log::spy();
        $this->actingAsStudent();
        $this->mockScannerService()->shouldReceive('processStudentScan')
            ->once()
            ->andThrow(new RuntimeException('SQLSTATE secret query token=do-not-expose'));

        $response = $this->postJson(route('attendances.student.scan'), $this->validPayload());

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('reason_code', 'SYSTEM_ERROR');

        $body = $response->json();
        $this->assertMatchesRegularExpression('/^SQR-[A-Z0-9]{10}$/', $body['report_id']);
        $this->assertStringContainsString($body['report_id'], $body['message']);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('do-not-expose', $response->getContent());
        Log::shouldHaveReceived('error')->once()->with(
            'Unexpected student QR attendance failure',
            Mockery::on(fn (array $context): bool => $context['report_id'] === $body['report_id']
                && $context['exception_class'] === RuntimeException::class)
        );
    }

    public function test_non_student_role_gets_structured_access_denial(): void
    {
        $this->mockRole('ROLE-TEACHER', 'TEACHER');
        $this->actingAs($this->user('USR-TEACHER', 'ROLE-TEACHER'));
        $this->mockScannerService()->shouldNotReceive('processStudentScan');

        $this->postJson(route('attendances.student.scan'), $this->validPayload())
            ->assertForbidden()
            ->assertJsonPath('reason_code', 'ACCESS_DENIED');
    }

    public function test_middleware_failure_is_safe_and_traceable(): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')
            ->once()
            ->andThrow(new RuntimeException('SQLSTATE middleware secret'));
        $this->app->instance(RoleService::class, $roleService);
        $this->actingAs($this->user('USR-STUDENT', 'ROLE-STUDENT'));
        $this->mockScannerService()->shouldNotReceive('processStudentScan');

        $response = $this->postJson(route('attendances.student.scan'), $this->validPayload());

        $response->assertStatus(500)
            ->assertJsonPath('reason_code', 'SYSTEM_ERROR');
        $this->assertMatchesRegularExpression('/^SQR-[A-Z0-9]{10}$/', $response->json('report_id'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('middleware secret', $response->getContent());
    }

    public function test_unauthenticated_request_gets_structured_rejection(): void
    {
        $this->postJson(route('attendances.student.scan'), $this->validPayload())
            ->assertUnauthorized()
            ->assertJsonPath('reason_code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_prepared_csrf_http_exception_gets_structured_rejection_instead_of_report_id(): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')
            ->once()
            ->andThrow(new HttpException(419, 'CSRF token mismatch.'));
        $this->app->instance(RoleService::class, $roleService);
        $this->actingAs($this->user('USR-STUDENT', 'ROLE-STUDENT'));

        $this->postJson(route('attendances.student.scan'), $this->validPayload())
            ->assertStatus(419)
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('reason_code', 'CSRF_TOKEN_INVALID')
            ->assertJsonMissingPath('report_id');
    }

    public function test_scanner_frontend_reads_http_error_body_and_reason_code(): void
    {
        $view = file_get_contents(resource_path('views/academic/attendances/qr_scanner.blade.php'));

        $this->assertStringContainsString('data = await response.json()', $view);
        $this->assertStringContainsString("data.reason_code || 'UNKNOWN_ERROR'", $view);
        $this->assertStringContainsString("reasonCode === 'OUTSIDE_GEOFENCE'", $view);
        $this->assertStringContainsString('x-text="errorMessage"', $view);
    }

    private function actingAsStudent(): void
    {
        $this->mockRole('ROLE-STUDENT', 'STUDENT');
        $this->actingAs($this->user('USR-STUDENT', 'ROLE-STUDENT'));
    }

    private function mockScannerService(): StudentQRAttendanceService
    {
        $service = Mockery::mock(StudentQRAttendanceService::class);
        $this->app->instance(StudentQRAttendanceService::class, $service);
        $this->app->instance(StudentRepositoryInterface::class, Mockery::mock(StudentRepositoryInterface::class));

        return $service;
    }

    private function mockRole(string $roleId, string $roleName): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->with($roleId)->andReturn([
            'Role_ID' => $roleId,
            'Role_Name' => $roleName,
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roleService);
    }

    private function user(string $userId, string $roleId): GenericUser
    {
        return new GenericUser([
            'id' => $userId,
            'User_ID' => $userId,
            'Role_ID' => $roleId,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'token' => 'opaque-qr-token',
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
