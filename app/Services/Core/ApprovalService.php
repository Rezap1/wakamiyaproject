<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;
use App\Services\Core\WorkflowService;
use App\Services\Core\ApprovalHistoryService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Auth;

class ApprovalService
{
    protected $repo;
    protected $workflowService;
    protected $historyService;
    protected $enterpriseEvent;

    public function __construct(
        ApprovalRepositoryInterface $repo,
        WorkflowService $workflowService,
        ApprovalHistoryService $historyService,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->repo = $repo;
        $this->workflowService = $workflowService;
        $this->historyService = $historyService;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAll() { return $this->repo->getAll(); }
    public function getById($id) { return $this->repo->getById($id); }

    public function currentApprover($id) {
        $app = $this->getById($id);
        return $app['Current_Approver'] ?? null;
    }

    public function nextApprover($module, $currentStep) {
        // Logic to determine next approver based on workflow config.
        // For phase 9.7, simplified:
        if ($module == 'Payroll') return 'DIRECTOR';
        if ($module == 'Billing') return 'FINANCE';
        if ($module == 'Document') return 'ADMINISTRATOR';
        return 'ADMINISTRATOR';
    }

    public function submit($module, $referenceType, $referenceId, $requesterEmail, $priority = 'Normal') {
        $workflow = $this->workflowService->activeWorkflow($module);
        $wfId = $workflow ? $workflow['Workflow_ID'] : 'DEFAULT';
        
        $next = $this->nextApprover($module, 0);

        $data = [
            'Approval_ID' => uniqid('APP_'),
            'Workflow_ID' => $wfId,
            'Reference_ID' => $referenceId,
            'Reference_Type' => $referenceType,
            'Requester_ID' => $requesterEmail,
            'Current_Approver' => $next,
            'Status' => 'Waiting Approval',
            'Priority' => $priority,
            'Submitted_At' => now()->toDateTimeString()
        ];

        $res = $this->repo->create($data);
        $this->repo->clearCache();

        $this->historyService->createHistory($data['Approval_ID'], $wfId, 'Submit', 'Draft', 'Waiting Approval', 'Submitted for approval.', $requesterEmail);
        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Submit_Approval', $referenceType, $referenceId, null, $data['Approval_ID']); } catch(\Exception $e) {}
        
        $this->enterpriseEvent->dispatch(
            strtoupper($module),
            'SUBMIT',
            $referenceType,
            $referenceId,
            Auth::id() ?? $requesterEmail,
            [$next],
            [],
            ['approval_id' => $data['Approval_ID']]
        );

        return $res;
    }

    public function approve($id, $userEmail, $remarks = '') {
        $app = $this->getById($id);
        if(!$app) throw new \Exception("Approval not found");

        $oldStatus = $app['Status'];
        // Assume single step approval for Phase 9.7 demo
        $app['Status'] = 'Approved';
        $app['Approved_At'] = now()->toDateTimeString();
        $app['Current_Approver'] = ''; // Completed

        $res = $this->repo->update($id, $app);
        $this->repo->clearCache();

        $this->historyService->createHistory($id, $app['Workflow_ID'], 'Approve', $oldStatus, 'Approved', $remarks, $userEmail);
        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Approve_Request', $app['Reference_Type'] ?? 'Unknown', $app['Reference_ID'] ?? 'Unknown', $oldStatus, 'Approved'); } catch(\Exception $e) {}

        $this->enterpriseEvent->dispatch(
            'SYSTEM',
            'APPROVE',
            $app['Reference_Type'] ?? 'Unknown',
            $app['Reference_ID'] ?? 'Unknown',
            Auth::id() ?? $userEmail,
            [],
            [$app['Requester_ID']],
            ['remarks' => $remarks]
        );

        return $res;
    }

    public function reject($id, $userEmail, $remarks = '') {
        $app = $this->getById($id);
        if(!$app) throw new \Exception("Approval not found");

        $oldStatus = $app['Status'];
        $app['Status'] = 'Rejected';
        $app['Rejected_At'] = now()->toDateTimeString();

        $res = $this->repo->update($id, $app);
        $this->repo->clearCache();

        $this->historyService->createHistory($id, $app['Workflow_ID'], 'Reject', $oldStatus, 'Rejected', $remarks, $userEmail);
        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Reject_Request', $app['Reference_Type'] ?? 'Unknown', $app['Reference_ID'] ?? 'Unknown', $oldStatus, 'Rejected'); } catch(\Exception $e) {}

        $this->enterpriseEvent->dispatch(
            'SYSTEM',
            'REJECT',
            $app['Reference_Type'] ?? 'Unknown',
            $app['Reference_ID'] ?? 'Unknown',
            Auth::id() ?? $userEmail,
            [],
            [$app['Requester_ID']],
            ['remarks' => $remarks]
        );

        return $res;
    }

    public function return($id) {}
    public function cancel($id) {}
    public function complete($id) {}
}