<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SubmissionService;
use App\Services\Core\ActivityLogService;

class SubmissionController extends Controller
{
    protected $submissionService, $activityLogService;
    public function __construct(SubmissionService $submissionService, ActivityLogService $activityLogService)
    {
        $this->submissionService = $submissionService;
        $this->activityLogService = $activityLogService;
    }
    public function index() {
        $submissions = $this->submissionService->getAll();
        return view('academic.submissions.index', compact('submissions'));
    }
    public function create(
        Request $request,
        \App\Repositories\GoogleSheets\AssignmentRepository $assignmentRepo,
        \App\Repositories\GoogleSheets\StudentRepository $studentRepo
    ) { 
        $assignmentId = $request->query('assignment_id');
        $assignments = $assignmentRepo->fetchAll();
        $students = $studentRepo->fetchAll();
        return view('academic.submissions.create', compact('assignmentId', 'assignments', 'students')); 
    }
    public function store(\App\Http\Requests\StoreSubmissionRequest $request) {
        $data = $request->except('_token');
        if ($request->hasFile('AttachmentFile')) {
            // Local file storage
            $path = $request->file('AttachmentFile')->store('submissions', 'public');
            $data['File_URL'] = '/storage/' . $path;
        }
        $data['Submission_Date'] = now()->toDateTimeString();
        $data['Status'] = 'Submitted';
        $this->submissionService->create($data);
        $this->activityLogService->log(auth()->id(), 'UPLOADED', 'Submission', 'Uploaded submission for assignment ' . $request->Assignment_ID);
        return redirect()->route('submissions.index')->with('success', 'Submitted!');
    }
    public function edit(
        $id,
        \App\Repositories\GoogleSheets\AssignmentRepository $assignmentRepo,
        \App\Repositories\GoogleSheets\StudentRepository $studentRepo
    ) {
        $submission = $this->submissionService->getById($id);
        $assignments = $assignmentRepo->fetchAll();
        $students = $studentRepo->fetchAll();
        return view('academic.submissions.edit', compact('submission', 'assignments', 'students'));
    }
    public function update(\App\Http\Requests\UpdateSubmissionRequest $request, $id) {
        $this->submissionService->update($id, $request->except(['_token', '_method']));
        $this->activityLogService->log(auth()->id(), 'REVIEWED', 'Submission', 'Reviewed submission ' . $id);
        return redirect()->route('submissions.index')->with('success', 'Reviewed!');
    }
    public function destroy($id)
    {
        try {
            $this->submissionService->delete($id);
            $this->activityLogService->log(auth()->id(), 'DELETED', 'Submission', 'Deleted submission ' . $id);
            return redirect()->route('submissions.index')->with('success', 'Submission berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
