<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SubmissionService;
use App\Services\Core\ActivityLogService;

class SubmissionController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Submission_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $submissions = $this->submissionService->getAll();

        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');

        return [
            'moduleName' => 'Pengumpulan Tugas (Submissions)',
            'data' => collect(array_values($submissions->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID', 'Siswa', 'Assignment', 'Tanggal', 'Status', 'Nilai'],
            'mapRow' => function($row) use ($students) {
                $studentId = $row['Student_ID'] ?? null;
                $studentName = $studentId && isset($students[$studentId]) ? $students[$studentId]['Full_Name'] : $studentId;
                return [
                    $row['Submission_ID'] ?? '-',
                    $studentName . ($studentId ? " ($studentId)" : ''),
                    $row['Assignment_ID'] ?? '-',
                    $row['Submission_Date'] ?? '-',
                    $row['Status'] ?? '-',
                    $row['Grade_Received'] ?? '-'
                ];
            },
            'isLandscape' => false,
        ];
    }

    protected $submissionService;
    public function __construct(SubmissionService $submissionService)
    {
        $this->submissionService = $submissionService;
    }
    public function index(\App\Repositories\GoogleSheets\StudentRepository $studentRepo) {
        $submissions = $this->submissionService->getAll();
        $students = $studentRepo->fetchAll();
        return view('academic.submissions.index', compact('submissions', 'students'));
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
        return redirect()->route('submissions.index')->with('success', 'Submitted!');
    }
    public function show(
        $id,
        \App\Repositories\GoogleSheets\AssignmentRepository $assignmentRepo,
        \App\Repositories\GoogleSheets\StudentRepository $studentRepo
    ) {
        return $this->edit($id, $assignmentRepo, $studentRepo);
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
        $data = $request->except(['_token', '_method']);
        $this->submissionService->update($id, $data);
        
        // Sync to MASTER_SCORE if graded
        if (($data['Status'] ?? '') === 'Graded') {
            $submission = $this->submissionService->getById($id);
            if ($submission) {
                $scoreService = app(\App\Services\Academic\ScoreService::class);
                $existingScore = collect($scoreService->getAll())->firstWhere('Assessment_ID', $submission['Assignment_ID']);
                
                $scoreData = [
                    'Student_ID' => $submission['Student_ID'],
                    'Assessment_ID' => $submission['Assignment_ID'],
                    'Score_Value' => $data['Grade_Received'] ?? 0,
                    'Status' => ($data['Grade_Received'] ?? 0) >= 60 ? 'PASS' : 'FAIL',
                    'Remarks' => 'From Submission',
                    'Created_At' => now()->toDateTimeString()
                ];
                
                if ($existingScore) {
                    $scoreService->update($existingScore['Score_ID'], $scoreData);
                } else {
                    $scoreData['Score_ID'] = 'SCR' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    $scoreService->create($scoreData);
                }
            }
        }
        
        return redirect()->route('submissions.index')->with('success', 'Reviewed!');
    }
    public function destroy($id)
    {
        try {
            $this->submissionService->delete($id);
            return redirect()->route('submissions.index')->with('success', 'Submission berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
