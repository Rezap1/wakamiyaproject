<?php
$dirConfig = 'config';
$dirInterface = 'app/Interfaces/GoogleSheets';
$dirRepo = 'app/Repositories/GoogleSheets';
$dirService = 'app/Services/Core';

// 1. Config
$wfConfig = <<<'EOT'
<?php
return [
    'status' => [
        'Draft',
        'Submitted',
        'Waiting Approval',
        'Approved',
        'Rejected',
        'Returned',
        'Cancelled',
        'Completed'
    ],
    'actions' => [
        'Submit',
        'Approve',
        'Reject',
        'Return',
        'Cancel'
    ]
];
EOT;
file_put_contents("$dirConfig/workflow.php", $wfConfig);

// 2. Interfaces
$wfInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface WorkflowRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents("$dirInterface/WorkflowRepositoryInterface.php", $wfInterface);

$apInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface ApprovalRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents("$dirInterface/ApprovalRepositoryInterface.php", $apInterface);

$aphInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface ApprovalHistoryRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
}
EOT;
file_put_contents("$dirInterface/ApprovalHistoryRepositoryInterface.php", $aphInterface);

// 3. Repositories
$wfRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\WorkflowRepositoryInterface;

class WorkflowRepository extends BaseSheetRepository implements WorkflowRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_WORKFLOW';
        $this->cacheKey = 'workflow_sheet';
        $this->primaryKey = 'Workflow_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Inactive']); }
}
EOT;
file_put_contents("$dirRepo/WorkflowRepository.php", $wfRepo);

$apRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;

class ApprovalRepository extends BaseSheetRepository implements ApprovalRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_APPROVAL';
        $this->cacheKey = 'approval_sheet';
        $this->primaryKey = 'Approval_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Cancelled']); }
}
EOT;
file_put_contents("$dirRepo/ApprovalRepository.php", $apRepo);

$aphRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface;

class ApprovalHistoryRepository extends BaseSheetRepository implements ApprovalHistoryRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_APPROVAL_HISTORY';
        $this->cacheKey = 'approval_history_sheet';
        $this->primaryKey = 'History_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
}
EOT;
file_put_contents("$dirRepo/ApprovalHistoryRepository.php", $aphRepo);

// 4. Services
$wfService = <<<'EOT'
<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\WorkflowRepositoryInterface;

class WorkflowService
{
    protected $repo;

    public function __construct(WorkflowRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getAll() { return $this->repo->getAll(); }
    public function getById($id) { return $this->repo->getById($id); }

    public function activeWorkflow($module) {
        return $this->getAll()->where('Module', $module)->where('Status', 'Active')->first();
    }

    public function createWorkflow(array $data) {
        $data['Workflow_ID'] = uniqid('WF_');
        $data['Created_At'] = now()->toDateTimeString();
        $res = $this->repo->create($data);
        $this->repo->clearCache();
        return $res;
    }
}
EOT;
file_put_contents("$dirService/WorkflowService.php", $wfService);

$aphService = <<<'EOT'
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
EOT;
file_put_contents("$dirService/ApprovalHistoryService.php", $aphService);

$apService = <<<'EOT'
<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;
use App\Services\Core\WorkflowService;
use App\Services\Core\ApprovalHistoryService;
use App\Services\Core\NotificationService;

class ApprovalService
{
    protected $repo;
    protected $workflowService;
    protected $historyService;
    protected $notifService;

    public function __construct(
        ApprovalRepositoryInterface $repo,
        WorkflowService $workflowService,
        ApprovalHistoryService $historyService,
        NotificationService $notifService
    ) {
        $this->repo = $repo;
        $this->workflowService = $workflowService;
        $this->historyService = $historyService;
        $this->notifService = $notifService;
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
        
        // Notify Approver
        try {
            $this->notifService->NotifyRole($next, 'Approval Required', "New $module requires your approval ($referenceId).", 'Information', 'High', "/approvals/{$data['Approval_ID']}");
        } catch (\Exception $e) {}

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

        // Notify Requester
        try {
            $this->notifService->NotifyUser($app['Requester_ID'], 'Request Approved', "Your request ({$app['Reference_ID']}) has been approved.", 'Success', 'Normal');
        } catch (\Exception $e) {}

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

        try {
            $this->notifService->NotifyUser($app['Requester_ID'], 'Request Rejected', "Your request ({$app['Reference_ID']}) has been rejected. Remarks: $remarks", 'Danger', 'High');
        } catch (\Exception $e) {}

        return $res;
    }

    public function return($id) {}
    public function cancel($id) {}
    public function complete($id) {}
}
EOT;
file_put_contents("$dirService/ApprovalService.php", $apService);

echo "Workflow Backend Created.\n";
?>
