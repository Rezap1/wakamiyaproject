<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\ScoreService;
use App\Services\Core\ActivityLogService;

class ScoreController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $scores = $this->scoreService->getAll();
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $scores = $scores->filter(function($item) use ($search) {
                return str_contains(strtolower($item['Score_ID'] ?? ''), $search);
            })->values();
        }
        
                $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        
        return [
            'moduleName' => 'Nilai (Score)',
            'data' => collect(array_values($scores->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Nilai', 'Siswa', 'Assessment', 'Nilai', 'Catatan'],
            'mapRow' => function($row) use ($students) {
                $studentId = $row['Student_ID'] ?? null;
                $studentName = $studentId && isset($students[$studentId]) ? $students[$studentId]['Full_Name'] : $studentId;
                
                return [
                    $row['Score_ID'] ?? '-',
                    $studentName . ($studentId ? " ($studentId)" : ''),
                    $row['Assessment_ID'] ?? $row['Assignment_ID'] ?? '-',
                    $row['Score'] ?? $row['Score_Value'] ?? '-',
                    $row['Remarks'] ?? '-'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$scores->count().'</td></tr>'
        ];

    }

    protected $scoreService, $activityLogService;
    public function __construct(ScoreService $scoreService, ActivityLogService $activityLogService)
    {
        $this->scoreService = $scoreService;
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request)
    {
        $scores = $this->scoreService->getAll();
        
        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        
        $scores = $scores->map(function($item) use ($students) {
            $studentId = $item['Student_ID'] ?? null;
            $studentName = $studentId && isset($students[$studentId]) ? $students[$studentId]['Full_Name'] : $studentId;
            $item['Student_Name'] = $studentName;
            $item['Student_Display'] = $studentName . ($studentId ? " ($studentId)" : '');
            return $item;
        });

        if ($request->filled('search')) {
            $search = $request->input('search');
            $scores = \App\Helpers\CollectionHelper::search($scores, $search, ['Score_ID', 'Student_Name', 'Student_ID', 'Assessment_ID', 'Score_Value']);
        }

        $scores = \App\Helpers\CollectionHelper::paginate($scores, 10)->withQueryString();
        
        return view('academic.scores.index', compact('scores'));
    }

    public function create(
        \App\Repositories\GoogleSheets\StudentRepository $studentRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\AssessmentRepository $assessmentRepo
    ) {
        $students = $studentRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $assessments = $assessmentRepo->fetchAll();
        return view('academic.scores.create', compact('students', 'teachers', 'assessments'));
    }

    public function store(\App\Http\Requests\StoreScoreRequest $request)
    {
        try {
            $this->scoreService->create($request->except('_token'));
            $this->activityLogService->log(auth()->id(), 'CREATE_SCORE', 'Score', 'Created score for ' . $request->Student_ID);
            return redirect()->route('scores.index')->with('success', 'Score recorded.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $score = $this->scoreService->getById($id);
        return view('academic.scores.show', compact('score'));
    }

    public function edit(
        $id,
        \App\Repositories\GoogleSheets\StudentRepository $studentRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\AssessmentRepository $assessmentRepo
    ) {
        $score = $this->scoreService->getById($id);
        $students = $studentRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $assessments = $assessmentRepo->fetchAll();
        return view('academic.scores.edit', compact('score', 'students', 'teachers', 'assessments'));
    }

    public function update(\App\Http\Requests\UpdateScoreRequest $request, $id)
    {
        try {
            $this->scoreService->update($id, $request->except(['_token', '_method']));
            $this->activityLogService->log(auth()->id(), 'UPDATE_SCORE', 'Score', 'Updated score ' . $id);
            return redirect()->route('scores.index')->with('success', 'Score updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function exportCSV()
    {
        $scores = $this->scoreService->getAll();
        $csv = "Score_ID,Student_ID,Assessment_ID,Score,Grade,Status\n";
        foreach ($scores as $s) {
            $csv .= ($s['Score_ID']??'').",".($s['Student_ID']??'').",".($s['Assessment_ID'] ?? $s['Assignment_ID'] ?? '').",".($s['Score'] ?? $s['Score_Value'] ?? '').",".($s['Grade']??'').",".($s['Status']??'')."\n";
        }
        return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename="scores.csv"');
    }
}
