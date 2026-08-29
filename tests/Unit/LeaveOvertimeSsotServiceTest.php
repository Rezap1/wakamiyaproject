<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\LeaveRepositoryInterface;
use App\Interfaces\GoogleSheets\OvertimeRepositoryInterface;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use App\Services\HR\LeaveService;
use App\Services\HR\OvertimeService;
use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LeaveOvertimeSsotServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-08-24 09:00:00');
    }

    public function test_leave_create_and_read_survive_cache_flush(): void
    {
        $repository = new InMemoryLeaveRepository();
        $service = $this->leaveServiceForCreate($repository);

        $record = $service->createLeaveRequest([
            'Leave_Type' => 'CUTI_TAHUNAN',
            'Start_Date' => '2026-08-25',
            'End_Date' => '2026-08-26',
            'Reason' => 'Keperluan keluarga',
        ]);

        $this->assertSame($record['Leave_ID'], $repository->getById($record['Leave_ID'])['Leave_ID']);
        Cache::flush();
        $this->assertSame($record['Leave_ID'], $service->getLeaveById($record['Leave_ID'])['Leave_ID']);
        $this->assertSame('EMP-001', $record['Employee_ID']);
    }

    public function test_overtime_create_and_read_survive_cache_flush(): void
    {
        config(['finance.overtime_rate_per_hour' => 25000]);
        $repository = new InMemoryOvertimeRepository();
        $service = $this->overtimeServiceForCreate($repository);

        $record = $service->createOvertimeRequest([
            'Date' => '2026-08-24',
            'Start_Time' => '18:00',
            'End_Time' => '20:30',
            'Reason' => 'Tutup buku',
        ]);

        $this->assertSame(2.5, $record['Duration_Hours']);
        $this->assertSame(62500.0, $record['Overtime_Pay']);
        Cache::flush();
        $this->assertSame($record['Overtime_ID'], $service->getOvertimeById($record['Overtime_ID'])['Overtime_ID']);
    }

    public function test_persistence_failure_is_fail_closed_and_does_not_dispatch_success_event(): void
    {
        $repository = Mockery::mock(LeaveRepositoryInterface::class);
        $repository->shouldReceive('getAll')->once()->andReturn(collect());
        $repository->shouldReceive('create')->once()->andThrow(new RuntimeException('SSOT unavailable'));
        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldReceive('dispatch')->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSOT unavailable');
        $this->leaveServiceForCreate($repository, $event)->createLeaveRequest([
            'Leave_Type' => 'SAKIT',
            'Start_Date' => '2026-08-25',
            'End_Date' => '2026-08-25',
            'Reason' => 'Sakit',
        ]);
    }

    public function test_invalid_actor_fails_before_any_ssot_mutation(): void
    {
        auth()->logout();
        $repository = Mockery::mock(OvertimeRepositoryInterface::class);
        $repository->shouldReceive('create')->never();

        $this->expectExceptionMessage('Akses Ditolak');
        $service = new OvertimeService(
            $repository,
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EmployeeService::class),
            Mockery::mock(EnterpriseEventService::class)
        );
        $service->createOvertimeRequest([
            'Date' => '2026-08-24', 'Start_Time' => '18:00', 'End_Time' => '19:00', 'Reason' => 'Test',
        ]);
    }

    public function test_overlapping_leave_is_rejected_before_ssot_mutation(): void
    {
        $repository = new InMemoryLeaveRepository([
            [
                'Leave_ID' => 'LEV-EXISTING',
                'Employee_ID' => 'EMP-001',
                'Start_Date' => '2026-08-25',
                'End_Date' => '2026-08-27',
                'Status' => 'SUBMITTED',
            ],
        ]);

        $this->expectExceptionMessage('bentrok');
        $this->leaveServiceForCreate($repository, null, false)->createLeaveRequest([
            'Leave_Type' => 'CUTI_TAHUNAN',
            'Start_Date' => '2026-08-26',
            'End_Date' => '2026-08-28',
            'Reason' => 'Bentrok',
        ]);
    }

    public function test_duplicate_overtime_slot_is_rejected_before_ssot_mutation(): void
    {
        $repository = new InMemoryOvertimeRepository([
            [
                'Overtime_ID' => 'OVT-EXISTING',
                'Employee_ID' => 'EMP-001',
                'Date' => '2026-08-24',
                'Start_Time' => '18:00',
                'Status' => 'SUBMITTED',
            ],
        ]);

        $this->expectExceptionMessage('sudah memiliki pengajuan lembur');
        $this->overtimeServiceForCreate($repository, false)->createOvertimeRequest([
            'Date' => '2026-08-24',
            'Start_Time' => '18:00',
            'End_Time' => '20:00',
            'Reason' => 'Duplikat',
        ]);
    }

    public function test_submitted_overtime_is_not_counted_by_payroll(): void
    {
        $repository = new InMemoryOvertimeRepository([
            ['Overtime_ID' => 'OVT-1', 'Employee_ID' => 'EMP-001', 'Date' => '2026-08-02', 'Status' => 'SUBMITTED', 'Overtime_Pay' => 100000],
            ['Overtime_ID' => 'OVT-2', 'Employee_ID' => 'EMP-001', 'Date' => '2026-08-03', 'Status' => 'APPROVED', 'Overtime_Pay' => 75000],
            ['Overtime_ID' => 'OVT-3', 'Employee_ID' => 'EMP-001', 'Date' => '2026-08-04', 'Status' => 'INCLUDED_IN_PAYROLL', 'Overtime_Pay' => 25000],
            ['Overtime_ID' => 'OVT-4', 'Employee_ID' => 'EMP-002', 'Date' => '2026-08-04', 'Status' => 'APPROVED', 'Overtime_Pay' => 90000],
        ]);
        $service = new OvertimeService(
            $repository,
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EmployeeService::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $this->assertSame(100000.0, $service->getApprovedOvertimePayForPeriod('EMP-001', '2026-08'));
    }

    public function test_cross_month_leave_counts_only_days_inside_payroll_period(): void
    {
        $repository = new InMemoryLeaveRepository([
            [
                'Leave_ID' => 'LEV-CROSS', 'Employee_ID' => 'EMP-001', 'Status' => 'APPROVED',
                'Start_Date' => '2026-07-30', 'End_Date' => '2026-08-02', 'Duration_Days' => 4,
            ],
        ]);
        $service = new LeaveService(
            $repository,
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EmployeeService::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $this->assertSame(2, $service->getApprovedLeavesForPeriod('EMP-001', '2026-07'));
        $this->assertSame(2, $service->getApprovedLeavesForPeriod('EMP-001', '2026-08'));
    }

    public function test_employee_cannot_read_another_employees_leave_or_overtime(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A', 'Role' => 'EMPLOYEE']));
        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->twice()->andReturn([
            ['Employee_ID' => 'EMP-A', 'User_ID' => 'USR-A'],
        ]);

        $leave = new LeaveService(
            new InMemoryLeaveRepository([['Leave_ID' => 'LEV-B', 'Employee_ID' => 'EMP-B']]),
            $employeeRepo,
            Mockery::mock(EmployeeService::class),
            Mockery::mock(EnterpriseEventService::class)
        );
        $overtime = new OvertimeService(
            new InMemoryOvertimeRepository([['Overtime_ID' => 'OVT-B', 'Employee_ID' => 'EMP-B']]),
            $employeeRepo,
            Mockery::mock(EmployeeService::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        try {
            $leave->getLeaveDocumentData('LEV-B');
            $this->fail('Leave milik employee lain seharusnya ditolak.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('bukan milik akun Anda', $e->getMessage());
        }

        $this->expectExceptionMessage('bukan milik akun Anda');
        $overtime->getOvertimeDocumentData('OVT-B');
    }

    private function leaveServiceForCreate(
        LeaveRepositoryInterface $repository,
        ?EnterpriseEventService $event = null,
        bool $expectsDispatch = true
    ): LeaveService {
        $this->actingAs(new GenericUser(['id' => 'USR-001', 'User_ID' => 'USR-001', 'Role' => 'EMPLOYEE']));
        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn([
            ['Employee_ID' => 'EMP-001', 'User_ID' => 'USR-001', 'Full_Name' => 'Pegawai Satu'],
        ]);
        $employeeService = Mockery::mock(EmployeeService::class);
        $employeeService->shouldReceive('isEmployeeActive')->once()->with('EMP-001')->andReturnTrue();
        if ($event === null) {
            $event = Mockery::mock(EnterpriseEventService::class);
            $expectsDispatch
                ? $event->shouldReceive('dispatch')->once()
                : $event->shouldReceive('dispatch')->never();
        }

        return new LeaveService($repository, $employeeRepo, $employeeService, $event);
    }

    private function overtimeServiceForCreate(
        OvertimeRepositoryInterface $repository,
        bool $expectsDispatch = true
    ): OvertimeService
    {
        $this->actingAs(new GenericUser(['id' => 'USR-001', 'User_ID' => 'USR-001', 'Role' => 'EMPLOYEE']));
        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn([
            ['Employee_ID' => 'EMP-001', 'User_ID' => 'USR-001', 'Full_Name' => 'Pegawai Satu'],
        ]);
        $employeeService = Mockery::mock(EmployeeService::class);
        $employeeService->shouldReceive('isEmployeeActive')->once()->with('EMP-001')->andReturnTrue();
        $event = Mockery::mock(EnterpriseEventService::class);
        $expectsDispatch
            ? $event->shouldReceive('dispatch')->once()
            : $event->shouldReceive('dispatch')->never();

        return new OvertimeService($repository, $employeeRepo, $employeeService, $event);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}

class InMemoryLeaveRepository implements LeaveRepositoryInterface
{
    public function __construct(public array $rows = []) {}
    public function fetchAll() { return collect(array_values($this->rows)); }
    public function getAll() { return $this->fetchAll(); }
    public function getById(string $id): ?array { return collect($this->rows)->firstWhere('Leave_ID', $id); }
    public function findByEmployee(string $employeeId) { return $this->fetchAll()->where('Employee_ID', $employeeId)->values(); }
    public function create(array $data) { $this->rows[$data['Leave_ID']] = $data; return true; }
    public function update($id, array $data) { $this->rows[$id] = array_merge($this->rows[$id], $data); return true; }
    public function delete($id) { unset($this->rows[$id]); return true; }
    public function hardDelete($id) { return $this->delete($id); }
    public function clearCache() {}
}

class InMemoryOvertimeRepository implements OvertimeRepositoryInterface
{
    public function __construct(public array $rows = []) {}
    public function fetchAll() { return collect(array_values($this->rows)); }
    public function getAll() { return $this->fetchAll(); }
    public function getById(string $id): ?array { return collect($this->rows)->firstWhere('Overtime_ID', $id); }
    public function findByEmployee(string $employeeId) { return $this->fetchAll()->where('Employee_ID', $employeeId)->values(); }
    public function create(array $data) { $this->rows[$data['Overtime_ID']] = $data; return true; }
    public function update($id, array $data) { $this->rows[$id] = array_merge($this->rows[$id], $data); return true; }
    public function delete($id) { unset($this->rows[$id]); return true; }
    public function hardDelete($id) { return $this->delete($id); }
    public function clearCache() {}
}
