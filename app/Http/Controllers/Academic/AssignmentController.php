<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\AssignmentService;
use App\Services\Core\ActivityLogService;

class AssignmentController extends Controller
{
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
        return view('academic.assignments.create', compact('classes', 'teachers')); 
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
        return view('academic.assignments.edit', compact('assignment', 'classes', 'teachers'));
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
