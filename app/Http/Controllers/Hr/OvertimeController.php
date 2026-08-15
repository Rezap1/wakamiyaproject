<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HR\OvertimeService;
use App\Http\Requests\StoreOvertimeRequest;
use App\Helpers\ReportHelper;

class OvertimeController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $overtimes = collect($this->overtimeService->getAllOvertimes());

        return [
            'moduleName' => 'Pengajuan Lembur (Overtime Requests)',
            'data' => $overtimes,
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Lembur', 'Pegawai', 'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Durasi (Jam)', 'Upah Lembur (Rp)', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Overtime_ID'] ?? '-',
                    $row['Employee_Name'] ?? $row['Employee_ID'] ?? '-',
                    $row['Date'] ?? '-',
                    $row['Start_Time'] ?? '-',
                    $row['End_Time'] ?? '-',
                    ($row['Duration_Hours'] ?? 0) . ' Jam',
                    'Rp ' . number_format((float)($row['Overtime_Pay'] ?? 0), 0, ',', '.'),
                    $row['Status'] ?? 'SUBMITTED'
                ];
            },
            'isLandscape' => true,
        ];
    }

    protected $overtimeService;

    public function __construct(OvertimeService $overtimeService)
    {
        $this->overtimeService = $overtimeService;
    }

    public function index(Request $request)
    {
        $overtimes = collect($this->overtimeService->getAllOvertimes());

        $user = auth()->user();
        if ($user && in_array(strtoupper($user->Role ?? ''), ['TEACHER', 'EMPLOYEE'])) {
            $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
            $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($employee) {
                $overtimes = $overtimes->where('Employee_ID', $employee['Employee_ID'])->values();
            } else {
                $overtimes = collect();
            }
        }

        $overtimes = \App\Helpers\CollectionHelper::paginate($overtimes, 10)->withQueryString();

        return view('hr.overtimes.index', compact('overtimes'));
    }

    public function create()
    {
        return view('hr.overtimes.create');
    }

    public function store(StoreOvertimeRequest $request)
    {
        try {
            $ot = $this->overtimeService->createOvertimeRequest($request->validated());
            return redirect()->route('hr.overtimes.show', $ot['Overtime_ID'])
                ->with('success', 'Pengajuan lembur berhasil dikirim.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        try {
            $docData = $this->overtimeService->getOvertimeDocumentData($id);
            return view('hr.overtimes.show', ['overtime' => $docData['overtime'], 'docData' => $docData]);
        } catch (\Exception $e) {
            return redirect()->route('hr.overtimes.index')->with('error', $e->getMessage());
        }
    }

    public function approve($id)
    {
        try {
            $approver = auth()->user()->Email ?? 'HR Manager';
            $this->overtimeService->approveOvertime($id, $approver);
            return redirect()->route('hr.overtimes.show', $id)->with('success', 'Pengajuan lembur disetujui (Approved).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $approver = auth()->user()->Email ?? 'HR Manager';
            $reason = $request->input('reason');
            $this->overtimeService->rejectOvertime($id, $approver, $reason);
            return redirect()->route('hr.overtimes.show', $id)->with('success', 'Pengajuan lembur ditolak (Rejected).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function downloadOvertimePdf($id)
    {
        try {
            $docData = $this->overtimeService->getOvertimeDocumentData($id);
            return ReportHelper::export(
                'pdf',
                'Surat_Lembur_' . $id,
                collect([$docData['overtime']]),
                $docData,
                'pdf.official_overtime_approval',
                [],
                null,
                false
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function verifyOvertimePublic($id)
    {
        try {
            $docData = $this->overtimeService->getOvertimeDocumentData($id);
            return view('hr.overtimes.verify_overtime_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $e->getMessage());
        }
    }
}
