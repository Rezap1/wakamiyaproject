<?php

// 1. Update AssessmentRepository
$assessmentRepoPath = 'app/Repositories/GoogleSheets/AssessmentRepository.php';
$assessmentRepoContent = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;

class AssessmentRepository extends BaseSheetRepository implements AssessmentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'ASSESSMENTS';
        $this->cacheKey = 'assessments_sheet';
        $this->primaryKey = 'Assessment_ID';
    }

    public function getAll()
    {
        return $this->fetchAll();
    }

    public function getById($id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function create(array $data)
    {
        if (empty($data['Assessment_ID'])) {
            $data['Assessment_ID'] = $this->generateNewId('ASM', 6);
        }
        return $this->append($data);
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    public function delete($id)
    {
        return $this->updateRow($id, ['Status' => 'Archived']);
    }
}
EOT;
file_put_contents($assessmentRepoPath, $assessmentRepoContent);

// 2. Update AssessmentController
$assessmentControllerPath = 'app/Http/Controllers/Academic/AssessmentController.php';
$assessmentControllerContent = <<<'EOT'
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\AssessmentService;
use App\Services\Core\ActivityLogService;

class AssessmentController extends Controller
{
    protected $assessmentService, $activityLogService;

    public function __construct(AssessmentService $assessmentService, ActivityLogService $activityLogService)
    {
        $this->assessmentService = $assessmentService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        $assessments = $this->assessmentService->getAll();
        return view('academic.assessments.index', compact('assessments'));
    }

    public function create(
        \App\Repositories\GoogleSheets\ProgramRepository $programRepo, 
        \App\Repositories\GoogleSheets\BatchRepository $batchRepo, 
        \App\Repositories\GoogleSheets\ClassRepository $classRepo, 
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo
    ) {
        $programs = $programRepo->fetchAll();
        $batches = $batchRepo->fetchAll();
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        return view('academic.assessments.create', compact('programs', 'batches', 'classes', 'teachers', 'subjects'));
    }

    public function store(Request $request)
    {
        try {
            $this->assessmentService->create($request->except('_token'));
            $this->activityLogService->log(auth()->id(), 'CREATE_ASSESSMENT', 'Assessment', 'Created assessment');
            return redirect()->route('assessments.index')->with('success', 'Assessment created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $assessment = $this->assessmentService->getById($id);
        return view('academic.assessments.show', compact('assessment'));
    }

    public function edit(
        $id,
        \App\Repositories\GoogleSheets\ProgramRepository $programRepo, 
        \App\Repositories\GoogleSheets\BatchRepository $batchRepo, 
        \App\Repositories\GoogleSheets\ClassRepository $classRepo, 
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo
    ) {
        $assessment = $this->assessmentService->getById($id);
        $programs = $programRepo->fetchAll();
        $batches = $batchRepo->fetchAll();
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        return view('academic.assessments.edit', compact('assessment', 'programs', 'batches', 'classes', 'teachers', 'subjects'));
    }

    public function update(Request $request, $id)
    {
        try {
            $this->assessmentService->update($id, $request->except(['_token', '_method']));
            $this->activityLogService->log(auth()->id(), 'UPDATE_ASSESSMENT', 'Assessment', 'Updated assessment ' . $id);
            return redirect()->route('assessments.index')->with('success', 'Assessment updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->assessmentService->delete($id);
            $this->activityLogService->log(auth()->id(), 'DELETE_ASSESSMENT', 'Assessment', 'Deleted assessment ' . $id);
            return redirect()->route('assessments.index')->with('success', 'Assessment deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
EOT;
file_put_contents($assessmentControllerPath, $assessmentControllerContent);

// 3. Update ScoreService
$scoreServicePath = 'app/Services/Academic/ScoreService.php';
$scoreServiceContent = <<<'EOT'
<?php
namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Services\Core\NotificationService;
use Exception;

class ScoreService
{
    protected $repository, $notificationService;

    public function __construct(
        ScoreRepositoryInterface $repository, 
        NotificationService $notificationService
    ) {
        $this->repository = $repository;
        $this->notificationService = $notificationService;
    }

    public function getAll() { return $this->repository->fetchAll(); }
    public function getById($id) { return $this->repository->findById($id); }
    public function generateId() { return $this->repository->generateNewId('SCR', 6); }

    public function validateScore(array $data)
    {
        $val = (float) ($data['Score_Value'] ?? 0);
        if ($val < 0 || $val > 100) throw new Exception("Score must be between 0 and 100.");
    }

    public function create(array $data)
    {
        $this->validateScore($data);
        if (!isset($data['Score_ID'])) $data['Score_ID'] = $this->generateId();
        
        $gradeResult = \App\Helpers\GradeHelper::calculate($data['Score_Value'] ?? 0);
        $data['Grade'] = $gradeResult['grade'];
        $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        $data['Created_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        $this->repository->clearCache();
        
        // Notify Student
        $this->notificationService->notifyUser($data['Student_ID'], 'New Score Published', 'Your score has been published.');
        
        return $res;
    }

    public function update($id, array $data)
    {
        $this->validateScore($data);
        if (isset($data['Score_Value'])) {
            $gradeResult = \App\Helpers\GradeHelper::calculate($data['Score_Value']);
            $data['Grade'] = $gradeResult['grade'];
            $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        }
        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        $this->repository->clearCache();
        return $res;
    }
}
EOT;
file_put_contents($scoreServicePath, $scoreServiceContent);

// 4. Update ScoreController
$scoreControllerPath = 'app/Http/Controllers/Academic/ScoreController.php';
$scoreControllerContent = <<<'EOT'
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\ScoreService;
use App\Services\Core\ActivityLogService;

class ScoreController extends Controller
{
    protected $scoreService, $activityLogService;
    public function __construct(ScoreService $scoreService, ActivityLogService $activityLogService)
    {
        $this->scoreService = $scoreService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        $scores = $this->scoreService->getAll();
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

    public function store(Request $request)
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

    public function update(Request $request, $id)
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
        $csv = "Score_ID,Student_ID,Assessment_ID,Score_Value,Grade,Status\n";
        foreach ($scores as $s) {
            $csv .= ($s['Score_ID']??'').",".($s['Student_ID']??'').",".($s['Assessment_ID']??'').",".($s['Score_Value']??'').",".($s['Grade']??'').",".($s['Status']??'')."\n";
        }
        return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename="scores.csv"');
    }
}
EOT;
file_put_contents($scoreControllerPath, $scoreControllerContent);

echo "Backend files updated successfully.\n";
?>
