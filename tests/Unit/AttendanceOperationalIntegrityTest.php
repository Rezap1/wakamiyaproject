<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AttendanceService;
use App\Services\Attendance\AutomaticStudentAbsenceService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\SystemSettingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AttendanceOperationalIntegrityTest extends TestCase
{
    public function test_manual_master_batch_is_create_only_and_has_audit_fields(): void
    {
        $repository = Mockery::mock(AttendanceRepositoryInterface::class);
        $repository->shouldReceive('fetchAll')->once()->andReturn(collect());
        $repository->shouldReceive('create')->twice()->with(Mockery::on(fn (array $row): bool => ($row['Attendance_Type'] ?? '') === 'CLASS_QR'
            && ($row['Verification_Method'] ?? '') === 'MASTER_MANUAL'
            && ($row['Created_By'] ?? '') === 'USR-MASTER'
            && str_starts_with((string) ($row['Attendance_ID'] ?? ''), 'ATT-MAN-')
        ))->andReturn(true);

        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->once();
        $service = new AttendanceService($repository, $events);

        $count = $service->recordManualBatch('CLS-A', '2026-10-22', [
            ['Student_ID' => 'STU-A', 'Status' => 'Hadir'],
            ['Student_ID' => 'STU-B', 'Status' => 'Izin', 'Notes' => 'Disetujui MASTER'],
        ], 'USR-MASTER');

        $this->assertSame(2, $count);
    }

    public function test_manual_master_batch_never_overwrites_existing_status(): void
    {
        $repository = Mockery::mock(AttendanceRepositoryInterface::class);
        $repository->shouldReceive('fetchAll')->once()->andReturn(collect([
            $this->attendance('STU-A', 'PRESENT'),
        ]));
        $repository->shouldNotReceive('update');
        $repository->shouldNotReceive('create');
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatch');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah memiliki status');

        (new AttendanceService($repository, $events))->recordManualBatch('CLS-A', '2026-10-22', [
            ['Student_ID' => 'STU-A', 'Status' => 'Alpha'],
        ], 'USR-MASTER');
    }

    public function test_automatic_absence_waits_until_all_student_qr_windows_close(): void
    {
        [$service] = $this->automaticService(
            [],
            [$this->student('STU-A')],
            [[
                'QR_TYPE' => 'STUDENT',
                'STATUS' => 'ACTIVE',
                'ACTIVE_FROM' => '2026-10-01 00:00:00',
                'ACTIVE_UNTIL' => '2026-10-31 23:59:59',
            ]],
            qrEnd: '10:00'
        );

        $result = $service->run(CarbonImmutable::parse('2026-10-22 09:00:00', 'Asia/Jakarta'));

        $this->assertSame('TOO_EARLY', $result['state']);
        $this->assertSame('10:00', $result['cutoff']);
        $this->assertSame(0, $result['created']);
    }

    public function test_automatic_absence_preserves_every_existing_legal_status_and_is_idempotent(): void
    {
        $rows = [
            $this->attendance('STU-PRESENT', 'PRESENT'),
            $this->attendance('STU-SICK', 'SICK'),
            $this->attendance('STU-PERMITTED', 'PERMITTED'),
            $this->attendance('STU-ABSENT', 'ABSENT'),
            [
                'Attendance_ID' => 'ATT-STU-SCHEDULE',
                'Student_ID' => 'STU-SCHEDULE',
                'Class_ID' => '',
                'Schedule_ID' => 'SCH-A',
                'Attendance_Type' => 'SCHEDULE',
                'Attendance_Date' => '2026-10-22',
                'Status' => 'PRESENT',
                'Is_Active' => 'TRUE',
            ],
        ];
        $students = collect(['STU-PRESENT', 'STU-SICK', 'STU-PERMITTED', 'STU-ABSENT', 'STU-SCHEDULE', 'STU-MISSING'])
            ->map(fn (string $id): array => $this->student($id))->all();
        [$service, $rows] = $this->automaticService($rows, $students);

        $first = $service->run(CarbonImmutable::parse('2026-10-22 08:31:00', 'Asia/Jakarta'));
        $second = $service->run(CarbonImmutable::parse('2026-10-22 08:32:00', 'Asia/Jakarta'));

        $this->assertSame(1, $first['created']);
        $this->assertSame(5, $first['skipped']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(6, $second['skipped']);
        $missing = collect($rows->getArrayCopy())->firstWhere('Student_ID', 'STU-MISSING');
        $this->assertSame('ABSENT', $missing['Status']);
        $this->assertSame('SYSTEM_AUTO_ABSENT', $missing['Verification_Method']);
    }

    public function test_automatic_absence_dry_run_never_writes(): void
    {
        [$service, $rows] = $this->automaticService([], [$this->student('STU-A')]);

        $result = $service->run(
            CarbonImmutable::parse('2026-10-22 09:00:00', 'Asia/Jakarta'),
            dryRun: true
        );

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['created']);
        $this->assertSame([], $rows->getArrayCopy());
    }

    /** @return array{0:AutomaticStudentAbsenceService,1:\ArrayObject} */
    private function automaticService(array $initialRows, array $students, array $qrs = [], string $qrEnd = '08:30'): array
    {
        $rows = new \ArrayObject($initialRows);
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->andReturnUsing(fn () => collect($rows->getArrayCopy()));
        $attendance->shouldReceive('create')->andReturnUsing(function (array $row) use ($rows): bool {
            $rows->append($row);

            return true;
        });

        $studentRepository = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepository->shouldReceive('fetchAll')->andReturn(collect($students));
        $qrRepository = Mockery::mock(PermanentQrRepositoryInterface::class);
        $qrRepository->shouldReceive('fetchAll')->andReturn(collect($qrs));

        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('get')->andReturnUsing(fn (string $key, $default = null) => match ($key) {
            'STUDENT_ATTENDANCE_START_TIME' => '06:00',
            'STUDENT_ATTENDANCE_END_TIME' => '08:30',
            default => $default,
        });

        $qrService = Mockery::mock(PermanentQrService::class);
        if ($qrs !== []) {
            $qrService->shouldReceive('getLifecycleConfiguration')->andReturn([
                'valid' => true,
                'from' => CarbonImmutable::parse('2026-10-01 00:00:00', 'Asia/Jakarta'),
                'until' => CarbonImmutable::parse('2026-10-31 23:59:59', 'Asia/Jakarta'),
                'message' => 'ok',
            ]);
            $qrService->shouldReceive('getAttendanceWindowConfiguration')->andReturn([
                'valid' => true,
                'start' => '06:00',
                'end' => $qrEnd,
                'inherited' => false,
                'message' => 'ok',
            ]);
        }

        return [new AutomaticStudentAbsenceService(
            $attendance,
            $studentRepository,
            $qrRepository,
            $settings,
            $qrService
        ), $rows];
    }

    private function student(string $id): array
    {
        return [
            'Student_ID' => $id,
            'Class_ID' => 'CLS-A',
            'Batch_ID' => 'BAT-A',
            'Enrollment_Status' => 'AKTIF',
            'Graduation_Status' => '',
            'Is_Active' => 'TRUE',
        ];
    }

    private function attendance(string $studentId, string $status): array
    {
        return [
            'Attendance_ID' => 'ATT-'.$studentId,
            'Student_ID' => $studentId,
            'Class_ID' => 'CLS-A',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-10-22',
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
