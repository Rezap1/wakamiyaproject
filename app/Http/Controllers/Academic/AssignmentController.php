<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\AssignmentService;
use App\Services\Core\ActivityLogService;

class AssignmentController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Deadline';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $assignments = $this->assignmentService->getAll();

        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                $teacherRepo = app(\App\Repositories\GoogleSheets\TeacherRepository::class);
                $teacherData = collect($teacherRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
                if ($teacherData) {
                    $scheduleService = app(\App\Services\Academic\ScheduleService::class);
                    $myClassIds = collect($scheduleService->getAll())
                        ->where('Teacher_ID', $teacherData['Teacher_ID'])
                        ->pluck('Class_ID')
                        ->unique()
                        ->toArray();
                    $assignments = $assignments
                        ->where('Teacher_ID', $teacherData['Teacher_ID'])
                        ->whereIn('Class_ID', $myClassIds)
                        ->values();
                } else {
                    $assignments = collect([]); // No teacher profile, no assignments
                }
            }
        }

        $classRepo = app(\App\Repositories\GoogleSheets\ClassRepository::class);
        $classes = $classRepo->fetchAll()->keyBy('Class_ID');

        return [
            'moduleName' => 'Tugas Harian (Assignments)',
            'data' => collect(array_values($assignments->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Tugas', 'Judul', 'Kelas', 'Deadline', 'Status'],
            'mapRow' => function($row) use ($classes) {
                $classId = $row['Class_ID'] ?? null;
                $className = $classId && isset($classes[$classId]) ? $classes[$classId]['Class_Name'] : $classId;
                return [
                    $row['Assignment_ID'] ?? '-',
                    $row['Title'] ?? '-',
                    $className ?? '-',
                    $row['Deadline'] ?? '-',
                    $row['Status'] ?? 'Active'
                ];
            },
            'isLandscape' => false,
        ];
    }

    protected $assignmentService;
    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    private function currentRoleName(): string
    {
        $user = auth()->user();
        if (!$user) {
            return '';
        }

        $roleName = strtolower(trim((string) ($user->Role ?? '')));
        if ($roleName !== '') {
            return $roleName;
        }

        $roleService = app(\App\Services\Core\RoleService::class);
        return strtolower(trim($roleService->getRoleById($user->Role_ID ?? '')['Role_Name'] ?? ''));
    }

    private function isTeacherUser(): bool
    {
        $roleName = $this->currentRoleName();

        return str_contains($roleName, 'teacher') || str_contains($roleName, 'guru');
    }

    private function currentTeacherId(): ?string
    {
        $user = auth()->user();
        if (!$user || !$this->isTeacherUser()) {
            return null;
        }

        $teacherRepo = app(\App\Repositories\GoogleSheets\TeacherRepository::class);
        $teacherData = collect($teacherRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);

        return $teacherData['Teacher_ID'] ?? null;
    }

    public function index() {
        if ($this->isTeacherUser()) {
            return redirect()->route('teacher.workspace.assignments');
        }

        $assignments = $this->assignmentService->getAll();
        return view('academic.assignments.index', compact('assignments'));
    }
    private function verifyAssignmentScope($assignmentId)
    {
        $user = auth()->user();
        if (!$user) return;

        // Only restrict teachers
        if (!$this->isTeacherUser()) {
            return;
        }

        $assignment = $this->assignmentService->getById($assignmentId);
        if (!$assignment) {
            abort(404, 'Tugas tidak ditemukan.');
        }

        $teacherId = $this->currentTeacherId();
        if (!$teacherId) {
            abort(403, 'Profil Pengajar tidak ditemukan.');
        }

        if (($assignment['Teacher_ID'] ?? '') !== $teacherId) {
            abort(403, 'Anda tidak memiliki hak akses untuk tugas ini (IDOR Protected).');
        }

        if (!$this->teacherCanAccessClass($teacherId, $assignment['Class_ID'] ?? '')) {
            abort(403, 'Anda tidak memiliki hak akses untuk tugas kelas ini (IDOR Protected).');
        }
    }

    private function verifyTeacherClassScope($classId)
    {
        $user = auth()->user();
        if (!$user) return;

        if (!$this->isTeacherUser()) {
            return;
        }

        $teacherId = $this->currentTeacherId();
        if (!$teacherId) {
            abort(403, 'Profil Pengajar tidak ditemukan.');
        }

        if (!$this->teacherCanAccessClass($teacherId, $classId)) {
            abort(403, 'Anda tidak memiliki hak akses untuk kelas ini (IDOR Protected).');
        }
    }

    private function teacherCanAccessClass(string $teacherId, string $classId): bool
    {
        if ($classId === '') {
            return false;
        }

        $scheduleService = app(\App\Services\Academic\ScheduleService::class);
        $hasSchedule = collect($scheduleService->getAll())
            ->where('Teacher_ID', $teacherId)
            ->where('Class_ID', $classId)
            ->isNotEmpty();

        $classRepo = app(\App\Repositories\GoogleSheets\ClassRepository::class);
        $isHomeroom = collect($classRepo->fetchAll())
            ->where('Homeroom_Teacher_ID', $teacherId)
            ->where('Class_ID', $classId)
            ->isNotEmpty();

        return $hasSchedule || $isHomeroom;
    }

    public function create(
        \App\Repositories\GoogleSheets\ClassRepository $classRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo
    ) {
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();

        $currentTeacherId = null;
        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                $teacherData = collect($teachers)->firstWhere('User_ID', $user->User_ID);
                if ($teacherData) {
                    $currentTeacherId = $teacherData['Teacher_ID'];
                }
            }
        }

        if ($currentTeacherId) {
            $scheduleService = app(\App\Services\Academic\ScheduleService::class);
            $scheduleClassIds = collect($scheduleService->getAll())
                ->where('Teacher_ID', $currentTeacherId)
                ->pluck('Class_ID');
            $homeroomClassIds = collect($classes)
                ->where('Homeroom_Teacher_ID', $currentTeacherId)
                ->pluck('Class_ID');
            $allowedClassIds = $scheduleClassIds->merge($homeroomClassIds)->filter()->unique()->values()->all();
            $classes = collect($classes)->whereIn('Class_ID', $allowedClassIds)->values();
        }

        return view('academic.assignments.create', compact('classes', 'teachers', 'currentTeacherId'));
    }
    public function store(\App\Http\Requests\StoreAssignmentRequest $request) {
        $data = $request->validated();
        $this->verifyTeacherClassScope($data['Class_ID']);
        if ($this->isTeacherUser()) {
            $teacherId = $this->currentTeacherId();
            if (!$teacherId) {
                abort(403, 'Profil Pengajar tidak ditemukan.');
            }
            $data['Teacher_ID'] = $teacherId;
        }
        $this->assignmentService->create($data);

        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                return redirect()->route('teacher.workspace.assignments')->with('success', 'Tugas berhasil dibuat!');
            }
        }
        return redirect()->route('assignments.index')->with('success', 'Created!');
    }
    public function show(
        $id,
        \App\Repositories\GoogleSheets\ClassRepository $classRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo
    ) {
        return $this->edit($id, $classRepo, $teacherRepo);
    }

    public function edit(
        $id,
        \App\Repositories\GoogleSheets\ClassRepository $classRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo
    ) {
        $this->verifyAssignmentScope($id);

        $assignment = $this->assignmentService->getById($id);
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();

        $currentTeacherId = null;
        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                $teacherData = collect($teachers)->firstWhere('User_ID', $user->User_ID);
                if ($teacherData) {
                    $currentTeacherId = $teacherData['Teacher_ID'];
                }
            }
        }

        if ($currentTeacherId) {
            $scheduleService = app(\App\Services\Academic\ScheduleService::class);
            $scheduleClassIds = collect($scheduleService->getAll())
                ->where('Teacher_ID', $currentTeacherId)
                ->pluck('Class_ID');
            $homeroomClassIds = collect($classes)
                ->where('Homeroom_Teacher_ID', $currentTeacherId)
                ->pluck('Class_ID');
            $allowedClassIds = $scheduleClassIds->merge($homeroomClassIds)->filter()->unique()->values()->all();
            $classes = collect($classes)->whereIn('Class_ID', $allowedClassIds)->values();
        }

        return view('academic.assignments.edit', compact('assignment', 'classes', 'teachers', 'currentTeacherId'));
    }
    public function update(\App\Http\Requests\UpdateAssignmentRequest $request, $id) {
        $this->verifyAssignmentScope($id);
        $data = $request->validated();
        $this->verifyTeacherClassScope($data['Class_ID']);
        if ($this->isTeacherUser()) {
            $teacherId = $this->currentTeacherId();
            if (!$teacherId) {
                abort(403, 'Profil Pengajar tidak ditemukan.');
            }
            $data['Teacher_ID'] = $teacherId;
        }

        \Illuminate\Support\Facades\Log::info("Assignment Update Payload: " . json_encode($data));

        $this->assignmentService->update($id, $data);

        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                return redirect()->route('teacher.workspace.assignments')->with('success', 'Tugas berhasil diperbarui!');
            }
        }
        return redirect()->route('assignments.index')->with('success', 'Updated!');
    }
    public function destroy($id) {
        $this->verifyAssignmentScope($id);

        $this->assignmentService->delete($id);

        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                return redirect()->route('teacher.workspace.assignments')->with('success', 'Tugas berhasil dihapus!');
            }
        }
        return redirect()->route('assignments.index')->with('success', 'Deleted!');
    }
}
