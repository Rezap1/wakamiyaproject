<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\SystemParameterRepositoryInterface;
use App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface;
use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Attendance\AttendanceWindowException;
use App\Services\Attendance\AttendanceWindowService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\SystemSettingService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ViewErrorBag;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class H875PermanentQrSettingsSyncTest extends TestCase
{
    #[DataProvider('lifecycleBoundaryProvider')]
    public function test_permanent_qr_lifecycle_uses_inclusive_wib_boundaries(
        string $now,
        string $state,
        bool $usable
    ): void {
        Carbon::setTestNow(CarbonImmutable::parse($now, PermanentQrService::TIMEZONE));
        $result = $this->permanentQrService()->getAvailabilityStatus($this->qrRow());

        $this->assertSame($state, $result['state']);
        $this->assertSame($usable, $result['usable']);
    }

    public static function lifecycleBoundaryProvider(): array
    {
        return [
            'before valid from' => ['2026-10-01 05:59:59', 'UPCOMING', false],
            'exact valid from' => ['2026-10-01 06:00:00', 'ACTIVE', true],
            'inside validity' => ['2026-12-20 12:00:00', 'ACTIVE', true],
            'exact valid until' => ['2027-10-01 20:45:00', 'ACTIVE', true],
            'after valid until' => ['2027-10-01 20:45:01', 'EXPIRED', false],
        ];
    }

    #[DataProvider('invalidLifecycleProvider')]
    public function test_permanent_qr_edit_rejects_invalid_lifecycle_without_update(
        string $from,
        string $until
    ): void {
        $repository = new H875PermanentQrRepositoryFake($this->qrRow());
        $service = $this->permanentQrService($repository);
        $this->actingAs(new GenericUser(['id' => 'USR-MASTER', 'User_ID' => 'USR-MASTER']));

        $this->expectException(InvalidArgumentException::class);

        try {
            $service->updateAvailability('QR00001', [
                'STATUS' => 'ACTIVE',
                'ACTIVE_FROM' => $from,
                'ACTIVE_UNTIL' => $until,
            ]);
        } finally {
            $this->assertNull($repository->lastUpdate);
        }
    }

    public static function invalidLifecycleProvider(): array
    {
        return [
            'missing start' => ['', '2027-10-01 20:45:00'],
            'missing end' => ['2026-10-01 06:00:00', ''],
            'impossible date' => ['2026-02-30 06:00:00', '2027-10-01 20:45:00'],
            'malformed time' => ['2026-10-01 6:00:00', '2027-10-01 20:45:00'],
            'zero duration' => ['2026-10-01 06:00:00', '2026-10-01 06:00:00'],
            'reversed' => ['2027-10-01 20:45:00', '2026-10-01 06:00:00'],
        ];
    }

    public function test_qr_validity_edit_changes_only_lifecycle_and_preserves_printed_identifier(): void
    {
        $repository = new H875PermanentQrRepositoryFake($this->qrRow());
        $activity = Mockery::mock(ActivityLogService::class);
        $activity->shouldReceive('log')->once();
        $service = new PermanentQrService($repository, $activity);
        $this->actingAs(new GenericUser(['id' => 'USR-MASTER', 'User_ID' => 'USR-MASTER']));

        $beforeUrl = $service->getCanonicalQrUrl($repository->row);
        $updated = $service->updateAvailability('QR00001', [
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => '2026-10-02 07:15:00',
            'ACTIVE_UNTIL' => '2027-11-03 21:30:00',
            'IDENTIFIER' => 'FORGED-ROTATION',
            'QR_TYPE' => 'EMPLOYEE',
        ]);

        $this->assertSame('WMS-ATT-STU-UNCHANGED', $updated['IDENTIFIER']);
        $this->assertSame($beforeUrl, $service->getCanonicalQrUrl($updated));
        $this->assertSame([
            'STATUS', 'ACTIVE_FROM', 'ACTIVE_UNTIL', 'ATTENDANCE_START_TIME', 'ATTENDANCE_END_TIME',
            'UPDATED_AT', 'UPDATED_BY', 'DEACTIVATED_AT',
        ], array_keys($repository->lastUpdate));
        $this->assertArrayNotHasKey('IDENTIFIER', $repository->lastUpdate);
        $this->assertArrayNotHasKey('QR_TYPE', $repository->lastUpdate);
    }

    public function test_each_qr_attendance_window_persists_independently_without_changing_global_window(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-19 12:00:00', PermanentQrService::TIMEZONE));

        $repository = new H875MultiPermanentQrRepositoryFake;
        $activity = Mockery::mock(ActivityLogService::class);
        $activity->shouldReceive('log')->times(4);
        $this->actingAs(new GenericUser(['id' => 'USR-MASTER', 'User_ID' => 'USR-MASTER']));

        $settingsRepository = new H875SystemSettingRepositoryFake;
        $settings = new SystemSettingService($settingsRepository, new H875SystemParameterRepositoryFake);
        $service = new PermanentQrService($repository, $activity, $settings);
        $globalBefore = [
            'start' => $settings->get(AttendanceWindowService::START_SETTING_KEY),
            'end' => $settings->get(AttendanceWindowService::END_SETTING_KEY),
        ];

        $qrA = $service->createQr([
            'QR_TYPE' => 'STUDENT',
            'LABEL' => 'QR Siswa Reguler',
            'ACTIVE_FROM' => '2026-10-19 07:20:00',
            'ACTIVE_UNTIL' => '2027-09-02 08:30:00',
            'ATTENDANCE_START_TIME' => '06:15',
            'ATTENDANCE_END_TIME' => '09:15',
        ]);
        $qrB = $service->createQr([
            'QR_TYPE' => 'STUDENT',
            'LABEL' => 'QR Siswa Kelas Karyawan',
            'ACTIVE_FROM' => '2026-10-19 07:30:00',
            'ACTIVE_UNTIL' => '2027-09-01 09:00:00',
            'ATTENDANCE_START_TIME' => '17:30',
            'ATTENDANCE_END_TIME' => '21:00',
        ]);
        $qrC = $service->createQr([
            'QR_TYPE' => 'EMPLOYEE',
            'LABEL' => 'QR Pegawai',
            'ACTIVE_FROM' => '2026-10-19 07:00:00',
            'ACTIVE_UNTIL' => '2027-09-01 17:00:00',
            'ATTENDANCE_START_TIME' => '07:00',
            'ATTENDANCE_END_TIME' => '17:00',
        ]);

        $identifierA = $qrA['IDENTIFIER'];
        $identifierB = $qrB['IDENTIFIER'];
        $service->updateAvailability($qrA['QR_ID'], [
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => '2026-10-19 06:17:00',
            'ACTIVE_UNTIL' => '2027-09-02 20:43:00',
            'ATTENDANCE_START_TIME' => '05:45',
            'ATTENDANCE_END_TIME' => '09:45',
        ]);

        // Recreate the service to prove the values come back from persistent
        // repository state instead of an in-memory return value.
        $reloadedService = new PermanentQrService($repository, $activity, $settings);
        $reloadedA = $reloadedService->getQrById($qrA['QR_ID']);
        $reloadedB = $reloadedService->getQrById($qrB['QR_ID']);
        $reloadedC = $reloadedService->getQrById($qrC['QR_ID']);
        $lifecycleA = $reloadedService->getLifecycleConfiguration($reloadedA);
        $lifecycleB = $reloadedService->getLifecycleConfiguration($reloadedB);

        $this->assertTrue($lifecycleA['valid']);
        $this->assertSame('2026-10-19 06:17', $lifecycleA['from']->format('Y-m-d H:i'));
        $this->assertSame('2027-09-02 20:43', $lifecycleA['until']->format('Y-m-d H:i'));
        $this->assertSame('2026-10-19 07:30', $lifecycleB['from']->format('Y-m-d H:i'));
        $this->assertSame('2027-09-01 09:00', $lifecycleB['until']->format('Y-m-d H:i'));
        $this->assertSame($identifierA, $reloadedA['IDENTIFIER']);
        $this->assertSame($identifierB, $reloadedB['IDENTIFIER']);
        $this->assertSame(['05:45', '09:45'], array_values(array_intersect_key(
            $reloadedService->getAttendanceWindowConfiguration($reloadedA),
            ['start' => true, 'end' => true]
        )));
        $this->assertSame(['17:30', '21:00'], array_values(array_intersect_key(
            $reloadedService->getAttendanceWindowConfiguration($reloadedB),
            ['start' => true, 'end' => true]
        )));
        $this->assertSame(['07:00', '17:00'], array_values(array_intersect_key(
            $reloadedService->getAttendanceWindowConfiguration($reloadedC),
            ['start' => true, 'end' => true]
        )));
        $this->assertSame($globalBefore, [
            'start' => $settings->get(AttendanceWindowService::START_SETTING_KEY),
            'end' => $settings->get(AttendanceWindowService::END_SETTING_KEY),
        ]);
        $this->assertSame([], $settingsRepository->updatedKeys);

        $qrCodes = collect([$reloadedA, $reloadedB, $reloadedC])->map(function (array $qr) use ($reloadedService): array {
            $qr['LIFECYCLE'] = $reloadedService->getLifecycleConfiguration($qr);
            $qr['AVAILABILITY'] = $reloadedService->getAvailabilityStatus($qr);
            $qr['ATTENDANCE_WINDOW'] = $reloadedService->getAttendanceWindowConfiguration($qr);

            return $qr;
        });
        $html = view('attendance.qr.index', [
            'qrCodes' => $qrCodes,
            'studentWindow' => [
                ...$globalBefore,
                'timezone' => AttendanceWindowService::TIMEZONE,
            ],
            'errors' => new ViewErrorBag,
        ])->render();

        $formPattern = '/<form action="'.preg_quote(route('attendance.qr.update', $qrA['QR_ID']), '/').'".*?<\/form>/s';
        $this->assertSame(1, preg_match($formPattern, $html, $matches));
        $this->assertStringContainsString('name="ACTIVE_FROM_TIME" value="06:17"', $matches[0]);
        $this->assertStringContainsString('name="ACTIVE_UNTIL_TIME" value="20:43"', $matches[0]);
        $this->assertStringContainsString('name="ATTENDANCE_START_TIME" value="05:45"', $matches[0]);
        $this->assertStringContainsString('name="ATTENDANCE_END_TIME" value="09:45"', $matches[0]);
        $this->assertStringNotContainsString('name="ACTIVE_FROM_TIME" value="'.$globalBefore['start'].'"', $matches[0]);
        $this->assertStringContainsString($globalBefore['start'].'–'.$globalBefore['end'].' WIB', $html);
    }

    public function test_student_attendance_settings_refresh_immediately_without_late_semantics(): void
    {
        $settingsRepository = new H875SystemSettingRepositoryFake;
        $service = new SystemSettingService($settingsRepository, new H875SystemParameterRepositoryFake);

        [$prepared, $errors] = $service->prepareSettingsForUpdate([
            'WORK_START_TIME' => '06:30',
            'WORK_END_TIME' => '09:00',
        ]);
        $this->assertSame([], $errors);

        $service->set('STUDENT_ATTENDANCE_START_TIME', $prepared['WORK_START_TIME'], 'USR-MASTER');
        $service->set('STUDENT_ATTENDANCE_END_TIME', $prepared['WORK_END_TIME'], 'USR-MASTER');
        $service->clearCache();

        $this->assertSame('06:30', $service->get('STUDENT_ATTENDANCE_START_TIME'));
        $this->assertSame('09:00', $service->get('STUDENT_ATTENDANCE_END_TIME'));

        $windowService = new AttendanceWindowService($service);
        foreach (['06:30:00', '08:45:00', '09:00:00'] as $time) {
            $window = $windowService->resolve(CarbonImmutable::parse("2026-10-01 {$time}", 'Asia/Jakarta'));
            $this->assertSame('PRESENT', $windowService->resolveStudentStatus($window)['status']);
        }

        foreach (['06:29:59', '09:00:01'] as $time) {
            $window = $windowService->resolve(CarbonImmutable::parse("2026-10-01 {$time}", 'Asia/Jakarta'));
            try {
                $windowService->assertOpen($window);
                $this->fail("{$time} seharusnya ditolak.");
            } catch (AttendanceWindowException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(['STUDENT_ATTENDANCE_START_TIME', 'STUDENT_ATTENDANCE_END_TIME'], $settingsRepository->updatedKeys);
    }

    public function test_new_qr_snapshots_global_default_and_old_qr_uses_global_fallback(): void
    {
        Carbon::setTestNow('2026-10-19 12:00:00');
        $settingsRepository = new H875SystemSettingRepositoryFake;
        $settings = new SystemSettingService($settingsRepository, new H875SystemParameterRepositoryFake);
        $repository = new H875MultiPermanentQrRepositoryFake;
        $activity = Mockery::mock(ActivityLogService::class);
        $activity->shouldReceive('log')->once();
        $service = new PermanentQrService($repository, $activity, $settings);
        $this->actingAs(new GenericUser(['id' => 'USR-MASTER', 'User_ID' => 'USR-MASTER']));

        $created = $service->createQr([
            'QR_TYPE' => 'STUDENT',
            'LABEL' => 'QR Default Snapshot',
            'ACTIVE_FROM' => '2026-10-19 06:00:00',
            'ACTIVE_UNTIL' => '2027-10-19 18:00:00',
        ]);
        $this->assertSame(['06:00', '08:30'], [
            $created['ATTENDANCE_START_TIME'], $created['ATTENDANCE_END_TIME'],
        ]);

        $settings->set(AttendanceWindowService::START_SETTING_KEY, '07:00', 'USR-MASTER');
        $settings->set(AttendanceWindowService::END_SETTING_KEY, '10:00', 'USR-MASTER');
        $settings->clearCache();

        $stored = $service->getAttendanceWindowConfiguration($service->getQrById($created['QR_ID']));
        $legacy = $service->getAttendanceWindowConfiguration(['QR_TYPE' => 'STUDENT']);
        $this->assertSame(['06:00', '08:30', false], [$stored['start'], $stored['end'], $stored['inherited']]);
        $this->assertSame(['07:00', '10:00', true], [$legacy['start'], $legacy['end'], $legacy['inherited']]);
    }

    public function test_valid_qr_and_valid_daily_window_creates_present_once(): void
    {
        Carbon::setTestNow('2026-10-01 07:00:00');
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendance->shouldReceive('create')->once()->with(Mockery::on(
            fn (array $row): bool => $row['Status'] === 'PRESENT' && $row['Late_Minutes'] === 0
        ))->andReturn(true);

        $result = $this->studentService($attendance, true)->processStudentScan(
            'https://wms.test/attendance/scan/student/WMS-ATT-STU-UNCHANGED',
            -6.81234,
            107.19451
        );

        $this->assertSame('PRESENT', $result['status']);
    }

    public function test_invalid_qr_inside_daily_window_is_rejected_without_attendance_write(): void
    {
        Carbon::setTestNow('2026-10-01 07:00:00');
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldNotReceive('fetchAll');
        $attendance->shouldNotReceive('create');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('kedaluwarsa');
        $this->studentService($attendance, false)->processStudentScan(
            'https://wms.test/attendance/scan/student/WMS-ATT-STU-UNCHANGED',
            -6.81234,
            107.19451
        );
    }

    public function test_valid_qr_outside_its_own_window_is_rejected_before_attendance_write(): void
    {
        Carbon::setTestNow('2026-10-01 05:59:59');
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldNotReceive('fetchAll');
        $attendance->shouldNotReceive('create');

        $this->expectException(AttendanceWindowException::class);
        $this->studentService($attendance, true, false)->processStudentScan(
            'https://wms.test/attendance/scan/student/WMS-ATT-STU-UNCHANGED',
            -6.81234,
            107.19451
        );
    }

    #[DataProvider('managementViewportProvider')]
    public function test_management_view_has_mobile_safe_controls_and_no_table_overflow_contract(
        int $viewport
    ): void {
        $source = file_get_contents(resource_path('views/attendance/qr/index.blade.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('<table', $source);
        $this->assertStringContainsString('overflow-x-clip', $source);
        $this->assertStringContainsString('grid-cols-1', $source);
        $this->assertStringContainsString('sm:grid-cols-2', $source);
        $this->assertStringContainsString('xl:grid-cols-3', $source);
        $this->assertStringContainsString('break-all', $source);
        $this->assertStringContainsString('min-h-[44px]', $source);
        $this->assertStringContainsString('type="date"', $source);
        $this->assertStringContainsString('type="time"', $source);
        $this->assertStringContainsString("route('attendance.qr.student-settings.update')", $source);
        $this->assertStringContainsString("route('attendance.qr.update'", $source);
        $this->assertStringContainsString('Asia/Jakarta (WIB)', $source);

        if ($viewport < 768) {
            $this->assertStringContainsString('grid-cols-1', $source);
            $this->assertStringContainsString('w-full min-w-0', $source);
        } elseif ($viewport < 1280) {
            $this->assertStringContainsString('md:grid-cols-2', $source);
        } else {
            $this->assertStringContainsString('xl:grid-cols-3', $source);
            $this->assertStringContainsString('max-w-7xl', $source);
        }
    }

    public static function managementViewportProvider(): array
    {
        return [
            '375px' => [375],
            '390px' => [390],
            '430px' => [430],
            'tablet 768px' => [768],
            'desktop 1440px' => [1440],
        ];
    }

    private function permanentQrService(?PermanentQrRepositoryInterface $repository = null): PermanentQrService
    {
        return new PermanentQrService(
            $repository ?? Mockery::mock(PermanentQrRepositoryInterface::class),
            Mockery::mock(ActivityLogService::class)
        );
    }

    private function studentService(
        AttendanceRepositoryInterface $attendance,
        ?bool $qrUsable,
        bool $expectEvent = true
    ): StudentQRAttendanceService {
        $this->actingAs(new GenericUser([
            'id' => 'USR-STUDENT',
            'User_ID' => 'USR-STUDENT',
            'Role' => 'STUDENT',
        ]));

        $student = Mockery::mock(StudentRepositoryInterface::class);
        $student->shouldReceive('fetchAll')->once()->andReturn([[
            'Student_ID' => 'STD-H875',
            'User_ID' => 'USR-STUDENT',
            'Full_Name' => 'Siswa H8.75',
            'Batch_ID' => 'BATCH-1',
            'Class_ID' => 'CLASS-1',
            'Enrollment_Status' => 'AKTIF',
            'Graduation_Status' => '',
            'Is_Active' => 'TRUE',
        ]]);

        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('get')->andReturnUsing(fn (string $key, mixed $default = null): mixed => match ($key) {
            'STUDENT_ATTENDANCE_START_TIME' => '06:00',
            'STUDENT_ATTENDANCE_END_TIME' => '08:30',
            'LPK_LATITUDE' => '-6.81234',
            'LPK_LONGITUDE' => '107.19451',
            'LPK_ALLOWED_RADIUS_METERS' => '20',
            default => $default,
        });

        if ($qrUsable !== null) {
            $qr = Mockery::mock(PermanentQrService::class);
            $qr->shouldReceive('getQrByIdentifier')->once()->andReturn($this->qrRow());
            $qr->shouldReceive('getAvailabilityStatus')->once()->andReturn($qrUsable
                ? ['usable' => true, 'state' => 'ACTIVE', 'message' => 'QR aktif.']
                : ['usable' => false, 'state' => 'EXPIRED', 'message' => 'QR kedaluwarsa.']);
            if ($qrUsable) {
                $qr->shouldReceive('getAttendanceWindowConfiguration')->once()->andReturn([
                    'valid' => true,
                    'start' => '06:00',
                    'end' => '08:30',
                    'inherited' => false,
                    'message' => 'Jam absensi tersimpan khusus untuk QR ini.',
                ]);
            }
            $this->app->instance(PermanentQrService::class, $qr);
        }

        $event = Mockery::mock(EnterpriseEventService::class);
        if ($qrUsable === true && $expectEvent) {
            $event->shouldReceive('dispatch')->once();
        } else {
            $event->shouldNotReceive('dispatch');
        }

        return new StudentQRAttendanceService($attendance, $student, $settings, $event);
    }

    private function qrRow(): array
    {
        return [
            'QR_ID' => 'QR00001',
            'QR_TYPE' => 'STUDENT',
            'IDENTIFIER' => 'WMS-ATT-STU-UNCHANGED',
            'LABEL' => 'QR Siswa',
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => '2026-10-01 06:00:00',
            'ACTIVE_UNTIL' => '2027-10-01 20:45:00',
            'ATTENDANCE_START_TIME' => '06:00',
            'ATTENDANCE_END_TIME' => '08:30',
            'CREATED_AT' => '2026-09-01 10:00:00',
            'CREATED_BY' => 'USR-ADMIN',
            'UPDATED_AT' => '2026-09-01 10:00:00',
            'UPDATED_BY' => 'USR-ADMIN',
            'DEACTIVATED_AT' => '',
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}

class H875PermanentQrRepositoryFake implements PermanentQrRepositoryInterface
{
    public ?array $lastUpdate = null;

    public function __construct(public array $row) {}

    public function fetchAll()
    {
        return collect([$this->row]);
    }

    public function fetchActive()
    {
        return $this->fetchAll();
    }

    public function findByIdentifier(string $identifier)
    {
        return $this->row['IDENTIFIER'] === $identifier ? $this->row : null;
    }

    public function findById(string $id)
    {
        return $this->row['QR_ID'] === $id ? $this->row : null;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return 'QR00002';
    }

    public function create(array $data)
    {
        return true;
    }

    public function update(string $id, array $data)
    {
        $this->lastUpdate = $data;
        $this->row = array_merge($this->row, $data);

        return true;
    }

    public function deactivate(string $id, string $actorUserId)
    {
        return true;
    }

    public function delete(string $id)
    {
        return true;
    }

    public function clearCache() {}
}

class H875MultiPermanentQrRepositoryFake implements PermanentQrRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    private int $nextId = 1;

    public function fetchAll()
    {
        return collect(array_values($this->rows));
    }

    public function fetchActive()
    {
        return $this->fetchAll()->where('STATUS', 'ACTIVE')->values();
    }

    public function findByIdentifier(string $identifier)
    {
        return $this->fetchAll()->firstWhere('IDENTIFIER', $identifier);
    }

    public function findById(string $id)
    {
        return $this->rows[$id] ?? null;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.str_pad((string) $this->nextId++, $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        $this->rows[$data['QR_ID']] = $this->withMysqlDateTimePrecision($data);

        return true;
    }

    public function update(string $id, array $data)
    {
        if (! isset($this->rows[$id])) {
            return false;
        }

        $this->rows[$id] = array_merge($this->rows[$id], $this->withMysqlDateTimePrecision($data));

        return true;
    }

    public function deactivate(string $id, string $actorUserId)
    {
        return $this->update($id, ['STATUS' => 'INACTIVE', 'UPDATED_BY' => $actorUserId]);
    }

    public function delete(string $id)
    {
        if (! isset($this->rows[$id])) {
            return false;
        }

        unset($this->rows[$id]);

        return true;
    }

    public function clearCache() {}

    private function withMysqlDateTimePrecision(array $data): array
    {
        foreach (['ACTIVE_FROM', 'ACTIVE_UNTIL'] as $field) {
            if (isset($data[$field]) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $data[$field])) {
                $data[$field] .= '.000000';
            }
        }
        foreach (['ATTENDANCE_START_TIME', 'ATTENDANCE_END_TIME'] as $field) {
            if (isset($data[$field]) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $data[$field])) {
                $data[$field] .= ':00';
            }
        }

        return $data;
    }
}

class H875SystemSettingRepositoryFake implements SystemSettingRepositoryInterface
{
    public array $updatedKeys = [];

    private array $rows;

    public function __construct()
    {
        $this->rows = [
            'SET_HR_WORK_START_TIME' => $this->row('SET_HR_WORK_START_TIME', 'WORK_START_TIME', '06:00'),
            'SET_HR_WORK_END_TIME' => $this->row('SET_HR_WORK_END_TIME', 'WORK_END_TIME', '08:30'),
            'SET_STUDENT_ATTENDANCE_START_TIME' => $this->row('SET_STUDENT_ATTENDANCE_START_TIME', 'STUDENT_ATTENDANCE_START_TIME', '06:00'),
            'SET_STUDENT_ATTENDANCE_END_TIME' => $this->row('SET_STUDENT_ATTENDANCE_END_TIME', 'STUDENT_ATTENDANCE_END_TIME', '08:30'),
            'SET_HR_LATE_TOLERANCE' => $this->row('SET_HR_LATE_TOLERANCE', 'LATE_TOLERANCE_MINUTES', '30'),
        ];
    }

    public function getAll()
    {
        return collect(array_values($this->rows));
    }

    public function getById($id)
    {
        return collect($this->rows)->first(fn (array $row): bool => $row['Setting_ID'] === $id || $row['Setting_Key'] === $id);
    }

    public function update($id, array $data)
    {
        $this->updatedKeys[] = $data['Setting_Key'];
        $this->rows[$data['Setting_ID']] = array_merge($this->rows[$data['Setting_ID']] ?? [], $data);

        return true;
    }

    public function clearCache() {}

    private function row(string $id, string $key, string $value): array
    {
        return [
            'Setting_ID' => $id,
            'Category' => 'Attendance',
            'Setting_Key' => $key,
            'Setting_Name' => $key,
            'Description' => '',
            'Value_Type' => $key === 'LATE_TOLERANCE_MINUTES' ? 'number' : 'text',
            'Setting_Value' => $value,
        ];
    }
}

class H875SystemParameterRepositoryFake implements SystemParameterRepositoryInterface
{
    public function getAll()
    {
        return collect();
    }

    public function getById($id)
    {
        return null;
    }

    public function update($id, array $data)
    {
        return false;
    }

    public function clearCache() {}
}
