<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AttendanceService;
use App\Services\Attendance\AutomaticStudentAbsenceService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\SystemSettingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        $repository->shouldReceive('clearCache')->once();

        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->once();
        $service = new AttendanceService($repository, $events);

        $count = $service->recordManualBatch('CLS-A', '2026-10-22', [
            ['Student_ID' => 'STU-A', 'Status' => 'Hadir'],
            ['Student_ID' => 'STU-B', 'Status' => 'PRESENT', 'Notes' => 'QR siswa tidak terbaca'],
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
            ['Student_ID' => 'STU-A', 'Status' => 'Hadir'],
        ], 'USR-MASTER');
    }

    public function test_manual_master_batch_rejects_non_present_status_at_service_boundary(): void
    {
        $repository = Mockery::mock(AttendanceRepositoryInterface::class);
        $repository->shouldNotReceive('fetchAll');
        $repository->shouldNotReceive('create');
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatch');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('hanya dapat mencatat status Hadir');

        (new AttendanceService($repository, $events))->recordManualBatch('CLS-A', '2026-10-22', [
            ['Student_ID' => 'STU-A', 'Status' => 'Izin'],
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

    public function test_official_attendance_correction_requires_reason_and_records_actor_audit(): void
    {
        $repository = Mockery::mock(AttendanceRepositoryInterface::class);
        $repository->shouldReceive('findById')->with('ATT-1')->once()->andReturn([
            'Attendance_ID' => 'ATT-1',
            'Status' => 'PRESENT',
        ]);
        $repository->shouldReceive('update')->once()->with('ATT-1', Mockery::on(
            fn (array $data): bool => $data['Status'] === 'SICK'
                && $data['Notes'] === 'Koreksi berdasarkan approval resmi.'
                && $data['Updated_By'] === 'USR-MASTER'
                && trim((string) $data['Updated_At']) !== ''
        ))->andReturn(true);
        $repository->shouldReceive('clearCache')->once();
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatchAudit')->once()->with(
            'ACADEMIC',
            'UPDATE',
            'ATTENDANCE_CORRECTION',
            'ATT-1',
            'USR-MASTER',
            ['ACADEMIC', 'ADMINISTRATOR'],
            [],
            Mockery::on(fn (array $metadata): bool => $metadata['Previous_Status'] === 'PRESENT'
                && $metadata['New_Status'] === 'SICK')
        );

        $result = (new AttendanceService($repository, $events))->update('ATT-1', [
            'Status' => 'SICK',
            'Notes' => 'Koreksi berdasarkan approval resmi.',
        ], 'USR-MASTER');

        $this->assertTrue($result);
    }

    public function test_official_attendance_correction_without_reason_is_rejected(): void
    {
        $repository = Mockery::mock(AttendanceRepositoryInterface::class);
        $repository->shouldReceive('findById')->once()->andReturn([
            'Attendance_ID' => 'ATT-1',
            'Status' => 'PRESENT',
        ]);
        $repository->shouldNotReceive('update');
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatchAudit');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Alasan koreksi');
        (new AttendanceService($repository, $events))->update('ATT-1', ['Status' => 'SICK'], 'USR-MASTER');
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

    public function test_automatic_absence_respects_approved_request_even_if_legacy_official_row_is_missing(): void
    {
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn(collect());
        $attendance->shouldNotReceive('create');
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([$this->student('STU-A')]));
        $qrs = Mockery::mock(PermanentQrRepositoryInterface::class);
        $qrs->shouldReceive('fetchAll')->once()->andReturn(collect());
        $requests = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requests->shouldReceive('fetchAll')->once()->andReturn(collect([[
            'Request_ID' => 'REQ-A',
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-A',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-10-22',
            'Status' => 'APPROVED',
        ]]));
        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('get')->andReturnUsing(fn (string $key, $default = null) => match ($key) {
            'STUDENT_ATTENDANCE_START_TIME' => '06:00',
            'STUDENT_ATTENDANCE_END_TIME' => '08:30',
            default => $default,
        });

        $service = new AutomaticStudentAbsenceService(
            $attendance,
            $students,
            $qrs,
            $settings,
            Mockery::mock(PermanentQrService::class),
            $requests
        );
        $result = $service->run(CarbonImmutable::parse('2026-10-22 09:00:00', 'Asia/Jakarta'));

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_pending_permission_remains_pending_and_follows_documented_absent_until_approved_policy(): void
    {
        $pending = [
            'Request_ID' => 'REQ-PENDING',
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-A',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-10-22',
            'Status' => 'PENDING',
        ];
        [$service, $rows] = $this->automaticService(
            [],
            [$this->student('STU-A')],
            requests: [$pending]
        );

        $result = $service->run(CarbonImmutable::parse('2026-10-22 09:00:00', 'Asia/Jakarta'));

        $this->assertSame(1, $result['created']);
        $this->assertSame('ABSENT', $rows[0]['Status']);
        $this->assertSame('PENDING', $pending['Status']);
    }

    public function test_automatic_absence_failure_returns_traceable_run_id_and_can_be_retried_by_date(): void
    {
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn(collect());
        $attendance->shouldReceive('create')->once()->andReturn(false);
        $studentRepository = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepository->shouldReceive('fetchAll')->once()->andReturn(collect([$this->student('STU-A')]));
        $qrRepository = Mockery::mock(PermanentQrRepositoryInterface::class);
        $qrRepository->shouldReceive('fetchAll')->once()->andReturn(collect());
        $requestRepository = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requestRepository->shouldReceive('fetchAll')->once()->andReturn(collect());
        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('get')->andReturnUsing(fn (string $key, $default = null) => match ($key) {
            'STUDENT_ATTENDANCE_START_TIME' => '06:00',
            'STUDENT_ATTENDANCE_END_TIME' => '08:30',
            default => $default,
        });
        $qrService = Mockery::mock(PermanentQrService::class);

        $this->app->instance(AttendanceRepositoryInterface::class, $attendance);
        $this->app->instance(StudentRepositoryInterface::class, $studentRepository);
        $this->app->instance(PermanentQrRepositoryInterface::class, $qrRepository);
        $this->app->instance(AttendanceRequestRepositoryInterface::class, $requestRepository);
        $this->app->instance(SystemSettingService::class, $settings);
        $this->app->instance(PermanentQrService::class, $qrService);
        Log::spy();

        $exitCode = Artisan::call('attendance:mark-absent', ['--date' => '2026-10-22']);

        $this->assertSame(1, $exitCode);
        $this->assertMatchesRegularExpression('/Kode laporan: ALP-[A-Z0-9]{10}/', Artisan::output());
        Log::shouldHaveReceived('error')->once()->with(
            'Automatic student absence run failed',
            Mockery::on(fn (array $context): bool => str_starts_with($context['run_id'], 'ALP-')
                && $context['target_date'] === '2026-10-22'
                && $context['exception_class'] === \RuntimeException::class)
        );
    }

    /** @return array{0:AutomaticStudentAbsenceService,1:\ArrayObject} */
    private function automaticService(
        array $initialRows,
        array $students,
        array $qrs = [],
        string $qrEnd = '08:30',
        array $requests = []
    ): array {
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
        $requestRepository = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requestRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($requests));

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
            $qrService,
            $requestRepository
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
