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

    protected $assignmentService, $activityLogService;
    public function __construct(AssignmentService $assignmentService, ActivityLogService $activityLogService)
    {
        $this->assignmentService = $assignmentService;
        $this->activityLogService = $activityLogService;
    }
    public function index() {
        $assignments = $this->assignmentService->getAll();
        return view('academic.assignments.index', compact('assignments'));
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
            $roleService = app(\App\Services\Core\RoleService::class);
            $role = $roleService->getRoleById($user->Role_ID ?? '');
            $roleName = strtolower(trim($role['Role_Name'] ?? ''));
            
            if (str_contains($roleName, 'teacher') || str_contains($roleName, 'guru')) {
                $teacherData = collect($teachers)->firstWhere('User_ID', $user->User_ID);
                if ($teacherData) {
                    $currentTeacherId = $teacherData['Teacher_ID'];
                }
            }
        }
        
        return view('academic.assignments.create', compact('classes', 'teachers', 'currentTeacherId')); 
    }
    public function store(\App\Http\Requests\StoreAssignmentRequest $request) {
        $this->assignmentService->create($request->except('_token'));
        $this->activityLogService->log(auth()->id(), 'CREATED', 'Assignment', 'Created assignment: ' . $request->Title);
        return redirect()->route('assignments.index')->with('success', 'Created!');
    }
    public function edit(
        $id,
        \App\Repositories\GoogleSheets\ClassRepository $classRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo
    ) {
        $assignment = $this->assignmentService->getById($id);
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        
        $currentTeacherId = null;
        $user = auth()->user();
        if ($user) {
            $roleService = app(\App\Services\Core\RoleService::class);
            $role = $roleService->getRoleById($user->Role_ID ?? '');
            $roleName = strtolower(trim($role['Role_Name'] ?? ''));
            
            if (str_contains($roleName, 'teacher') || str_contains($roleName, 'guru')) {
                $teacherData = collect($teachers)->firstWhere('User_ID', $user->User_ID);
                if ($teacherData) {
                    $currentTeacherId = $teacherData['Teacher_ID'];
                }
            }
        }
        
        return view('academic.assignments.edit', compact('assignment', 'classes', 'teachers', 'currentTeacherId'));
    }
    public function update(\App\Http\Requests\UpdateAssignmentRequest $request, $id) {
        $this->assignmentService->update($id, $request->except(['_token', '_method']));
        $this->activityLogService->log(auth()->id(), 'UPDATED', 'Assignment', 'Updated assignment ' . $id);
        return redirect()->route('assignments.index')->with('success', 'Updated!');
    }
    public function destroy($id) {
        $this->assignmentService->delete($id);
        return redirect()->route('assignments.index')->with('success', 'Deleted!');
    }
}
