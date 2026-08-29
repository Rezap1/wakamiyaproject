<?php

namespace Tests\Unit;

use App\Http\Controllers\Core\ApprovalController;
use App\Services\Core\ApprovalHistoryService;
use App\Services\Core\ApprovalService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApprovalControllerScopeTest extends TestCase
{
    public function test_director_cannot_approve_request_assigned_to_another_role(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-DIR',
            'User_ID' => 'USR-DIR',
            'Role' => 'DIRECTOR',
        ]));

        $approvalService = Mockery::mock(ApprovalService::class);
        $approvalService->shouldReceive('getById')->once()->with('APP-FINANCE')->andReturn([
            'Approval_ID' => 'APP-FINANCE',
            'Current_Approver' => 'FINANCE',
            'Status' => 'Waiting Approval',
        ]);
        $approvalService->shouldReceive('approve')->never();

        $controller = new ApprovalController(
            $approvalService,
            Mockery::mock(ApprovalHistoryService::class)
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('bukan approver aktif');

        $controller->approve(Request::create('/approvals/APP-FINANCE/approve', 'POST'), 'APP-FINANCE');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
