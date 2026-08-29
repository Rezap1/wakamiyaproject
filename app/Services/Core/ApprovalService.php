<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;
use App\Services\Core\WorkflowService;
use App\Services\Core\ApprovalHistoryService;
use App\Services\Core\EnterpriseEventService;
use App\Support\ActorIdentity;

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
        if (!$workflow || empty($workflow['Workflow_ID'])) {
            throw new \Exception("Workflow aktif untuk modul {$module} tidak ditemukan.");
        }

        $actorId = ActorIdentity::required();
        $wfId = $workflow['Workflow_ID'];
        
        $next = $this->nextApprover($module, 0);

        $data = [
            'Approval_ID' => uniqid('APP_'),
            'Workflow_ID' => $wfId,
            'Reference_ID' => $referenceId,
            'Reference_Type' => $referenceType,
            'Requester_ID' => $actorId,
            'Current_Approver' => $next,
            'Status' => 'Waiting Approval',
            'Priority' => $priority,
            'Submitted_At' => now()->toDateTimeString()
        ];

        $res = $this->repo->create($data);
        if (!$res) {
            throw new \Exception('Gagal menyimpan permintaan approval.');
        }
        $this->repo->clearCache();

        $this->historyService->createHistory($data['Approval_ID'], $wfId, 'Submit', 'Draft', 'Waiting Approval', 'Submitted for approval.', $actorId);
        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Submit_Approval', $referenceType, $referenceId, null, $data['Approval_ID']); } catch(\Exception $e) {}
        
        $this->enterpriseEvent->dispatch(
            strtoupper($module),
            'SUBMIT',
            $referenceType,
            $referenceId,
            $actorId,
            [$next],
            [],
            ['approval_id' => $data['Approval_ID']]
        );

        return $res;
    }

    public function approve($id, $userEmail, $remarks = '') {
        $app = $this->getById($id);
        if(!$app) throw new \Exception("Approval not found");

        $oldStatus = trim((string) ($app['Status'] ?? ''));
        if ($oldStatus !== 'Waiting Approval') {
            throw new \Exception("Status approval saat ini ({$oldStatus}) tidak dapat disetujui.");
        }

        $actorId = ActorIdentity::required();
        // Assume single step approval for Phase 9.7 demo
        $app['Status'] = 'Approved';
        $app['Approved_By'] = $actorId;
        $app['Approved_At'] = now()->toDateTimeString();
        $app['Current_Approver'] = ''; // Completed

        $res = $this->repo->update($id, $app);
        if (!$res) {
            throw new \Exception("Gagal menyimpan persetujuan #{$id}.");
        }
        $this->repo->clearCache();

        $this->historyService->createHistory($id, $app['Workflow_ID'], 'Approve', $oldStatus, 'Approved', $remarks, $actorId);
        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Approve_Request', $app['Reference_Type'] ?? 'Unknown', $app['Reference_ID'] ?? 'Unknown', $oldStatus, 'Approved'); } catch(\Exception $e) {}

        $this->enterpriseEvent->dispatch(
            'SYSTEM',
            'APPROVE',
            $app['Reference_Type'] ?? 'Unknown',
            $app['Reference_ID'] ?? 'Unknown',
            $actorId,
            [],
            [$app['Requester_ID']],
            ['remarks' => $remarks]
        );

        return $res;
    }

    public function reject($id, $userEmail, $remarks = '') {
        $app = $this->getById($id);
        if(!$app) throw new \Exception("Approval not found");

        $oldStatus = trim((string) ($app['Status'] ?? ''));
        if ($oldStatus !== 'Waiting Approval') {
            throw new \Exception("Status approval saat ini ({$oldStatus}) tidak dapat ditolak.");
        }

        $actorId = ActorIdentity::required();
        $app['Status'] = 'Rejected';
        $app['Rejected_By'] = $actorId;
        $app['Rejected_At'] = now()->toDateTimeString();
        $app['Current_Approver'] = '';

        $res = $this->repo->update($id, $app);
        if (!$res) {
            throw new \Exception("Gagal menyimpan penolakan #{$id}.");
        }
        $this->repo->clearCache();

        $this->historyService->createHistory($id, $app['Workflow_ID'], 'Reject', $oldStatus, 'Rejected', $remarks, $actorId);
        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Reject_Request', $app['Reference_Type'] ?? 'Unknown', $app['Reference_ID'] ?? 'Unknown', $oldStatus, 'Rejected'); } catch(\Exception $e) {}

        $this->enterpriseEvent->dispatch(
            'SYSTEM',
            'REJECT',
            $app['Reference_Type'] ?? 'Unknown',
            $app['Reference_ID'] ?? 'Unknown',
            $actorId,
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
