<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\ApprovalService;
use App\Services\Core\ApprovalHistoryService;
use Illuminate\Support\Facades\Auth;
use App\Services\Core\RoleService;

class ApprovalController extends Controller
{
    protected $approvalService;
    protected $historyService;

    public function __construct(ApprovalService $approvalService, ApprovalHistoryService $historyService)
    {
        $this->approvalService = $approvalService;
        $this->historyService = $historyService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $userRole = $this->currentRoleName($user);
        
        $approvals = $this->approvalService->getAll()
            ->filter(function($a) use ($userRole) {
                if ($userRole === 'ADMINISTRATOR') {
                    return ($a['Status'] ?? '') === 'Waiting Approval';
                }

                return strtoupper(trim($a['Current_Approver'] ?? '')) === $userRole
                    && ($a['Status'] ?? '') === 'Waiting Approval';
            })->sortByDesc('Submitted_At');

        return view('workflow.index', compact('approvals'));
    }

    public function show($id)
    {
        $approval = $this->approvalService->getById($id);
        if (!$approval) abort(404);
        $this->authorizeApprovalAccess($approval);
        
        $history = $this->historyService->history($id);
        $currentRole = $this->currentRoleName();

        return view('workflow.show', compact('approval', 'history', 'currentRole'));
    }

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $userEmail = $this->authenticatedActor($user);
        $approval = $this->approvalService->getById($id);
        if (!$approval) abort(404);
        $this->authorizeApprovalAccess($approval);
        $remarks = $request->input('remarks', '');
        
        $this->approvalService->approve($id, $userEmail, $remarks);
        return redirect()->route('approvals.index')->with('success', 'Request Approved.');
    }

    public function reject(Request $request, $id)
    {
        $user = Auth::user();
        $userEmail = $this->authenticatedActor($user);
        $approval = $this->approvalService->getById($id);
        if (!$approval) abort(404);
        $this->authorizeApprovalAccess($approval);
        $remarks = $request->input('remarks', '');
        
        $this->approvalService->reject($id, $userEmail, $remarks);
        return redirect()->route('approvals.index')->with('danger', 'Request Rejected.');
    }

    private function currentRoleName($user = null): string
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            abort(403, 'Sesi pengguna tidak valid.');
        }

        $roleName = strtoupper(trim((string) ($user->Role ?? '')));
        if ($roleName === '' && !empty($user->Role_ID)) {
            $role = app(RoleService::class)->getRoleById($user->Role_ID);
            $roleName = strtoupper(trim($role['Role_Name'] ?? ''));
        }

        if ($roleName === '') {
            abort(403, 'Role pengguna tidak valid.');
        }

        return $roleName;
    }

    private function authorizeApprovalAccess(array $approval): void
    {
        $roleName = $this->currentRoleName();
        if (in_array($roleName, ['MASTER', 'ADMINISTRATOR'])) {
            return;
        }

        if (strtoupper(trim($approval['Current_Approver'] ?? '')) !== $roleName
            || ($approval['Status'] ?? '') !== 'Waiting Approval') {
            abort(403, 'Anda bukan approver aktif untuk request ini.');
        }
    }

    private function authenticatedActor($user): string
    {
        return \App\Support\ActorIdentity::required();
    }
}
