<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Attendance\AttendanceRequestService;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Helpers\StoragePathHelper;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AttendanceRequestController extends Controller
{
    protected $requestService;
    protected $studentRepo;
    protected $scheduleRepo;

    public function __construct(
        AttendanceRequestService $requestService,
        StudentRepositoryInterface $studentRepo,
        ScheduleRepositoryInterface $scheduleRepo
    ) {
        $this->requestService = $requestService;
        $this->studentRepo = $studentRepo;
        $this->scheduleRepo = $scheduleRepo;
    }

    private function getAuthenticatedStudent()
    {
        $user = auth()->user();
        if (!$user) return null;

        $students = collect($this->studentRepo->fetchAll());
        return $students->firstWhere('User_ID', $user->User_ID);
    }

    public function index()
    {
        $student = $this->getAuthenticatedStudent();
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Profil siswa tidak ditemukan.');
        }

        $requests = $this->requestService->getStudentRequests($student['Student_ID']);

        // Sort descending by Created_At
        $requests = $requests->sortByDesc('Created_At')->values();

        return view('student.attendance.requests-index', compact('requests', 'student'));
    }

    public function create()
    {
        $student = $this->getAuthenticatedStudent();
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Profil siswa tidak ditemukan.');
        }

        // Fetch student's schedules
        $classId = $student['Class_ID'] ?? null;
        $schedules = collect([]);
        if ($classId) {
            $schedules = collect($this->scheduleRepo->fetchAll())->where('Class_ID', $classId)->values();
        }

        return view('student.attendance.request-create', compact('student', 'schedules'));
    }

    public function store(Request $request)
    {
        $student = $this->getAuthenticatedStudent();
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Profil siswa tidak ditemukan.');
        }

        $request->validate([
            'Attendance_Date' => 'required|date',
            'Schedule_ID' => 'required|string',
            'Request_Type' => 'required|in:SAKIT,IZIN',
            'Reason' => 'required|string|max:500',
            'Evidence' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        // Validate Schedule belongs to Class
        $schedule = collect($this->scheduleRepo->fetchAll())->firstWhere('Schedule_ID', $request->Schedule_ID);
        if (!$schedule || ($schedule['Class_ID'] ?? '') !== ($student['Class_ID'] ?? '')) {
            return back()->with('error', 'Jadwal tidak valid atau bukan milik Anda.');
        }

        // Handle File Upload
        $file = $request->file('Evidence');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('attendance-evidence', $filename);

        // Create Attendance_ID to match how Attendance Engine generates or expects it
        // A simple way is to hash schedule + date + student, or just use a standard format
        $attendanceId = 'ATT-' . $student['Student_ID'] . '-' . Carbon::parse($request->Attendance_Date)->format('Ymd') . '-' . $request->Schedule_ID;

        try {
            $this->requestService->createRequest([
                'Attendance_ID' => $attendanceId,
                'Student_ID' => $student['Student_ID'],
                'Schedule_ID' => $request->Schedule_ID,
                'Attendance_Date' => $request->Attendance_Date,
                'Request_Type' => $request->Request_Type,
                'Reason' => $request->Reason,
                'Evidence_URL' => 'storage/' . $path,
            ], auth()->user());

            return redirect()->route('student.attendance.requests.index')->with('success', 'Pengajuan berhasil dikirim dan sedang menunggu review.');
        } catch (\Exception $e) {
            try {
                $persisted = collect($this->requestService->getStudentRequests($student['Student_ID']))
                    ->contains(fn ($item) => ($item['Evidence_URL'] ?? '') === 'storage/' . $path);
                if (!$persisted) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                }
            } catch (\Throwable $lookupFailure) {
                // Preserve the file when persistence cannot be determined safely.
            }
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function downloadEvidence(Request $httpRequest, $id)
    {
        $student = $this->getAuthenticatedStudent();
        if (!$student) {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        $attendanceRequest = $this->requestService->findById($id);
        if (!$attendanceRequest || ($attendanceRequest['Student_ID'] ?? '') !== ($student['Student_ID'] ?? '')) {
            abort(403, 'Akses Ditolak: bukti pengajuan ini bukan milik akun Anda.');
        }

        if (empty($attendanceRequest['Evidence_URL'])) {
            abort(404, 'Bukti tidak ditemukan.');
        }

        $path = StoragePathHelper::privateFileResponsePath($attendanceRequest['Evidence_URL']);
        if (!$path) {
            abort(404, 'File bukti tidak ditemukan di server.');
        }

        if ($httpRequest->boolean('inline')) {
            return response()->file($path);
        }

        return response()->download($path, $this->evidenceFilename($attendanceRequest, $path));
    }

    private function evidenceFilename(array $request, string $path): string
    {
        $requestId = preg_replace('/[^A-Za-z0-9_-]/', '_', $request['Request_ID'] ?? 'request');
        $type = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '_', $request['Request_Type'] ?? 'bukti'));
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return 'surat-' . $type . '-' . $requestId . ($extension ? '.' . $extension : '');
    }
}
