<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\ApprovalService;
use App\Services\Core\ApprovalHistoryService;
use Illuminate\Support\Facades\Auth;

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
        $userEmail = Auth::user()->email ?? 'user@example.com';
        $userRole = session('role') ?? 'GUEST';
        
        $approvals = $this->approvalService->getAll()
            ->filter(function($a) use ($userRole) {
                return ($a['Current_Approver'] ?? '') == $userRole && ($a['Status'] ?? '') === 'Waiting Approval';
            })->sortByDesc('Submitted_At');

        return view('workflow.index', compact('approvals'));
    }

    public function show($id)
    {
        $approval = $this->approvalService->getById($id);
        if (!$approval) abort(404);
        
        $history = $this->historyService->history($id);

        return view('workflow.show', compact('approval', 'history'));
    }

    public function approve(Request $request, $id)
    {
        $userEmail = Auth::user()->email ?? 'user@example.com';
        $remarks = $request->input('remarks', '');
        
        $this->approvalService->approve($id, $userEmail, $remarks);
        return redirect()->route('approvals.index')->with('success', 'Request Approved.');
    }

    public function reject(Request $request, $id)
    {
        $userEmail = Auth::user()->email ?? 'user@example.com';
        $remarks = $request->input('remarks', '');
        
        $this->approvalService->reject($id, $userEmail, $remarks);
        return redirect()->route('approvals.index')->with('danger', 'Request Rejected.');
    }
}
