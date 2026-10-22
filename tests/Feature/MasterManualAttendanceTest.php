<?php

namespace Tests\Feature;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AttendanceService;
use App\Services\Attendance\MasterManualAttendanceService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class MasterManualAttendanceTest extends TestCase
{
    public function test_roster_excludes_every_official_status_and_active_permission_request(): void
    {
        $official = collect(['PRESENT', 'LATE', 'SICK', 'PERMITTED', 'ABSENT'])->map(
            fn (string $status, int $index): array => $this->attendance('STU-'.($index + 1), $status)
        )->all();
        $students = collect(range(1, 8))->map(fn (int $index): array => $this->student('STU-'.$index))->all();
        $students[] = array_merge($this->student('STU-INACTIVE'), ['Is_Active' => 'FALSE']);
        $requests = [
            $this->permissionRequest('STU-6', 'PENDING'),
            $this->permissionRequest('STU-7', 'APPROVED'),
        ];

        $service = $this->service($official, $students, $requests);
        $roster = $service->roster('BAT-A', 'CLS-A', '2026-10-22');

        $this->assertSame(['STU-8'], array_column($roster['eligible'], 'Student_ID'));
        $this->assertCount(7, $roster['blocked']);
        $this->assertSame(
            ['ABSENT', 'LATE', 'PERMITTED', 'PRESENT', 'SICK'],
            collect($roster['blocked'])->where('source', 'OFFICIAL_ATTENDANCE')->pluck('status')->sort()->values()->all()
        );
        $this->assertSame(
            ['APPROVED', 'PENDING'],
            collect($roster['blocked'])->where('source', 'ATTENDANCE_REQUEST')->pluck('status')->sort()->values()->all()
        );
        $this->assertNotContains('STU-INACTIVE', array_column($roster['eligible'], 'Student_ID'));
    }

    public function test_manual_record_stores_present_reason_actor_timestamp_and_source(): void
    {
        $created = null;
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->times(3)->andReturn(collect());
        $attendance->shouldReceive('create')->once()->andReturnUsing(function (array $row) use (&$created): bool {
            $created = $row;

            return true;
        });
        $attendance->shouldReceive('clearCache')->once();
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->once();

        $service = $this->service(
            [],
            [$this->student('STU-1')],
            [],
            $attendance,
            new AttendanceService($attendance, $events)
        );
        $count = $service->record('BAT-A', 'CLS-A', '2026-10-22', ['STU-1'], 'QR gagal membaca kamera.', 'USR-MASTER');

        $this->assertSame(1, $count);
        $this->assertSame('PRESENT', $created['Status']);
        $this->assertSame('MASTER_MANUAL', $created['Verification_Method']);
        $this->assertSame('QR gagal membaca kamera.', $created['Notes']);
        $this->assertSame('USR-MASTER', $created['Created_By']);
        $this->assertNotEmpty($created['Created_At']);
    }

    public function test_qr_record_created_after_roster_load_is_rejected_at_submit(): void
    {
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn(collect());
        $attendance->shouldReceive('fetchAll')->once()->andReturn(collect([$this->attendance('STU-1', 'PRESENT')]));
        $attendance->shouldNotReceive('create');
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatch');

        $service = $this->service(
            [],
            [$this->student('STU-1')],
            [],
            $attendance,
            new AttendanceService($attendance, $events)
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('data telah berubah');
        $service->record('BAT-A', 'CLS-A', '2026-10-22', ['STU-1'], 'QR gagal membaca kamera.', 'USR-MASTER');
    }

    public function test_database_unique_conflict_is_reported_without_second_attendance(): void
    {
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->times(3)->andReturn(collect());
        $attendance->shouldReceive('create')->once()->andThrow(new DuplicatePrimaryKeyException('duplicate'));
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatch');
        $service = $this->service(
            [],
            [$this->student('STU-1')],
            [],
            $attendance,
            new AttendanceService($attendance, $events)
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('berkonflik');
        $service->record('BAT-A', 'CLS-A', '2026-10-22', ['STU-1'], 'QR gagal membaca kamera.', 'USR-MASTER');
    }

    public function test_non_master_cannot_call_manual_endpoint(): void
    {
        $roles = Mockery::mock(RoleService::class);
        $roles->shouldReceive('getRoleById')->with('ROLE-ACADEMIC')->zeroOrMoreTimes()->andReturn([
            'Role_ID' => 'ROLE-ACADEMIC',
            'Role_Name' => 'ACADEMIC',
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roles);
        $this->actingAs(new GenericUser([
            'id' => 'USR-ACADEMIC',
            'User_ID' => 'USR-ACADEMIC',
            'Role_ID' => 'ROLE-ACADEMIC',
        ]));

        $this->get(route('attendances.manual.create'))->assertForbidden();
        $this->post(route('attendances.manual.store'), [])->assertForbidden();
    }

    public function test_master_can_open_manual_page_and_invalid_direct_status_is_rejected(): void
    {
        $this->actingAsRole('MASTER');
        $this->app->instance(
            MasterManualAttendanceService::class,
            $this->service([], [$this->student('STU-1')], [])
        );

        $this->get(route('attendances.manual.create'))
            ->assertOk()
            ->assertSee('Absensi Manual MASTER')
            ->assertSee('Presensi Harian / QR Kelas');

        $this->post(route('attendances.manual.store'), [
            'Batch_ID' => 'BAT-A',
            'Class_ID' => 'CLS-A',
            'Attendance_Date' => '2026-10-22',
            'Attendance_Type' => 'CLASS_QR',
            'Manual_Reason' => 'Mencoba bypass workflow.',
            'Student_IDs' => ['STU-1'],
            'Status' => 'SICK',
        ])->assertSessionHasErrors('Status');
    }

    public function test_master_roster_endpoint_filters_on_backend(): void
    {
        $this->actingAsRole('MASTER');
        $this->app->instance(
            MasterManualAttendanceService::class,
            $this->service(
                [$this->attendance('STU-1', 'PRESENT')],
                [$this->student('STU-1'), $this->student('STU-2')],
                []
            )
        );

        $this->getJson(route('attendances.manual.roster', [
            'batch_id' => 'BAT-A',
            'class_id' => 'CLS-A',
            'attendance_date' => '2026-10-22',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'eligible')
            ->assertJsonPath('eligible.0.Student_ID', 'STU-2')
            ->assertJsonCount(1, 'blocked')
            ->assertJsonPath('blocked.0.Student_ID', 'STU-1');
    }

    private function service(
        array $official,
        array $students,
        array $requests,
        ?AttendanceRepositoryInterface $attendance = null,
        ?AttendanceService $attendanceService = null
    ): MasterManualAttendanceService {
        $attendance ??= Mockery::mock(AttendanceRepositoryInterface::class);
        if ($attendanceService === null) {
            $attendance->shouldReceive('fetchAll')->andReturn(collect($official));
            $attendanceService = Mockery::mock(AttendanceService::class);
        }

        $requestRepository = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requestRepository->shouldReceive('fetchAll')->andReturn(collect($requests));
        $studentRepository = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepository->shouldReceive('fetchAll')->andReturn(collect($students));
        $classRepository = Mockery::mock(ClassRepositoryInterface::class);
        $classRepository->shouldReceive('fetchAll')->andReturn(collect([$this->classRow()]));
        $batchRepository = Mockery::mock(BatchRepositoryInterface::class);
        $batchRepository->shouldReceive('fetchAll')->andReturn(collect([$this->batch()]));

        return new MasterManualAttendanceService(
            $attendance,
            $requestRepository,
            $studentRepository,
            $classRepository,
            $batchRepository,
            $attendanceService
        );
    }

    private function student(string $id): array
    {
        return [
            'Student_ID' => $id,
            'Student_Number' => 'N-'.$id,
            'Full_Name' => 'Siswa '.$id,
            'Batch_ID' => 'BAT-A',
            'Class_ID' => 'CLS-A',
            'Enrollment_Status' => 'AKTIF',
            'Is_Active' => 'TRUE',
        ];
    }

    private function attendance(string $studentId, string $status): array
    {
        return [
            'Attendance_ID' => 'ATT-'.$studentId,
            'Student_ID' => $studentId,
            'Class_ID' => 'CLS-A',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-10-22',
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }

    private function permissionRequest(string $studentId, string $status): array
    {
        return [
            'Request_ID' => 'REQ-'.$studentId,
            'Student_ID' => $studentId,
            'Class_ID' => 'CLS-A',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-10-22',
            'Request_Type' => 'SAKIT',
            'Status' => $status,
        ];
    }

    private function batch(): array
    {
        return ['Batch_ID' => 'BAT-A', 'Batch_Name' => 'Batch A', 'Is_Active' => 'TRUE'];
    }

    private function classRow(): array
    {
        return ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'];
    }

    private function actingAsRole(string $role): void
    {
        $roleId = 'ROLE-'.$role;
        $roles = Mockery::mock(RoleService::class);
        $roles->shouldReceive('getRoleById')->with($roleId)->zeroOrMoreTimes()->andReturn([
            'Role_ID' => $roleId,
            'Role_Name' => $role,
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roles);
        $this->actingAs(new GenericUser([
            'id' => 'USR-'.$role,
            'User_ID' => 'USR-'.$role,
            'Role_ID' => $roleId,
            'Role' => $role,
        ]));
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
