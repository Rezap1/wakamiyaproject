<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Attendance\AttendanceRequestService;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Helpers\AttendanceStatusHelper;
use App\Helpers\CollectionHelper;
use App\Helpers\StoragePathHelper;
use App\Support\Reporting\HumanReadableResolver;

class AttendanceRequestController extends Controller
{
    protected $requestService;
    protected $studentRepo;
    protected $scheduleRepo;
    protected $batchRepo;
    protected $classRepo;
    protected $attendanceRepo;
    protected $subjectRepo;
    protected $teacherRepo;

    public function __construct(
        AttendanceRequestService $requestService,
        StudentRepositoryInterface $studentRepo,
        ScheduleRepositoryInterface $scheduleRepo,
        BatchRepositoryInterface $batchRepo,
        ClassRepositoryInterface $classRepo,
        ?AttendanceRepositoryInterface $attendanceRepo = null,
        ?SubjectRepositoryInterface $subjectRepo = null,
        ?TeacherRepositoryInterface $teacherRepo = null
    ) {
        $this->requestService = $requestService;
        $this->studentRepo = $studentRepo;
        $this->scheduleRepo = $scheduleRepo;
        $this->batchRepo = $batchRepo;
        $this->classRepo = $classRepo;
        $this->attendanceRepo = $attendanceRepo;
        $this->subjectRepo = $subjectRepo;
        $this->teacherRepo = $teacherRepo;
    }

    public function index(Request $request)
    {
        $allRequests = collect($this->requestService->getAll());
        $students = collect($this->studentRepo->fetchAll());
        $classes = collect($this->classRepo->fetchAll());
        $schedules = collect($this->scheduleRepo->fetchAll());
        $subjects = $this->subjectRepo ? collect($this->subjectRepo->fetchAll()) : collect();
        $studentsById = $students->keyBy('Student_ID');
        $classesById = $classes->keyBy('Class_ID');
        $schedulesById = $schedules->keyBy('Schedule_ID');
        $subjectsById = $subjects->keyBy('Subject_ID');
        
        $enrichedRequests = $allRequests->map(fn ($req) => $this->enrichRequest(
            $req,
            $studentsById,
            $classesById,
            $schedulesById,
            $subjectsById
        ));

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
        $isTeacher = $this->isTeacherUser();
        $request = $isTeacher
            ? $this->requestService->assertTeacherCanReviewRequest($id, auth()->user())
            : $this->requestService->findById($id);
        if (!$request) {
            return redirect()->route($this->indexRouteName())->with('error', 'Pengajuan tidak ditemukan.');
        }

        $students = collect($this->studentRepo->fetchAll());
        $classes = collect($this->classRepo->fetchAll());
        $schedules = collect($this->scheduleRepo->fetchAll());
        $subjects = $this->subjectRepo ? collect($this->subjectRepo->fetchAll()) : collect();

        $request = $this->enrichRequest(
            $request,
            $students->keyBy('Student_ID'),
            $classes->keyBy('Class_ID'),
            $schedules->keyBy('Schedule_ID'),
            $subjects->keyBy('Subject_ID')
        );
        $existingAttendance = null;
        if ($this->attendanceRepo && !empty($request['Attendance_ID'])) {
            $existingAttendance = $this->attendanceRepo->findById($request['Attendance_ID']);
        }
        $request['Existing_Attendance'] = $existingAttendance;
        $request['Existing_Attendance_Status_Label'] = AttendanceStatusHelper::label($existingAttendance['Status'] ?? '');

        $canReview = $isTeacher && ($request['Status_Normalized'] ?? '') === 'PENDING';
        $routePrefix = $isTeacher ? 'teacher.workspace.attendance-requests' : 'academic.attendance.requests';
        $backRoute = route($this->indexRouteName());
        $evidenceRoute = $routePrefix . '.evidence';
        $approveRoute = $routePrefix . '.approve';
        $rejectRoute = $routePrefix . '.reject';

        return view('academic.attendance.request-show', compact(
            'request',
            'canReview',
            'backRoute',
            'evidenceRoute',
            'approveRoute',
            'rejectRoute',
            'isTeacher'
        ));
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

            return redirect()->route($this->indexRouteName())->with('success', 'Pengajuan berhasil disetujui.');
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

            return redirect()->route($this->indexRouteName())->with('success', 'Pengajuan berhasil ditolak.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function downloadEvidence(Request $httpRequest, $id)
    {
        $request = $this->isTeacherUser()
            ? $this->requestService->assertTeacherCanReviewRequest($id, auth()->user())
            : $this->requestService->findById($id);
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

    private function enrichRequest($request, $studentsById, $classesById, $schedulesById, $subjectsById): array
    {
        $request = is_array($request) ? $request : (array) $request;
        $student = $studentsById->get($request['Student_ID'] ?? '');
        $studentClassId = trim((string) ($student['Class_ID'] ?? $request['Class_ID'] ?? ''));
        $attendanceType = strtoupper(trim((string) ($request['Attendance_Type'] ?? '')))
            ?: (empty($request['Schedule_ID']) ? 'CLASS_QR' : 'SCHEDULE');
        $isClassBased = in_array($attendanceType, ['CLASS_QR', 'CLASS_MANUAL'], true);
        $schedule = $isClassBased ? null : $schedulesById->get($request['Schedule_ID'] ?? '');

        $request['Student_Name'] = HumanReadableResolver::studentName($request['Student_ID'] ?? '', $studentsById);
        $request['Class_Name'] = HumanReadableResolver::className($studentClassId, $classesById);
        $request['Batch_ID'] = $student['Batch_ID'] ?? '';
        $request['Attendance_Type'] = $isClassBased ? 'CLASS_QR' : 'SCHEDULE';
        $request['Target_Display'] = $isClassBased
            ? 'Absensi Kelas / QR'
            : HumanReadableResolver::scheduleLabel($request['Schedule_ID'] ?? '', $schedulesById, $classesById, $subjectsById);
        $request['Schedule_Display'] = $request['Target_Display'];
        $request['Subject_Name'] = $isClassBased
            ? 'Absensi Kelas / QR'
            : HumanReadableResolver::subjectName($schedule['Subject_ID'] ?? '', $subjectsById);
        $request['Status_Normalized'] = $this->requestStatus($request['Status'] ?? '');
        $request['Status_Label'] = $this->requestStatusLabel($request['Status'] ?? '');
        $request['Request_Type_Label'] = AttendanceStatusHelper::label($request['Request_Type'] ?? '');
        $request['Reviewed_By_Name'] = $this->reviewerName($request['Reviewed_By'] ?? '');

        return $request;
    }

    private function indexRouteName(): string
    {
        return $this->isTeacherUser()
            ? 'teacher.workspace.attendance-requests'
            : 'academic.attendance.requests.index';
    }

    private function isTeacherUser(): bool
    {
        $role = $this->currentRoleName();

        return str_contains($role, 'TEACHER') || str_contains($role, 'GURU');
    }

    private function currentRoleName(): string
    {
        $user = auth()->user();
        if (!$user) {
            return '';
        }

        $roleName = strtoupper(trim((string) ($user->Role ?? $user->Role_Name ?? '')));
        if ($roleName !== '') {
            return $roleName;
        }

        try {
            return strtoupper(trim((string) (app(\App\Services\Core\RoleService::class)->getRoleById($user->Role_ID ?? '')['Role_Name'] ?? '')));
        } catch (\Throwable) {
            return '';
        }
    }

    private function requestStatus(?string $status): string
    {
        $status = strtoupper(trim((string) $status));
        $status = str_replace([' ', '-'], '_', $status);

        return in_array($status, ['', 'PENDING', 'WAITING', 'WAITING_APPROVAL', 'WAITING_REVIEW', 'SUBMITTED'], true)
            ? 'PENDING'
            : $status;
    }

    private function requestStatusLabel(?string $status): string
    {
        return match ($this->requestStatus($status)) {
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            default => 'Menunggu Review',
        };
    }

    private function reviewerName(?string $reviewedBy): string
    {
        $reviewedBy = trim((string) $reviewedBy);
        if ($reviewedBy === '' || !$this->teacherRepo) {
            return 'Belum direview';
        }

        $teacher = collect($this->teacherRepo->fetchAll())->firstWhere('User_ID', $reviewedBy);

        return trim((string) ($teacher['Full_Name'] ?? $teacher['Teacher_Name'] ?? '')) ?: 'Reviewer tidak ditemukan';
    }
}
