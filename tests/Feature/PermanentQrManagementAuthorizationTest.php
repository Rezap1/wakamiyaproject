<?php

namespace Tests\Feature;

use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
use App\Services\HR\QRAttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermanentQrManagementAuthorizationTest extends TestCase
{
    #[DataProvider('unauthorizedRoleProvider')]
    public function test_non_privileged_roles_cannot_access_or_forge_qr_management(
        string $roleId,
        string $roleName
    ): void {
        $this->bindControllerDependencies($roleId, $roleName);
        $this->actingAs($this->user("USR-{$roleName}", $roleId));

        $this->get('/attendance/qr')->assertForbidden();
        $this->patch('/attendance/qr/QR00001', $this->validEditPayload())->assertForbidden();
        $this->post('/attendance/qr/student-settings', [
            'WORK_START_TIME' => '06:30',
            'WORK_END_TIME' => '09:00',
        ])->assertForbidden();
    }

    public static function unauthorizedRoleProvider(): array
    {
        return [
            'student' => ['ROLE-STUDENT', 'STUDENT'],
            'teacher' => ['ROLE-TEACHER', 'TEACHER'],
            'hr' => ['ROLE-HR', 'HR'],
            'finance' => ['ROLE-FINANCE', 'FINANCE'],
            'marketing' => ['ROLE-MARKETING', 'MARKETING'],
            'academic' => ['ROLE-ACADEMIC', 'ACADEMIC'],
        ];
    }

    #[DataProvider('privilegedRoleProvider')]
    public function test_master_and_administrator_can_edit_qr_without_rotating_identifier(
        string $roleId,
        string $roleName
    ): void {
        [$qrService] = $this->bindControllerDependencies($roleId, $roleName);
        $qrService->shouldReceive('getQrById')->once()->with('QR00001')->andReturn([
            'QR_ID' => 'QR00001',
            'QR_TYPE' => 'STUDENT',
            'IDENTIFIER' => 'WMS-ATT-STU-UNCHANGED',
        ]);
        $qrService->shouldReceive('updateAvailability')
            ->once()
            ->with('QR00001', [
                'STATUS' => 'ACTIVE',
                'ACTIVE_FROM' => '2026-10-01 06:15:00',
                'ACTIVE_UNTIL' => '2027-10-01 20:45:00',
                'ATTENDANCE_START_TIME' => '06:30',
                'ATTENDANCE_END_TIME' => '09:00',
            ]);
        $this->actingAs($this->user("USR-{$roleName}", $roleId));

        $this->patch('/attendance/qr/QR00001', $this->validEditPayload())
            ->assertRedirect(route('attendance.qr.index'));
    }

    #[DataProvider('privilegedRoleProvider')]
    public function test_master_and_administrator_can_update_student_attendance_window(
        string $roleId,
        string $roleName
    ): void {
        [, $settings] = $this->bindControllerDependencies($roleId, $roleName);
        $settings->shouldReceive('prepareSettingsForUpdate')->once()->andReturn([
            ['WORK_START_TIME' => '06:30', 'WORK_END_TIME' => '09:00'],
            [],
        ]);
        $settings->shouldReceive('set')->once()->with('STUDENT_ATTENDANCE_START_TIME', '06:30', "USR-{$roleName}");
        $settings->shouldReceive('set')->once()->with('STUDENT_ATTENDANCE_END_TIME', '09:00', "USR-{$roleName}");
        $settings->shouldReceive('reloadCache')->once();
        $this->actingAs($this->user("USR-{$roleName}", $roleId));

        $this->post('/attendance/qr/student-settings', [
            'WORK_START_TIME' => '06:30',
            'WORK_END_TIME' => '09:00',
        ])
            ->assertRedirect(route('attendance.qr.index'));
    }

    public static function privilegedRoleProvider(): array
    {
        return [
            'administrator' => ['ROLE-ADMIN', 'ADMINISTRATOR'],
            'master' => ['ROLE-MASTER', 'MASTER'],
        ];
    }

    public function test_management_page_renders_mysql_datetime_six_lifecycle_instead_of_invalid_configuration(): void
    {
        [$qrService, $settings] = $this->bindControllerDependencies('ROLE-MASTER', 'MASTER');
        $qr = [
            'QR_ID' => 'QR00001',
            'QR_TYPE' => 'STUDENT',
            'IDENTIFIER' => 'WMS-ATT-STU-UNCHANGED',
            'LABEL' => 'Presensi Utama',
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => '2026-10-01 06:15:00.000000',
            'ACTIVE_UNTIL' => '2027-10-01 20:45:00.654321',
            'CREATED_AT' => '2026-10-01 06:00:00.000000',
        ];

        $qrService->shouldReceive('getAllQrCodes')->once()->andReturn([$qr]);
        $qrService->shouldReceive('getLifecycleConfiguration')->once()->with($qr)->andReturn([
            'valid' => true,
            'from' => CarbonImmutable::parse('2026-10-01 06:15:00.000000', 'Asia/Jakarta'),
            'until' => CarbonImmutable::parse('2027-10-01 20:45:00.654321', 'Asia/Jakarta'),
            'message' => 'Masa berlaku QR Presensi valid.',
        ]);
        $qrService->shouldReceive('getAvailabilityStatus')->once()->with($qr)->andReturn([
            'usable' => true,
            'state' => 'ACTIVE',
            'message' => 'QR Presensi aktif.',
        ]);
        $qrService->shouldReceive('getAttendanceWindowConfiguration')->once()->with($qr)->andReturn([
            'valid' => true,
            'start' => '06:30',
            'end' => '09:00',
            'inherited' => false,
            'message' => 'Jam absensi tersimpan khusus untuk QR ini.',
        ]);
        $settings->shouldReceive('get')->twice()->andReturn('06:30', '09:00');
        $this->actingAs($this->user('USR-MASTER', 'ROLE-MASTER'));

        $this->get('/attendance/qr')
            ->assertOk()
            ->assertDontSee('Konfigurasi tidak valid')
            ->assertSee('value="2026-10-01"', false)
            ->assertSee('value="06:15"', false)
            ->assertSee('value="2027-10-01"', false)
            ->assertSee('value="20:45"', false);
    }

    public function test_preview_print_and_pdf_keep_the_qr_identifier_and_its_own_lifecycle(): void
    {
        [$qrService] = $this->bindControllerDependencies('ROLE-MASTER', 'MASTER');
        $qr = [
            'QR_ID' => 'QR00001',
            'QR_TYPE' => 'STUDENT',
            'IDENTIFIER' => 'WMS-ATT-STU-UNCHANGED',
            'LABEL' => 'Presensi Utama',
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => '2026-10-19 06:17:00.000000',
            'ACTIVE_UNTIL' => '2027-09-02 20:43:00.000000',
        ];
        $lifecycle = [
            'valid' => true,
            'from' => CarbonImmutable::parse($qr['ACTIVE_FROM'], 'Asia/Jakarta'),
            'until' => CarbonImmutable::parse($qr['ACTIVE_UNTIL'], 'Asia/Jakarta'),
            'message' => 'Masa berlaku QR Presensi valid.',
        ];
        $availability = [
            'usable' => true,
            'state' => 'ACTIVE',
            'message' => 'QR Presensi aktif.',
        ];

        $qrService->shouldReceive('getQrById')->times(3)->with('QR00001')->andReturn($qr);
        $qrService->shouldReceive('getLifecycleConfiguration')->twice()->with($qr)->andReturn($lifecycle);
        $qrService->shouldReceive('getAvailabilityStatus')->twice()->with($qr)->andReturn($availability);
        $qrService->shouldReceive('getAttendanceWindowConfiguration')->twice()->with($qr)->andReturn([
            'valid' => true,
            'start' => '06:30',
            'end' => '09:00',
            'inherited' => false,
            'message' => 'Jam absensi tersimpan khusus untuk QR ini.',
        ]);
        $qrService->shouldReceive('getCanonicalQrUrl')->once()->with($qr)
            ->andReturn('https://wms.test/attendance/scan/student/WMS-ATT-STU-UNCHANGED');

        $activity = Mockery::mock(ActivityLogService::class);
        $activity->shouldReceive('log')->twice();
        $this->app->instance(ActivityLogService::class, $activity);

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        Pdf::shouldReceive('loadView')->once()->with('attendance.qr.print', Mockery::on(
            fn (array $data): bool => ($data['qr']['IDENTIFIER'] ?? '') === 'WMS-ATT-STU-UNCHANGED'
                && ($data['lifecycle']['from'] ?? null)?->format('H:i') === '06:17'
                && ($data['lifecycle']['until'] ?? null)?->format('H:i') === '20:43'
                && ($data['attendanceWindow']['start'] ?? null) === '06:30'
                && ($data['attendanceWindow']['end'] ?? null) === '09:00'
        ))->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('QR_Presensi_WMS-ATT-STU-UNCHANGED.pdf')
            ->andReturn(response('pdf-body', 200, ['Content-Type' => 'application/pdf']));

        $this->actingAs($this->user('USR-MASTER', 'ROLE-MASTER'));

        $this->get('/attendance/qr/QR00001/preview')
            ->assertOk()
            ->assertSee(route('attendance.qr.print', 'QR00001'), false);
        $this->get('/attendance/qr/QR00001/print')
            ->assertOk()
            ->assertSee('WMS-ATT-STU-UNCHANGED')
            ->assertSee('19-10-2026 06:17')
            ->assertSee('02-09-2027 20:43');
        $this->get('/attendance/qr/QR00001/pdf')
            ->assertOk()
            ->assertSee('pdf-body');
    }

    public function test_invalid_student_attendance_window_is_rejected_without_any_setting_write(): void
    {
        [, $settings] = $this->bindControllerDependencies('ROLE-MASTER', 'MASTER');
        $settings->shouldReceive('prepareSettingsForUpdate')->once()->andReturn([
            ['WORK_START_TIME' => '09:00', 'WORK_END_TIME' => '06:30'],
            ['Jam selesai absensi harus setelah jam mulai pada hari yang sama.'],
        ]);
        $settings->shouldNotReceive('set');
        $settings->shouldNotReceive('reloadCache');
        $this->actingAs($this->user('USR-MASTER', 'ROLE-MASTER'));

        $this->from('/attendance/qr')->post('/attendance/qr/student-settings', [
            'WORK_START_TIME' => '09:00',
            'WORK_END_TIME' => '06:30',
        ])
            ->assertRedirect('/attendance/qr')
            ->assertSessionHasErrors('attendance_settings');
    }

    public function test_reversed_qr_lifecycle_is_rejected_with_clear_error_before_write(): void
    {
        [$qrService] = $this->bindControllerDependencies('ROLE-MASTER', 'MASTER');
        $qrService->shouldReceive('getLifecycleConfiguration')->once()->with([
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => '2026-10-02 09:00:00',
            'ACTIVE_UNTIL' => '2026-10-02 08:00:00',
            'ATTENDANCE_START_TIME' => '06:30',
            'ATTENDANCE_END_TIME' => '09:00',
        ])->andReturn([
            'valid' => false,
            'from' => null,
            'until' => null,
            'message' => 'Waktu berakhir QR harus setelah waktu mulai berlaku.',
        ]);
        $qrService->shouldNotReceive('getQrById');
        $qrService->shouldNotReceive('updateAvailability');
        $this->actingAs($this->user('USR-MASTER', 'ROLE-MASTER'));

        $this->from('/attendance/qr')->patch('/attendance/qr/QR00001', [
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM_DATE' => '2026-10-02',
            'ACTIVE_FROM_TIME' => '09:00',
            'ACTIVE_UNTIL_DATE' => '2026-10-02',
            'ACTIVE_UNTIL_TIME' => '08:00',
            'ATTENDANCE_START_TIME' => '06:30',
            'ATTENDANCE_END_TIME' => '09:00',
        ])
            ->assertRedirect('/attendance/qr')
            ->assertSessionHasErrors([
                'ACTIVE_UNTIL_DATE' => 'Waktu berakhir QR harus setelah waktu mulai berlaku.',
            ]);
    }

    private function bindControllerDependencies(string $roleId, string $roleName): array
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->with($roleId)->andReturn([
            'Role_ID' => $roleId,
            'Role_Name' => $roleName,
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roleService);

        $qrService = Mockery::mock(PermanentQrService::class);
        $qrService->shouldReceive('getLifecycleConfiguration')->byDefault()->andReturn([
            'valid' => true,
            'from' => null,
            'until' => null,
            'message' => 'Masa berlaku QR Presensi valid.',
        ]);
        $settingService = Mockery::mock(SystemSettingService::class);
        $this->app->instance(PermanentQrService::class, $qrService);
        $this->app->instance(SystemSettingService::class, $settingService);
        $this->app->instance(StudentQRAttendanceService::class, Mockery::mock(StudentQRAttendanceService::class));
        $this->app->instance(QRAttendanceService::class, Mockery::mock(QRAttendanceService::class));
        $this->app->instance(ActivityLogService::class, Mockery::mock(ActivityLogService::class));

        return [$qrService, $settingService];
    }

    private function validEditPayload(): array
    {
        return [
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM_DATE' => '2026-10-01',
            'ACTIVE_FROM_TIME' => '06:15',
            'ACTIVE_UNTIL_DATE' => '2027-10-01',
            'ACTIVE_UNTIL_TIME' => '20:45',
            'ATTENDANCE_START_TIME' => '06:30',
            'ATTENDANCE_END_TIME' => '09:00',
            'IDENTIFIER' => 'FORGED-ROTATION',
            'QR_TYPE' => 'EMPLOYEE',
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
