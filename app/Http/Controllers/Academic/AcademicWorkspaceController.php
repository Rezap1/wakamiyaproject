<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\Academic\AssessmentService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\ScheduleService;
use Illuminate\Support\Facades\Cache;

class AcademicWorkspaceController extends Controller
{
    protected $assessmentService;
    protected $scoreService;
    protected $scheduleService;

    public function __construct(
        AssessmentService $assessmentService, 
        ScoreService $scoreService,
        ScheduleService $scheduleService
    ) {
        $this->assessmentService = $assessmentService;
        $this->scoreService = $scoreService;
        $this->scheduleService = $scheduleService;
    }

    public function reports()
    {
        $data = Cache::remember('analytics_dashboard', 60, function() {
            $assessments = $this->assessmentService->getAll();
            $scores = $this->scoreService->getAll();

            $passed = $scores->where('Status', 'PASS')->count();
            $failed = $scores->where('Status', 'FAIL')->count();

            $chartData = [
                'pass_fail' => [$passed, $failed],
                'categories' => $assessments->groupBy('Category')->map->count()->values()->toArray(),
                'category_labels' => $assessments->groupBy('Category')->keys()->toArray(),
            ];

            return compact('assessments', 'scores', 'chartData', 'passed', 'failed');
        });

        return view('academic.admin.reports', $data);
    }

    public function calendar()
    {
        $events = Cache::remember('academic_calendar_events', 60, function() {
            $allSchedules = collect($this->scheduleService->getAll());
            return $allSchedules->map(function($s) {
                return [
                    'date' => $s['Date'] ?? date('Y-m-d'),
                    'title' => ($s['Subject_ID'] ?? 'Class') . ' - ' . ($s['Topic'] ?? ''),
                    'type' => $s['Type'] ?? 'Class'
                ];
            })->values()->toArray();
        });

        return view('academic.admin.calendar', compact('events'));
    }
}
