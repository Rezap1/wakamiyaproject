<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HR\OvertimeService;
use App\Http\Requests\StoreOvertimeRequest;
use App\Helpers\ReportHelper;
use App\Helpers\UserResolverHelper;
use App\Support\Reporting\HumanReadableResolver;

class OvertimeController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $overtimes = collect($this->overtimeService->getAllOvertimes());
        $employeesById = collect(app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll())
            ->keyBy('Employee_ID');

        return [
            'moduleName' => 'Pengajuan Lembur (Overtime Requests)',
            'data' => $overtimes,
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Pegawai', 'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Durasi (Jam)', 'Upah Lembur (Rp)', 'Status'],
            'mapRow' => function($row) use ($employeesById) {
                return [
                    HumanReadableResolver::employeeName($row['Employee_ID'] ?? '', $employeesById),
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

        $overtimes = $overtimes->map(function($ot) {
            $ot['Employee_Name'] = UserResolverHelper::getName($ot['Employee_ID'] ?? '');
            $ot['Approved_By_Name'] = UserResolverHelper::getName($ot['Approved_By'] ?? '');
            return $ot;
        });

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
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        try {
            $docData = $this->overtimeService->getOvertimeDocumentData($id);
            $docData['overtime']['Employee_Name'] = UserResolverHelper::getName($docData['overtime']['Employee_ID'] ?? '');
            $docData['overtime']['Approved_By_Name'] = UserResolverHelper::getName($docData['overtime']['Approved_By'] ?? '');
            return view('hr.overtimes.show', ['overtime' => $docData['overtime'], 'docData' => $docData]);
        } catch (\Exception $e) {
            return redirect()->route('hr.overtimes.index')->with('error', $this->safeExceptionMessage($e));
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
            $this->overtimeService->approveOvertime($id, $approver);
            return redirect()->route('hr.overtimes.show', $id)->with('success', 'Pengajuan lembur disetujui (Approved).');
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
            $this->overtimeService->rejectOvertime($id, $approver, $reason);
            return redirect()->route('hr.overtimes.show', $id)->with('success', 'Pengajuan lembur ditolak (Rejected).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function downloadOvertimePdf($id)
    {
        try {
            $docData = $this->overtimeService->getOvertimeDocumentData($id);
            $docData['overtime']['Employee_Name'] = UserResolverHelper::getName($docData['overtime']['Employee_ID'] ?? '');
            $docData['overtime']['Approved_By'] = UserResolverHelper::getName($docData['overtime']['Approved_By'] ?? '');
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
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function verifyOvertimePublic($id)
    {
        try {
            $docData = $this->overtimeService->getOvertimeDocumentData($id, true);
            $docData['overtime']['Employee_Name'] = UserResolverHelper::getName($docData['overtime']['Employee_ID'] ?? '');
            $docData['overtime']['Approved_By'] = UserResolverHelper::getName($docData['overtime']['Approved_By'] ?? '');
            return view('hr.overtimes.verify_overtime_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $this->safeExceptionMessage($e, 'Dokumen lembur tidak ditemukan atau tidak tersedia.'));
        }
    }
}
