<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\PermanentQrService;
use App\Services\HR\QRAttendanceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\GenericUser;
use App\Services\Core\SystemSettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AttendanceQrFlowTest extends TestCase
{
    public function test_permanent_qr_respects_activation_window(): void
    {
        $service = new PermanentQrService(
            Mockery::mock(PermanentQrRepositoryInterface::class),
            Mockery::mock(ActivityLogService::class)
        );

        $qr = [
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => '2026-08-23 08:00:00',
            'ACTIVE_UNTIL' => '2026-08-23 17:00:00',
        ];

        Carbon::setTestNow('2026-08-23 07:59:00');
        $this->assertSame('SCHEDULED', $service->getAvailabilityStatus($qr)['state']);

        Carbon::setTestNow('2026-08-23 08:30:00');
        $this->assertTrue($service->isQrCurrentlyUsable($qr));

        Carbon::setTestNow('2026-08-23 17:01:00');
        $this->assertSame('EXPIRED', $service->getAvailabilityStatus($qr)['state']);
    }

    public function test_employee_dynamic_qr_token_only_generated_inside_session_window(): void
    {
        Cache::flush();

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $session = [
            'Session_ID' => 'QRS-TEST',
            'Title' => 'Presensi Pegawai',
            'Date' => '2026-08-23',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ];

        Cache::forever('qr_session_QRS-TEST', $session);

        Carbon::setTestNow('2026-08-23 07:30:00');
        $this->expectException(Exception::class);
        $service->generateDynamicToken('QRS-TEST');
    }

    public function test_employee_dynamic_qr_token_is_generated_inside_session_window(): void
    {
        Cache::flush();
        $this->configureEmployeeSettings(25);

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $session = [
            'Session_ID' => 'QRS-TEST',
            'Title' => 'Presensi Pegawai',
            'Date' => '2026-08-23',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ];

        Cache::forever('qr_session_QRS-TEST', $session);

        Carbon::setTestNow('2026-08-23 08:30:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        $this->assertArrayHasKey('token', $token);
        $this->assertSame(25, $token['expires_in']);
        $this->assertSame($session, $token['session']);

        $payload = json_decode(base64_decode($token['token']), true);
        $this->assertSame(25, $payload['expires_at'] - Carbon::now()->timestamp);
    }

    public function test_employee_qr_session_defaults_to_thirty_minute_grace_period(): void
    {
        Cache::flush();

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            $enterpriseEvent
        );

        $this->actingAs(new GenericUser([
            'id' => 'USR-HR',
            'User_ID' => 'USR-HR',
            'Full_Name' => 'HR Auth User',
        ]));

        Carbon::setTestNow('2026-08-23 08:00:00');
        $session = $service->createSession([
            'Title' => 'Presensi Pegawai',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 120,
        ]);

        $this->assertSame(30, $session['Grace_Period']);
        $this->assertSame('HR Auth User', $session['Created_By']);
    }

    public function test_employee_qr_duplicate_scan_is_denied(): void
    {
        Cache::flush();

        $this->disableEmployeeGeofence();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Attendance_ID' => 'ATT-EXISTING',
                'Employee_ID' => 'EMP-1',
                'Session_ID' => 'QRS-TEST',
                'Check_In_Time' => '08:10:00',
                'Is_Active' => 'TRUE',
            ],
        ]);
        $attendanceRepo->shouldNotReceive('create');
        $attendanceRepo->shouldNotReceive('update');

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Employee_ID' => 'EMP-1',
                'User_ID' => 'USR-1',
                'Full_Name' => 'Pegawai Test',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $service = new QRAttendanceService(
            $attendanceRepo,
            $employeeRepo,
            Mockery::mock(EnterpriseEventService::class)
        );

        $session = [
            'Session_ID' => 'QRS-TEST',
            'Title' => 'Presensi Pegawai',
            'Date' => '2026-08-23',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ];

        Cache::forever('qr_session_QRS-TEST', $session);

        Carbon::setTestNow('2026-08-23 08:20:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        $this->actingAs(new GenericUser([
            'id' => 'USR-1',
            'User_ID' => 'USR-1',
        ]));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah melakukan presensi');

        $service->processScan($token['token'], 'Unit Test Device', -6.81234, 107.19451);
    }

    public function test_employee_qr_same_token_allows_different_employees(): void
    {
        Cache::flush();
        $this->disableEmployeeGeofence();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')
            ->twice()
            ->andReturn(
                [],
                [[
                    'Attendance_ID' => 'ATT-A',
                    'Employee_ID' => 'EMP-A',
                    'Session_ID' => 'QRS-TEST',
                    'Is_Active' => 'TRUE',
                ]]
            );
        $attendanceRepo->shouldReceive('create')->twice()->andReturn(true);
        $attendanceRepo->shouldReceive('clearCache')->twice();

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->twice()->andReturn([
            [
                'Employee_ID' => 'EMP-A',
                'User_ID' => 'USR-A',
                'Full_Name' => 'Employee A',
                'Is_Active' => 'TRUE',
            ],
            [
                'Employee_ID' => 'EMP-B',
                'User_ID' => 'USR-B',
                'Full_Name' => 'Employee B',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->twice();

        $service = new QRAttendanceService($attendanceRepo, $employeeRepo, $enterpriseEvent);
        $this->putOpenEmployeeSession();

        Carbon::setTestNow('2026-08-23 08:10:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));
        $first = $service->processScan($token['token'], 'Device A', -6.81234, 107.19451);

        $this->actingAs(new GenericUser(['id' => 'USR-B', 'User_ID' => 'USR-B']));
        $second = $service->processScan($token['token'], 'Device B', -6.81234, 107.19451);

        $this->assertSame('EMP-A', $first['employee']['id']);
        $this->assertSame('EMP-B', $second['employee']['id']);
        $this->assertSame('PRESENT', $first['status']);
        $this->assertSame('PRESENT', $second['status']);
    }

    public function test_employee_qr_time_classification_boundaries(): void
    {
        Cache::flush();
        $this->disableEmployeeGeofence();

        $created = [];
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->twice()->andReturn([], []);
        $attendanceRepo->shouldReceive('create')
            ->twice()
            ->with(Mockery::on(function ($record) use (&$created) {
                $created[] = $record;
                return true;
            }))
            ->andReturn(true);
        $attendanceRepo->shouldReceive('clearCache')->twice();

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->twice()->andReturn([
            ['Employee_ID' => 'EMP-A', 'User_ID' => 'USR-A', 'Full_Name' => 'Employee A', 'Is_Active' => 'TRUE'],
            ['Employee_ID' => 'EMP-B', 'User_ID' => 'USR-B', 'Full_Name' => 'Employee B', 'Is_Active' => 'TRUE'],
        ]);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->twice();

        $service = new QRAttendanceService($attendanceRepo, $employeeRepo, $enterpriseEvent);
        $this->putOpenEmployeeSession();

        Carbon::setTestNow('2026-08-23 08:00:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        Carbon::setTestNow('2026-08-23 08:30:00');
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));
        $onTime = $service->processScan($token['token'], 'Device A', -6.81234, 107.19451);

        Carbon::setTestNow('2026-08-23 08:31:00');
        $this->actingAs(new GenericUser(['id' => 'USR-B', 'User_ID' => 'USR-B']));
        $late = $service->processScan($token['token'], 'Device B', -6.81234, 107.19451);

        $this->assertSame('PRESENT', $onTime['status']);
        $this->assertSame(0, $onTime['late_minutes']);
        $this->assertSame('LATE', $late['status']);
        $this->assertSame(31, $late['late_minutes']);
        $this->assertSame('PRESENT', $created[0]['Status']);
        $this->assertSame('LATE', $created[1]['Status']);
    }

    public function test_employee_qr_token_after_sixty_minutes_is_expired(): void
    {
        Cache::flush();

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );
        $this->putOpenEmployeeSession();

        Carbon::setTestNow('2026-08-23 08:00:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        Carbon::setTestNow('2026-08-23 09:01:00');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('kadaluarsa');

        $service->processScan($token['token'], 'Expired Device');
    }

    public function test_employee_qr_scan_without_gps_is_denied(): void
    {
        Cache::flush();

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );
        $this->putOpenEmployeeSession();

        Carbon::setTestNow('2026-08-23 08:10:00');
        $token = $service->generateDynamicToken('QRS-TEST');
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('GPS wajib aktif');

        $service->processScan($token['token'], 'Device A');
    }

    public function test_student_qr_duplicate_scan_is_denied(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Attendance_ID' => 'ATT-STU-EXISTING',
                'Student_ID' => 'STU-A',
                'Attendance_Date' => '2026-08-23',
                'Check_In_Time' => '08:05:00',
                'Is_Active' => 'TRUE',
            ],
        ]);
        $attendanceRepo->shouldNotReceive('create');
        $attendanceRepo->shouldNotReceive('update');

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $this->studentRepositoryForQr(),
            $this->studentQrSettings(),
            Mockery::mock(EnterpriseEventService::class)
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah melakukan presensi');

        $service->processStudentScan($this->studentToken(), -6.81234, 107.19451, 'Student Device');
    }

    public function test_student_qr_late_minutes_are_calculated_from_start_time(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $created = null;
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendanceRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($record) use (&$created) {
                $created = $record;
                return true;
            }))
            ->andReturn(true);
        $attendanceRepo->shouldReceive('clearCache')->once();

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $this->studentRepositoryForQr(),
            $this->studentQrSettings(),
            $enterpriseEvent
        );

        Carbon::setTestNow('2026-08-23 08:16:00');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));

        $result = $service->processStudentScan($this->studentToken(), -6.81234, 107.19451, 'Student Device');

        $this->assertSame('LATE', $result['status']);
        $this->assertSame(76, $result['late_minutes']);
        $this->assertSame(76, $created['Late_Minutes']);
    }

    public function test_student_qr_scan_outside_twenty_meter_geofence_is_denied(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldNotReceive('fetchAll');
        $attendanceRepo->shouldNotReceive('create');

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            Mockery::mock(StudentRepositoryInterface::class),
            $this->studentQrSettings(),
            Mockery::mock(EnterpriseEventService::class)
        );

        Carbon::setTestNow('2026-08-23 08:10:00');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('di luar area LPK');

        $service->processStudentScan($this->studentToken(), -6.80000, 107.19451, 'Student Device');
    }

    private function putOpenEmployeeSession(): void
    {
        Cache::forever('qr_session_QRS-TEST', [
            'Session_ID' => 'QRS-TEST',
            'Title' => 'Presensi Pegawai',
            'Date' => '2026-08-23',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ]);
    }

    private function disableEmployeeGeofence(): void
    {
        $this->configureEmployeeSettings(3600);
    }

    private function configureEmployeeSettings(int $ttl): void
    {
        $settingService = Mockery::mock(SystemSettingService::class);
        $settingService->shouldReceive('get')->andReturnUsing(function ($key, $default = null) use ($ttl) {
            return match ($key) {
                'LPK_LATITUDE' => -6.81234,
                'LPK_LONGITUDE' => 107.19451,
                'LPK_ALLOWED_RADIUS_METERS' => 100,
                'QR_TOKEN_TTL_SECONDS' => $ttl,
                default => $default,
            };
        });
        $this->app->instance(SystemSettingService::class, $settingService);
    }

    private function putOpenStudentSession(): void
    {
        Cache::forever('student_qr_session_STUDENT-QRS-2026-08-23', [
            'Session_ID' => 'STUDENT-QRS-2026-08-23',
            'Title' => 'Presensi Siswa',
            'Type' => 'STUDENT',
            'Date' => '2026-08-23',
            'Start_Time' => '07:00',
            'End_Time' => '18:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ]);
    }

    private function studentToken(): string
    {
        $sessionId = 'STUDENT-QRS-2026-08-23';
        $expiresAt = Carbon::now()->addSeconds(25)->timestamp;
        $nonce = 'STU-NONCE-' . Str::random(6);
        $qrType = 'STUDENT';
        $signature = hash_hmac('sha256', "{$qrType}|{$sessionId}|{$expiresAt}|{$nonce}", (string) config('app.key'));
        Cache::put("qr_student_nonce_{$nonce}", $sessionId, 60);

        return base64_encode(json_encode([
            'qr_type' => $qrType,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
            'sig' => $signature,
        ]));
    }

    private function studentRepositoryForQr(): StudentRepositoryInterface
    {
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Student_ID' => 'STU-A',
                'User_ID' => 'USR-STU',
                'Full_Name' => 'Student A',
                'Batch_ID' => 'BATCH-1',
                'Class_ID' => 'CLASS-1',
                'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '',
                'Is_Active' => 'TRUE',
            ],
        ]);

        return $studentRepo;
    }

    private function studentQrSettings(): SystemSettingService
    {
        $settingService = Mockery::mock(SystemSettingService::class);
        $settingService->shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            return match ($key) {
                'LPK_LATITUDE' => -6.81234,
                'LPK_LONGITUDE' => 107.19451,
                'LPK_ALLOWED_RADIUS_METERS' => 20,
                default => $default,
            };
        });

        return $settingService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
