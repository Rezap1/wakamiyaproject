<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface;

class ApprovalHistoryService
{
    protected $repo;

    public function __construct(ApprovalHistoryRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function history($approvalId) {
        return $this->repo->getAll()->where('Approval_ID', $approvalId)->sortByDesc('Created_At');
    }

    public function createHistory($approvalId, $workflowId, $action, $oldStatus, $newStatus, $remarks, $userEmail) {
        $data = [
            'History_ID' => uniqid('HIS_'),
            'Approval_ID' => $approvalId,
            'Workflow_ID' => $workflowId,
            'Action' => $action,
            'Old_Status' => $oldStatus,
            'New_Status' => $newStatus,
            'Performed_By' => $userEmail,
            'Remarks' => $remarks,
            'Created_At' => now()->toDateTimeString()
        ];
        $res = $this->repo->create($data);
        $this->repo->clearCache();
        return $res;
    }
}