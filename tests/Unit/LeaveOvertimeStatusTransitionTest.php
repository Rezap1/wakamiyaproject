<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\LeaveRepositoryInterface;
use App\Interfaces\GoogleSheets\OvertimeRepositoryInterface;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use App\Services\HR\LeaveService;
use App\Services\HR\OvertimeService;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class LeaveOvertimeStatusTransitionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-HR', 'User_ID' => 'USR-HR', 'Role' => 'HR']));
        Cache::flush();
    }

    public function test_approved_leave_cannot_be_rejected_again(): void
    {
        $repo = Mockery::mock(LeaveRepositoryInterface::class);
        $repo->shouldReceive('getById')->once()->with('LEV-APPROVED')->andReturn([
            'Leave_ID' => 'LEV-APPROVED', 'Employee_ID' => 'EMP-001', 'Status' => 'APPROVED',
        ]);
        $repo->shouldReceive('update')->never();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('tidak dapat ditolak');
        $this->leaveService($repo)->rejectLeave('LEV-APPROVED', 'USR-HR', 'Tidak valid');
    }

    public function test_rejected_overtime_cannot_be_approved_again(): void
    {
        $repo = Mockery::mock(OvertimeRepositoryInterface::class);
        $repo->shouldReceive('getById')->once()->with('OVT-REJECTED')->andReturn([
            'Overtime_ID' => 'OVT-REJECTED', 'Employee_ID' => 'EMP-001', 'Status' => 'REJECTED',
        ]);
        $repo->shouldReceive('update')->never();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('tidak dapat disetujui');
        $this->overtimeService($repo)->approveOvertime('OVT-REJECTED', 'USR-HR');
    }

    public function test_leave_approval_ignores_spoofed_actor_and_persists_to_repository(): void
    {
        $repo = Mockery::mock(LeaveRepositoryInterface::class);
        $repo->shouldReceive('getById')->once()->andReturn([
            'Leave_ID' => 'LEV-SUBMITTED', 'Employee_ID' => 'EMP-001', 'Status' => 'SUBMITTED',
        ]);
        $repo->shouldReceive('update')->once()->with('LEV-SUBMITTED', Mockery::on(
            fn (array $changes) => $changes['Status'] === 'APPROVED'
                && $changes['Approved_By'] === 'USR-HR'
                && !empty($changes['Updated_At'])
        ))->andReturnTrue();

        $result = $this->leaveService($repo, $this->eventExpectingActor('USR-HR', 'Approved_By'))
            ->approveLeave('LEV-SUBMITTED', 'SPOOFED-ACTOR');

        $this->assertSame('USR-HR', $result['Approved_By']);
    }

    public function test_overtime_approval_ignores_spoofed_actor_and_persists_to_repository(): void
    {
        $repo = Mockery::mock(OvertimeRepositoryInterface::class);
        $repo->shouldReceive('getById')->once()->andReturn([
            'Overtime_ID' => 'OVT-SUBMITTED', 'Employee_ID' => 'EMP-001', 'Status' => 'SUBMITTED',
        ]);
        $repo->shouldReceive('update')->once()->with('OVT-SUBMITTED', Mockery::on(
            fn (array $changes) => $changes['Status'] === 'APPROVED'
                && $changes['Approved_By'] === 'USR-HR'
                && !empty($changes['Updated_At'])
        ))->andReturnTrue();

        $result = $this->overtimeService($repo, $this->eventExpectingActor('USR-HR', 'Approved_By'))
            ->approveOvertime('OVT-SUBMITTED', 'SPOOFED-ACTOR');

        $this->assertSame('USR-HR', $result['Approved_By']);
    }

    public function test_leave_rejection_and_cancellation_are_repository_updates(): void
    {
        $rejectRepo = Mockery::mock(LeaveRepositoryInterface::class);
        $rejectRepo->shouldReceive('getById')->once()->andReturn([
            'Leave_ID' => 'LEV-REJECT', 'Employee_ID' => 'EMP-001', 'Status' => 'SUBMITTED',
        ]);
        $rejectRepo->shouldReceive('update')->once()->with('LEV-REJECT', Mockery::on(
            fn (array $changes) => $changes['Status'] === 'REJECTED'
                && $changes['Rejected_By'] === 'USR-HR'
                && $changes['Rejection_Reason'] === 'Ditolak'
        ))->andReturnTrue();
        $rejected = $this->leaveService($rejectRepo, $this->event())->rejectLeave('LEV-REJECT', 'SPOOFED', 'Ditolak');

        $cancelRepo = Mockery::mock(LeaveRepositoryInterface::class);
        $cancelRepo->shouldReceive('getById')->once()->andReturn([
            'Leave_ID' => 'LEV-CANCEL', 'Employee_ID' => 'EMP-001', 'Status' => 'SUBMITTED',
        ]);
        $cancelRepo->shouldReceive('update')->once()->with('LEV-CANCEL', Mockery::on(
            fn (array $changes) => $changes['Status'] === 'CANCELLED'
                && $changes['Cancelled_By'] === 'USR-HR'
        ))->andReturnTrue();
        $cancelled = $this->leaveService($cancelRepo, $this->event())->cancelLeave('LEV-CANCEL', 'SPOOFED');

        $this->assertSame('REJECTED', $rejected['Status']);
        $this->assertSame('CANCELLED', $cancelled['Status']);
    }

    public function test_overtime_rejection_is_a_repository_update(): void
    {
        $repo = Mockery::mock(OvertimeRepositoryInterface::class);
        $repo->shouldReceive('getById')->once()->andReturn([
            'Overtime_ID' => 'OVT-REJECT', 'Employee_ID' => 'EMP-001', 'Status' => 'SUBMITTED',
        ]);
        $repo->shouldReceive('update')->once()->with('OVT-REJECT', Mockery::on(
            fn (array $changes) => $changes['Status'] === 'REJECTED'
                && $changes['Rejected_By'] === 'USR-HR'
        ))->andReturnTrue();

        $result = $this->overtimeService($repo, $this->event())->rejectOvertime('OVT-REJECT', 'SPOOFED', 'Ditolak');

        $this->assertSame('REJECTED', $result['Status']);
    }

    private function leaveService(LeaveRepositoryInterface $repo, ?EnterpriseEventService $event = null): LeaveService
    {
        return new LeaveService(
            $repo,
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EmployeeService::class),
            $event ?? Mockery::mock(EnterpriseEventService::class)
        );
    }

    private function overtimeService(OvertimeRepositoryInterface $repo, ?EnterpriseEventService $event = null): OvertimeService
    {
        return new OvertimeService(
            $repo,
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EmployeeService::class),
            $event ?? Mockery::mock(EnterpriseEventService::class)
        );
    }

    private function event(): EnterpriseEventService
    {
        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldReceive('dispatch')->once();
        return $event;
    }

    private function eventExpectingActor(string $actor, string $payloadKey): EnterpriseEventService
    {
        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldReceive('dispatch')->once()->withArgs(
            fn ($module, $action, $type, $id, $actualActor, $roles, $users, $payload) =>
                $actualActor === $actor && ($payload[$payloadKey] ?? '') === $actor
        );
        return $event;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
