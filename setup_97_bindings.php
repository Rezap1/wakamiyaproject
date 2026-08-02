<?php
$file = 'app/Providers/AppServiceProvider.php';
$content = file_get_contents($file);

if (strpos($content, 'ApprovalRepositoryInterface') === false) {
    $useStatement = "use App\Interfaces\GoogleSheets\WorkflowRepositoryInterface;\nuse App\Repositories\GoogleSheets\WorkflowRepository;\nuse App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;\nuse App\Repositories\GoogleSheets\ApprovalRepository;\nuse App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface;\nuse App\Repositories\GoogleSheets\ApprovalHistoryRepository;\n";
    $bindStatement = "\$this->app->bind(WorkflowRepositoryInterface::class, WorkflowRepository::class);\n        \$this->app->bind(ApprovalRepositoryInterface::class, ApprovalRepository::class);\n        \$this->app->bind(ApprovalHistoryRepositoryInterface::class, ApprovalHistoryRepository::class);\n    }\n";
    
    $content = preg_replace('/class AppServiceProvider extends ServiceProvider/', $useStatement . "\nclass AppServiceProvider extends ServiceProvider", $content);
    $content = preg_replace('/}\s+\/\*\*\s+\*\s+Bootstrap any application services/', $bindStatement . "\n    /**\n     * Bootstrap any application services", $content);
    
    file_put_contents($file, $content);
    echo "Workflow Bindings added.\n";
} else {
    echo "Workflow Bindings already exist.\n";
}

$dirCtrl = 'app/Http/Controllers/Core';

$appCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\ApprovalService;
use App\Services\Core\ApprovalHistoryService;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    protected $approvalService;
    protected $historyService;

    public function __construct(ApprovalService $approvalService, ApprovalHistoryService $historyService)
    {
        $this->approvalService = $approvalService;
        $this->historyService = $historyService;
    }

    public function index(Request $request)
    {
        $userEmail = Auth::user()->email ?? 'user@example.com';
        $userRole = session('role') ?? 'GUEST';
        
        $approvals = $this->approvalService->getAll()
            ->filter(function($a) use ($userRole) {
                return ($a['Current_Approver'] ?? '') == $userRole && ($a['Status'] ?? '') === 'Waiting Approval';
            })->sortByDesc('Submitted_At');

        return view('workflow.index', compact('approvals'));
    }

    public function show($id)
    {
        $approval = $this->approvalService->getById($id);
        if (!$approval) abort(404);
        
        $history = $this->historyService->history($id);

        return view('workflow.show', compact('approval', 'history'));
    }

    public function approve(Request $request, $id)
    {
        $userEmail = Auth::user()->email ?? 'user@example.com';
        $remarks = $request->input('remarks', '');
        
        $this->approvalService->approve($id, $userEmail, $remarks);
        return redirect()->route('approvals.index')->with('success', 'Request Approved.');
    }

    public function reject(Request $request, $id)
    {
        $userEmail = Auth::user()->email ?? 'user@example.com';
        $remarks = $request->input('remarks', '');
        
        $this->approvalService->reject($id, $userEmail, $remarks);
        return redirect()->route('approvals.index')->with('danger', 'Request Rejected.');
    }
}
EOT;
file_put_contents("$dirCtrl/ApprovalController.php", $appCtrl);

$fileRoute = 'routes/web.php';
$routeContent = file_get_contents($fileRoute);

if (strpos($routeContent, 'ApprovalController') === false) {
    $useStatement = "use App\Http\Controllers\Core\ApprovalController;";
    $routeContent = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/', "use Illuminate\Support\Facades\Route;\n" . $useStatement, $routeContent, 1);

    $routes = <<<'EOT'
        // Approval Engine Routes
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/approvals/{id}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
EOT;

    $routeContent = str_replace("Route::resource('employees', EmployeeController::class);", $routes . "\n        Route::resource('employees', EmployeeController::class);", $routeContent);
    file_put_contents($fileRoute, $routeContent);
    echo "Approval Routes added.\n";
} else {
    echo "Approval Routes already exist.\n";
}
?>
