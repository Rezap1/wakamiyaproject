<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Attendance\AttendanceRequestService;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Helpers\CollectionHelper;
use App\Helpers\StoragePathHelper;

class AttendanceRequestController extends Controller
{
    protected $requestService;
    protected $studentRepo;
    protected $scheduleRepo;
    protected $batchRepo;
    protected $classRepo;

    public function __construct(
        AttendanceRequestService $requestService,
        StudentRepositoryInterface $studentRepo,
        ScheduleRepositoryInterface $scheduleRepo,
        BatchRepositoryInterface $batchRepo,
        ClassRepositoryInterface $classRepo
    ) {
        $this->requestService = $requestService;
        $this->studentRepo = $studentRepo;
        $this->scheduleRepo = $scheduleRepo;
        $this->batchRepo = $batchRepo;
        $this->classRepo = $classRepo;
    }

    public function index(Request $request)
    {
        $allRequests = collect($this->requestService->getAll());
        $students = collect($this->studentRepo->fetchAll());
        $classes = collect($this->classRepo->fetchAll());
        
        // Enrich data
        $enrichedRequests = $allRequests->map(function($req) use ($students, $classes) {
            $student = $students->firstWhere('Student_ID', $req['Student_ID'] ?? '');
            $class = $classes->firstWhere('Class_ID', $student['Class_ID'] ?? '');
            
            $req['Student_Name'] = $student['Full_Name'] ?? 'Unknown';
            $req['Class_Name'] = $class['Class_Name'] ?? 'Unknown';
            $req['Batch_ID'] = $student['Batch_ID'] ?? 'Unknown';
            return $req;
        });

        // Default Filter to PENDING if not specified
        $statusFilter = $request->query('status', 'PENDING');
        if ($statusFilter !== 'ALL') {
            $enrichedRequests = $enrichedRequests->where('Status', $statusFilter);
        }

        // Sort descending by created at
        $enrichedRequests = $enrichedRequests->sortByDesc('Created_At')->values();

        $paginated = CollectionHelper::paginate($enrichedRequests, 25)->withQueryString();

        return view('academic.attendance.requests-index', compact('paginated', 'statusFilter'));
    }

    public function show($id)
    {
        $request = $this->requestService->findById($id);
        if (!$request) {
            return redirect()->route('academic.attendance.requests.index')->with('error', 'Pengajuan tidak ditemukan.');
        }

        $students = collect($this->studentRepo->fetchAll());
        $classes = collect($this->classRepo->fetchAll());
        $schedules = collect($this->scheduleRepo->fetchAll());

        $student = $students->firstWhere('Student_ID', $request['Student_ID']);
        $class = $classes->firstWhere('Class_ID', $student['Class_ID'] ?? '');
        $schedule = $schedules->firstWhere('Schedule_ID', $request['Schedule_ID']);

        $request['Student_Name'] = $student['Full_Name'] ?? 'Unknown';
        $request['Class_Name'] = $class['Class_Name'] ?? 'Unknown';
        $request['Subject_Name'] = $schedule['Subject_Name'] ?? $schedule['Subject_ID'] ?? 'Unknown';

        return view('academic.attendance.request-show', compact('request'));
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'Approve_As' => 'required|in:SAKIT,IZIN',
            'Academic_Notes' => 'nullable|string|max:500'
        ]);

        try {
            $this->requestService->approveRequest(
                $id, 
                $request->Approve_As, 
                $request->Academic_Notes ?? '', 
                auth()->user()
            );

            return redirect()->route('academic.attendance.requests.index')->with('success', 'Pengajuan berhasil disetujui.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'Academic_Notes' => 'required|string|max:500'
        ]);

        try {
            $this->requestService->rejectRequest(
                $id, 
                $request->Academic_Notes, 
                auth()->user()
            );

            return redirect()->route('academic.attendance.requests.index')->with('success', 'Pengajuan berhasil ditolak.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function downloadEvidence(Request $httpRequest, $id)
    {
        $request = $this->requestService->findById($id);
        if (!$request || empty($request['Evidence_URL'])) {
            abort(404, 'Bukti tidak ditemukan.');
        }

        $path = StoragePathHelper::privateFileResponsePath($request['Evidence_URL']);
        if (!$path) {
            abort(404, 'File bukti tidak ditemukan di server.');
        }

        if ($httpRequest->boolean('inline')) {
            return response()->file($path);
        }

        return response()->download($path, $this->evidenceFilename($request, $path));
    }

    private function evidenceFilename(array $request, string $path): string
    {
        $requestId = preg_replace('/[^A-Za-z0-9_-]/', '_', $request['Request_ID'] ?? 'request');
        $type = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '_', $request['Request_Type'] ?? 'bukti'));
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return 'surat-' . $type . '-' . $requestId . ($extension ? '.' . $extension : '');
    }
}
