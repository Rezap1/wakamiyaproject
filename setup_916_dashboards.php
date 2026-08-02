<?php

$studentDashCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Academic\ScoreService;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    protected $scoreService;

    public function __construct(ScoreService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function index()
    {
        // 1. Fetch real scores for logged in student
        $studentId = auth()->user()->id ?? 'STU-001';
        $allScores = $this->scoreService->getAll();
        
        $myScores = $allScores->filter(function($s) use ($studentId) {
            return ($s['Student_ID'] ?? '') == $studentId;
        });

        // 2. Real Calculation of progress
        $langProgress = 0;
        $jftScore = $myScores->firstWhere('Assessment_ID', 'JFT-001');
        if ($jftScore) {
            $langProgress = ($jftScore['Score_Value'] ?? 0) >= config('assessment.passing_score', 65) ? 100 : 50;
        }

        $internals = $myScores->map(function($s) {
            return [
                'name' => $s['Assessment_ID'],
                'status' => $s['Status'] ?? 'Completed',
                'score' => $s['Score_Value'],
                'color' => ($s['Status'] ?? '') == 'PASS' ? 'emerald' : 'red'
            ];
        })->take(8)->toArray();

        return view('dashboard.student', compact('myScores', 'langProgress', 'internals'));
    }
}
EOT;
file_put_contents('app/Http/Controllers/Core/StudentDashboardController.php', $studentDashCtrl);


$academicDashCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Academic\AssessmentService;
use App\Services\Academic\ScoreService;

class AcademicDashboardController extends Controller
{
    protected $assessmentService, $scoreService;

    public function __construct(AssessmentService $assessmentService, ScoreService $scoreService)
    {
        $this->assessmentService = $assessmentService;
        $this->scoreService = $scoreService;
    }

    public function index()
    {
        $assessments = $this->assessmentService->getAll();
        $scores = $this->scoreService->getAll();

        $totalScore = $scores->sum('Score_Value');
        $avgScore = $scores->count() > 0 ? round($totalScore / $scores->count(), 1) : 0;
        
        $passed = $scores->where('Status', 'PASS')->count();
        $passingRate = $scores->count() > 0 ? round(($passed / $scores->count()) * 100) : 0;

        $kpi = [
            'programs' => 4,
            'classes' => 12,
            'teachers' => 25,
            'students' => 350,
            'attendance_rate' => '94%',
            'average_score' => $avgScore,
            'submission_rate' => $passingRate . '% (Passing)',
            'total_assessments' => $assessments->count(),
            'total_scores' => $scores->count()
        ];

        $todayClasses = [];
        $pendingReviews = [];
        $activities = [];

        return view('dashboard.academic', compact('kpi', 'todayClasses', 'pendingReviews', 'activities'));
    }
}
EOT;
file_put_contents('app/Http/Controllers/Core/AcademicDashboardController.php', $academicDashCtrl);


$academicWorkspaceCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\Academic\AssessmentService;
use App\Services\Academic\ScoreService;
use Illuminate\Http\Request;

class AcademicWorkspaceController extends Controller
{
    protected $assessmentService, $scoreService;

    public function __construct(AssessmentService $assessmentService, ScoreService $scoreService)
    {
        $this->assessmentService = $assessmentService;
        $this->scoreService = $scoreService;
    }

    public function reports()
    {
        $assessments = $this->assessmentService->getAll();
        $scores = $this->scoreService->getAll();

        $passed = $scores->where('Status', 'PASS')->count();
        $failed = $scores->where('Status', 'FAIL')->count();

        $chartData = [
            'pass_fail' => [$passed, $failed],
            'categories' => $assessments->groupBy('Category')->map->count()->values()->toArray(),
            'category_labels' => $assessments->groupBy('Category')->keys()->toArray(),
        ];

        return view('academic.admin.reports', compact('assessments', 'scores', 'chartData', 'passed', 'failed'));
    }

    public function calendar()
    {
        $events = [];
        return view('academic.admin.calendar', compact('events'));
    }
}
EOT;
file_put_contents('app/Http/Controllers/Academic/AcademicWorkspaceController.php', $academicWorkspaceCtrl);

echo "Dashboard Controllers updated.\n";
?>
