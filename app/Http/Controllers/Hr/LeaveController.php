<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HR\LeaveService;
use App\Http\Requests\StoreLeaveRequest;
use App\Helpers\ReportHelper;
use App\Helpers\UserResolverHelper;

class LeaveController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $leaves = collect($this->leaveService->getAllLeaves());

        return [
            'moduleName' => 'Pengajuan Cuti (Leave Requests)',
            'data' => $leaves,
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Cuti', 'Pegawai', 'Tipe Cuti', 'Tanggal Mulai', 'Tanggal Selesai', 'Durasi', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Leave_ID'] ?? '-',
                    UserResolverHelper::getName($row['Employee_ID'] ?? ''),
                    $row['Leave_Type'] ?? '-',
                    $row['Start_Date'] ?? '-',
                    $row['End_Date'] ?? '-',
                    ($row['Duration_Days'] ?? 1) . ' Hari',
                    $row['Status'] ?? 'SUBMITTED'
                ];
            },
            'isLandscape' => true,
        ];
    }

    protected $leaveService;

    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    public function index(Request $request)
    {
        $leaves = collect($this->leaveService->getAllLeaves());

        $user = auth()->user();
        if ($user && in_array(strtoupper($user->Role ?? ''), ['TEACHER', 'EMPLOYEE'])) {
            $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
            $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($employee) {
                $leaves = $leaves->where('Employee_ID', $employee['Employee_ID'])->values();
            } else {
                $leaves = collect();
            }
        }

        $leaves = $leaves->map(function($l) {
            $l['Employee_Name'] = UserResolverHelper::getName($l['Employee_ID'] ?? '');
            $l['Approved_By_Name'] = UserResolverHelper::getName($l['Approved_By'] ?? '');
            return $l;
        });

        $leaves = \App\Helpers\CollectionHelper::paginate($leaves, 10)->withQueryString();

        return view('hr.leaves.index', compact('leaves'));
    }

    public function create()
    {
        return view('hr.leaves.create');
    }

    public function store(StoreLeaveRequest $request)
    {
        try {
            $leave = $this->leaveService->createLeaveRequest($request->validated());
            return redirect()->route('hr.leaves.show', $leave['Leave_ID'])
                ->with('success', 'Pengajuan cuti berhasil dikirim.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        try {
            $docData = $this->leaveService->getLeaveDocumentData($id);
            $docData['leave']['Employee_Name'] = UserResolverHelper::getName($docData['leave']['Employee_ID'] ?? '');
            $docData['leave']['Approved_By_Name'] = UserResolverHelper::getName($docData['leave']['Approved_By'] ?? '');
            return view('hr.leaves.show', ['leave' => $docData['leave'], 'docData' => $docData]);
        } catch (\Exception $e) {
            return redirect()->route('hr.leaves.index')->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function approve($id)
    {
        try {
            $user = auth()->user();
            $approver = $user->Email ?? $user->email ?? $user->Username ?? $user->User_ID ?? null;
            if (!$approver) {
                abort(403, 'Identitas approver tidak valid.');
            }
            $this->leaveService->approveLeave($id, $approver);
            return redirect()->route('hr.leaves.show', $id)->with('success', 'Pengajuan cuti disetujui (Approved).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $approver = $user->Email ?? $user->email ?? $user->Username ?? $user->User_ID ?? null;
            if (!$approver) {
                abort(403, 'Identitas approver tidak valid.');
            }
            $reason = $request->input('reason');
            $this->leaveService->rejectLeave($id, $approver, $reason);
            return redirect()->route('hr.leaves.show', $id)->with('success', 'Pengajuan cuti ditolak (Rejected).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function cancel($id)
    {
        try {
            $user = $this->authenticatedActor();
            $this->leaveService->cancelLeave($id, $user);
            return redirect()->route('hr.leaves.show', $id)->with('success', 'Pengajuan cuti dibatalkan (Cancelled).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function downloadLeavePdf($id)
    {
        try {
            $docData = $this->leaveService->getLeaveDocumentData($id);
            $docData['leave']['Employee_Name'] = UserResolverHelper::getName($docData['leave']['Employee_ID'] ?? '');
            $docData['leave']['Approved_By'] = UserResolverHelper::getName($docData['leave']['Approved_By'] ?? '');
            return ReportHelper::export(
                'pdf',
                'Surat_Cuti_' . $id,
                collect([$docData['leave']]),
                $docData,
                'pdf.official_leave_approval',
                [],
                null,
                false
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function verifyLeavePublic($id)
    {
        try {
            $docData = $this->leaveService->getLeaveDocumentData($id, true);
            $docData['leave']['Employee_Name'] = UserResolverHelper::getName($docData['leave']['Employee_ID'] ?? '');
            $docData['leave']['Approved_By'] = UserResolverHelper::getName($docData['leave']['Approved_By'] ?? '');
            return view('hr.leaves.verify_leave_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $this->safeExceptionMessage($e, 'Dokumen cuti tidak ditemukan atau tidak tersedia.'));
        }
    }

    private function authenticatedActor(): string
    {
        $user = auth()->user();
        $actor = $user->User_ID ?? $user->Email ?? $user->email ?? null;
        if (!$actor) {
            abort(403, 'Identitas pengguna tidak valid.');
        }

        return $actor;
    }
}
