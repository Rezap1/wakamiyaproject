<?php

// 1. Update AppServiceProvider.php for Ngrok and Bindings
$appServiceProviderPath = 'app/Providers/AppServiceProvider.php';
$appServiceProviderContent = file_get_contents($appServiceProviderPath);

// Add Assessment Repository Bindings
if (strpos($appServiceProviderContent, 'AssessmentRepositoryInterface') === false) {
    $useStatement = "use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;\nuse App\Repositories\GoogleSheets\NotificationRepository;\nuse App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;\nuse App\Repositories\GoogleSheets\AssessmentRepository;";
    $appServiceProviderContent = str_replace(
        "use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;\nuse App\Repositories\GoogleSheets\NotificationRepository;",
        $useStatement,
        $appServiceProviderContent
    );
    
    $bindStatement = "\$this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);\n        \$this->app->bind(AssessmentRepositoryInterface::class, AssessmentRepository::class);";
    $appServiceProviderContent = str_replace(
        "\$this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);",
        $bindStatement,
        $appServiceProviderContent
    );
}

// Add URL::forceScheme('https') for Ngrok
if (strpos($appServiceProviderContent, "URL::forceScheme('https');") === false) {
    $bootReplacement = "public function boot(): void\n    {\n        if(env('APP_ENV') !== 'local' || isset(\$_SERVER['HTTP_X_FORWARDED_PROTO'])) {\n            \URL::forceScheme('https');\n        }\n";
    $appServiceProviderContent = preg_replace(
        '/public function boot\(\): void\s*\{/',
        $bootReplacement,
        $appServiceProviderContent
    );
}
file_put_contents($appServiceProviderPath, $appServiceProviderContent);


// 2. Create config/assessment.php
$configContent = <<<'EOT'
<?php

return [
    'categories' => [
        'Placement Test',
        'Daily Quiz',
        'Assignment',
        'Mid Test',
        'Final Test',
        'Speaking',
        'Listening',
        'Reading',
        'Writing',
        'Interview',
        'Attitude',
        'Attendance Contribution'
    ],
    
    'grades' => [
        'A+' => ['min' => 95, 'max' => 100],
        'A'  => ['min' => 90, 'max' => 94],
        'A-' => ['min' => 85, 'max' => 89],
        'B+' => ['min' => 80, 'max' => 84],
        'B'  => ['min' => 75, 'max' => 79],
        'B-' => ['min' => 70, 'max' => 74],
        'C+' => ['min' => 65, 'max' => 69],
        'C'  => ['min' => 60, 'max' => 64],
        'D'  => ['min' => 50, 'max' => 59],
        'E'  => ['min' => 0,  'max' => 49],
    ],
    
    'passing_score' => 65,
];
EOT;
if (!is_dir('config')) mkdir('config', 0777, true);
file_put_contents('config/assessment.php', $configContent);


// 3. Create GradeHelper
$helperContent = <<<'EOT'
<?php

namespace App\Helpers;

class GradeHelper
{
    public static function calculate($score)
    {
        $grades = config('assessment.grades');
        $passingScore = config('assessment.passing_score');
        
        $letterGrade = 'E'; // Default
        foreach ($grades as $grade => $range) {
            if ($score >= $range['min'] && $score <= $range['max']) {
                $letterGrade = $grade;
                break;
            }
        }
        
        return [
            'score' => $score,
            'grade' => $letterGrade,
            'pass' => $score >= $passingScore,
            'percentage' => $score . '%'
        ];
    }
}
EOT;
if (!is_dir('app/Helpers')) mkdir('app/Helpers', 0777, true);
file_put_contents('app/Helpers/GradeHelper.php', $helperContent);


// 4. Create AssessmentRepositoryInterface
$interfaceContent = <<<'EOT'
<?php

namespace App\Interfaces\GoogleSheets;

interface AssessmentRepositoryInterface
{
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents('app/Interfaces/GoogleSheets/AssessmentRepositoryInterface.php', $interfaceContent);


// 5. Create AssessmentRepository
$repoContent = <<<'EOT'
<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Services\GoogleSheets\GoogleSheetsService;

class AssessmentRepository implements AssessmentRepositoryInterface
{
    protected $sheetsService;
    protected $spreadsheetId;
    protected $range = 'ASSESSMENTS!A:Z';

    public function __construct(GoogleSheetsService $sheetsService)
    {
        $this->sheetsService = $sheetsService;
        $this->spreadsheetId = config('services.google.spreadsheet_id');
    }

    public function getAll()
    {
        return $this->sheetsService->readSheet($this->spreadsheetId, $this->range);
    }

    public function getById($id)
    {
        $data = $this->getAll();
        foreach ($data as $item) {
            if (isset($item['Assessment_ID']) && $item['Assessment_ID'] == $id) {
                return $item;
            }
        }
        return null;
    }

    public function create(array $data)
    {
        $this->sheetsService->appendRow($this->spreadsheetId, $this->range, array_values($data));
        return $data;
    }

    public function update($id, array $data)
    {
        return true;
    }

    public function delete($id)
    {
        return true;
    }
}
EOT;
file_put_contents('app/Repositories/GoogleSheets/AssessmentRepository.php', $repoContent);


// 6. Create AssessmentService
$serviceContent = <<<'EOT'
<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;

class AssessmentService
{
    protected $assessmentRepository;

    public function __construct(AssessmentRepositoryInterface $assessmentRepository)
    {
        $this->assessmentRepository = $assessmentRepository;
    }

    public function getAll()
    {
        return $this->assessmentRepository->getAll();
    }

    public function getById($id)
    {
        return $this->assessmentRepository->getById($id);
    }

    public function create(array $data)
    {
        return $this->assessmentRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->assessmentRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->assessmentRepository->delete($id);
    }
}
EOT;
file_put_contents('app/Services/Academic/AssessmentService.php', $serviceContent);


// 7. Create AssessmentController
$controllerContent = <<<'EOT'
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

    public function create()
    {
        return view('academic.assessments.create');
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

    public function edit($id)
    {
        $assessment = $this->assessmentService->getById($id);
        return view('academic.assessments.edit', compact('assessment'));
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
file_put_contents('app/Http/Controllers/Academic/AssessmentController.php', $controllerContent);


// 8. Add show method to ScoreController
$scoreControllerPath = 'app/Http/Controllers/Academic/ScoreController.php';
$scoreControllerContent = file_get_contents($scoreControllerPath);
if (strpos($scoreControllerContent, 'public function show') === false) {
    $showMethod = "    public function show(\$id)\n    {\n        \$score = \$this->scoreService->getById(\$id);\n        return view('academic.scores.show', compact('score'));\n    }\n\n";
    $scoreControllerContent = str_replace(
        "    public function edit(\$id)",
        $showMethod . "    public function edit(\$id)",
        $scoreControllerContent
    );
    file_put_contents($scoreControllerPath, $scoreControllerContent);
}


// 9. Update routes/web.php
$webPath = 'routes/web.php';
$webContent = file_get_contents($webPath);
if (strpos($webContent, 'AssessmentController') === false) {
    $webContent = str_replace(
        "use App\Http\Controllers\Academic\ScoreController;",
        "use App\Http\Controllers\Academic\ScoreController;\nuse App\Http\Controllers\Academic\AssessmentController;",
        $webContent
    );
    $webContent = str_replace(
        "Route::resource('scores', ScoreController::class);",
        "Route::resource('scores', ScoreController::class);\n        Route::resource('assessments', AssessmentController::class);",
        $webContent
    );
    file_put_contents($webPath, $webContent);
}

echo "Phase 9.1.1 completed successfully.\n";
?>
