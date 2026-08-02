<?php
$baseDir = __DIR__;

// Controllers
$ac = <<<PHP
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\AssignmentService;
use App\Services\Core\ActivityLogService;

class AssignmentController extends Controller
{
    protected \$assignmentService, \$activityLogService;
    public function __construct(AssignmentService \$assignmentService, ActivityLogService \$activityLogService)
    {
        \$this->assignmentService = \$assignmentService;
        \$this->activityLogService = \$activityLogService;
    }
    public function index() {
        \$assignments = \$this->assignmentService->getAll();
        return view('academic.assignments.index', compact('assignments'));
    }
    public function create() { return view('academic.assignments.create'); }
    public function store(Request \$request) {
        \$this->assignmentService->create(\$request->except('_token'));
        \$this->activityLogService->log(auth()->id(), 'CREATED', 'Assignment', 'Created assignment: ' . \$request->Title);
        return redirect()->route('assignments.index')->with('success', 'Created!');
    }
    public function edit(\$id) {
        \$assignment = \$this->assignmentService->getById(\$id);
        return view('academic.assignments.edit', compact('assignment'));
    }
    public function update(Request \$request, \$id) {
        \$this->assignmentService->update(\$id, \$request->except(['_token', '_method']));
        \$this->activityLogService->log(auth()->id(), 'UPDATED', 'Assignment', 'Updated assignment ' . \$id);
        return redirect()->route('assignments.index')->with('success', 'Updated!');
    }
    public function destroy(\$id) {
        \$this->assignmentService->delete(\$id);
        return redirect()->route('assignments.index')->with('success', 'Deleted!');
    }
}
PHP;
file_put_contents("$baseDir/app/Http/Controllers/Academic/AssignmentController.php", $ac);

$sc = <<<PHP
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SubmissionService;
use App\Services\Core\ActivityLogService;

class SubmissionController extends Controller
{
    protected \$submissionService, \$activityLogService;
    public function __construct(SubmissionService \$submissionService, ActivityLogService \$activityLogService)
    {
        \$this->submissionService = \$submissionService;
        \$this->activityLogService = \$activityLogService;
    }
    public function index() {
        \$submissions = \$this->submissionService->getAll();
        return view('academic.submissions.index', compact('submissions'));
    }
    public function create(Request \$request) { 
        \$assignmentId = \$request->query('assignment_id');
        return view('academic.submissions.create', compact('assignmentId')); 
    }
    public function store(Request \$request) {
        \$data = \$request->except('_token');
        if (\$request->hasFile('AttachmentFile')) {
            // Local file storage
            \$path = \$request->file('AttachmentFile')->store('submissions', 'public');
            \$data['File_URL'] = '/storage/' . \$path;
        }
        \$data['Submission_Date'] = now()->toDateTimeString();
        \$data['Status'] = 'Submitted';
        \$this->submissionService->create(\$data);
        \$this->activityLogService->log(auth()->id(), 'UPLOADED', 'Submission', 'Uploaded submission for assignment ' . \$request->Assignment_ID);
        return redirect()->route('submissions.index')->with('success', 'Submitted!');
    }
    public function edit(\$id) {
        \$submission = \$this->submissionService->getById(\$id);
        return view('academic.submissions.edit', compact('submission'));
    }
    public function update(Request \$request, \$id) {
        \$this->submissionService->update(\$id, \$request->except(['_token', '_method']));
        \$this->activityLogService->log(auth()->id(), 'REVIEWED', 'Submission', 'Reviewed submission ' . \$id);
        return redirect()->route('submissions.index')->with('success', 'Reviewed!');
    }
}
PHP;
file_put_contents("$baseDir/app/Http/Controllers/Academic/SubmissionController.php", $sc);

// Views
@mkdir("$baseDir/resources/views/academic/assignments", 0777, true);
@mkdir("$baseDir/resources/views/academic/submissions", 0777, true);

$asnIdx = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Assignments')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <div class="flex justify-between mb-4">
        <h2 class="text-xl font-bold">Assignments</h2>
        <a href="{{ route('assignments.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded">Add Assignment</a>
    </div>
    <table class="min-w-full"><tr class="bg-gray-100"><th>Title</th><th>Teacher</th><th>Deadline</th><th>Status</th><th>Aksi</th></tr>
    @foreach($assignments as $a)
    <tr>
        <td>{{ $a['Title']??'-' }}</td><td>{{ $a['Teacher_ID']??'-' }}</td><td>{{ $a['Deadline']??'-' }}</td>
        <td>{{ $a['Status']??'Draft' }}</td>
        <td><a href="{{ route('assignments.edit', $a['Assignment_ID']) }}" class="text-blue-500">Edit</a></td>
    </tr>
    @endforeach
    </table>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/assignments/index.blade.php", $asnIdx);

$asnForm = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Form Assignment')
@section('content')
<div class="bg-white rounded-2xl shadow p-6"><form action="{{ isset($assignment) ? route('assignments.update', $assignment['Assignment_ID']) : route('assignments.store') }}" method="POST">
    @csrf @if(isset($assignment)) @method('PUT') @endif
    <div class="grid grid-cols-2 gap-4">
        <div><label class="block font-bold">Title</label><input type="text" name="Title" value="{{ $assignment['Title']??'' }}" class="w-full border-gray-300 rounded"></div>
        <div><label class="block font-bold">Class ID</label><input type="text" name="Class_ID" value="{{ $assignment['Class_ID']??'' }}" class="w-full border-gray-300 rounded"></div>
        <div><label class="block font-bold">Teacher ID</label><input type="text" name="Teacher_ID" value="{{ $assignment['Teacher_ID']??'' }}" class="w-full border-gray-300 rounded"></div>
        <div><label class="block font-bold">Deadline</label><input type="date" name="Deadline" value="{{ $assignment['Deadline']??'' }}" class="w-full border-gray-300 rounded"></div>
        <div><label class="block font-bold">Status</label><select name="Status" class="w-full border-gray-300 rounded"><option>Draft</option><option>Published</option><option>Closed</option></select></div>
        <div class="col-span-2"><label class="block font-bold">Description</label><textarea name="Description" class="w-full border-gray-300 rounded">{{ $assignment['Description']??'' }}</textarea></div>
    </div>
    <div class="mt-4"><button class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button></div>
</form></div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/assignments/create.blade.php", $asnForm);
file_put_contents("$baseDir/resources/views/academic/assignments/edit.blade.php", $asnForm);

$subIdx = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Submissions')
@section('content')
<div class="bg-white rounded-2xl shadow p-6">
    <table class="min-w-full"><tr class="bg-gray-100"><th>Student</th><th>Assignment</th><th>Date</th><th>Status</th><th>Score</th><th>Aksi</th></tr>
    @foreach($submissions as $s)
    <tr>
        <td>{{ $s['Student_ID']??'-' }}</td><td>{{ $s['Assignment_ID']??'-' }}</td><td>{{ $s['Submission_Date']??'-' }}</td>
        <td>{{ $s['Status']??'-' }}</td><td>{{ $s['Grade_Received']??'-' }}</td>
        <td><a href="{{ route('submissions.edit', $s['Submission_ID']) }}" class="text-blue-500">Review</a></td>
    </tr>
    @endforeach
    </table>
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/submissions/index.blade.php", $subIdx);

$subCreate = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Upload Submission')
@section('content')
<div class="bg-white rounded-2xl shadow p-6"><form action="{{ route('submissions.store') }}" method="POST" enctype="multipart/form-data">@csrf
    <input type="hidden" name="Assignment_ID" value="{{ $assignmentId }}">
    <div class="grid gap-4">
        <div><label class="block font-bold">Student ID</label><input type="text" name="Student_ID" class="w-full border-gray-300 rounded"></div>
        <div><label class="block font-bold">Attachment File</label><input type="file" name="AttachmentFile" class="w-full"></div>
        <div><label class="block font-bold">Comment</label><textarea name="Comment" class="w-full border-gray-300 rounded"></textarea></div>
    </div>
    <div class="mt-4"><button class="bg-blue-600 text-white px-4 py-2 rounded">Upload</button></div>
</form></div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/submissions/create.blade.php", $subCreate);

$subEdit = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Review Submission')
@section('content')
<div class="bg-white rounded-2xl shadow p-6"><form action="{{ route('submissions.update', $submission['Submission_ID']) }}" method="POST">@csrf @method('PUT')
    <div class="grid gap-4">
        <div><label class="block font-bold">Student ID</label><input type="text" value="{{ $submission['Student_ID']??'' }}" class="w-full border-gray-300 rounded" readonly></div>
        <div><label class="block font-bold">File</label><a href="{{ $submission['File_URL']??'#' }}" target="_blank" class="text-blue-600 underline">Lihat File</a></div>
        <div><label class="block font-bold">Score</label><input type="number" name="Grade_Received" value="{{ $submission['Grade_Received']??'' }}" class="w-full border-gray-300 rounded"></div>
        <div><label class="block font-bold">Feedback</label><textarea name="Feedback" class="w-full border-gray-300 rounded">{{ $submission['Feedback']??'' }}</textarea></div>
        <div><label class="block font-bold">Status</label><select name="Status" class="w-full border-gray-300 rounded"><option>Reviewed</option><option>Completed</option></select></div>
    </div>
    <div class="mt-4"><button class="bg-blue-600 text-white px-4 py-2 rounded">Simpan Review</button></div>
</form></div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/academic/submissions/edit.blade.php", $subEdit);

echo "Controllers and views created.\n";
