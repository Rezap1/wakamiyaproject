<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;
use App\Services\Core\ApprovalHistoryService;
use App\Services\Core\ApprovalService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\WorkflowService;
use Exception;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class ApprovalServiceIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser([
            'id' => 'USR-DIRECTOR',
            'User_ID' => 'USR-DIRECTOR',
            'Role' => 'DIRECTOR',
        ]));
    }

    public function test_submit_fails_closed_when_active_workflow_is_missing(): void
    {
        $repository = Mockery::mock(ApprovalRepositoryInterface::class);
        $repository->shouldNotReceive('create');

        $workflow = Mockery::mock(WorkflowService::class);
        $workflow->shouldReceive('activeWorkflow')->once()->with('Payroll')->andReturn(null);

        $service = $this->makeService($repository, $workflow);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Workflow aktif');

        $service->submit('Payroll', 'Payroll_Record', 'PRL-001', 'SPOOFED-ACTOR');
    }

    public function test_submit_records_authenticated_actor_instead_of_spoofed_parameter(): void
    {
        $repository = Mockery::mock(ApprovalRepositoryInterface::class);
        $repository->shouldReceive('create')->once()->with(Mockery::on(
            fn ($row) => ($row['Requester_ID'] ?? '') === 'USR-DIRECTOR'
                && ($row['Workflow_ID'] ?? '') === 'WF-001'
        ))->andReturn(true);
        $repository->shouldReceive('clearCache')->once();

        $workflow = Mockery::mock(WorkflowService::class);
        $workflow->shouldReceive('activeWorkflow')->once()->with('Payroll')->andReturn([
            'Workflow_ID' => 'WF-001',
        ]);

        $history = Mockery::mock(ApprovalHistoryService::class);
        $history->shouldReceive('createHistory')->once()->with(
            Mockery::type('string'),
            'WF-001',
            'Submit',
            'Draft',
            'Waiting Approval',
            'Submitted for approval.',
            'USR-DIRECTOR'
        );

        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldReceive('dispatch')->once()->withArgs(
            fn ($module, $action, $referenceType, $referenceId, $actor) =>
                $module === 'PAYROLL'
                && $action === 'SUBMIT'
                && $referenceType === 'Payroll_Record'
                && $referenceId === 'PRL-001'
                && $actor === 'USR-DIRECTOR'
        );

        $service = new ApprovalService($repository, $workflow, $history, $event);

        $this->assertTrue($service->submit('Payroll', 'Payroll_Record', 'PRL-001', 'SPOOFED-ACTOR'));
    }

    public function test_completed_approval_cannot_be_approved_again(): void
    {
        $repository = Mockery::mock(ApprovalRepositoryInterface::class);
        $repository->shouldReceive('getById')->once()->with('APP-001')->andReturn([
            'Approval_ID' => 'APP-001',
            'Status' => 'Approved',
        ]);
        $repository->shouldNotReceive('update');

        $service = $this->makeService($repository);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('tidak dapat disetujui');

        $service->approve('APP-001', 'SPOOFED-ACTOR');
    }

    public function test_approval_persistence_failure_emits_no_history_or_success_event(): void
    {
        $repository = Mockery::mock(ApprovalRepositoryInterface::class);
        $repository->shouldReceive('getById')->once()->with('APP-001')->andReturn([
            'Approval_ID' => 'APP-001',
            'Workflow_ID' => 'WF-001',
            'Reference_Type' => 'Payroll_Record',
            'Reference_ID' => 'PRL-001',
            'Requester_ID' => 'USR-HR',
            'Status' => 'Waiting Approval',
        ]);
        $repository->shouldReceive('update')->once()->with('APP-001', Mockery::on(
            fn ($row) => ($row['Approved_By'] ?? '') === 'USR-DIRECTOR'
        ))->andReturn(false);
        $repository->shouldNotReceive('clearCache');

        $history = Mockery::mock(ApprovalHistoryService::class);
        $history->shouldNotReceive('createHistory');
        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldNotReceive('dispatch');

        $service = new ApprovalService(
            $repository,
            Mockery::mock(WorkflowService::class),
            $history,
            $event
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Gagal menyimpan persetujuan');

        $service->approve('APP-001', 'SPOOFED-ACTOR');
    }

    private function makeService(
        ApprovalRepositoryInterface $repository,
        ?WorkflowService $workflow = null
    ): ApprovalService {
        return new ApprovalService(
            $repository,
            $workflow ?? Mockery::mock(WorkflowService::class),
            Mockery::mock(ApprovalHistoryService::class),
            Mockery::mock(EnterpriseEventService::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
