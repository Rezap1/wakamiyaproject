<?php
$file = 'app/Providers/AppServiceProvider.php';
$content = file_get_contents($file);

if (strpos($content, 'AuditLogRepositoryInterface') === false) {
    $useStatement = "use App\Interfaces\GoogleSheets\AuditLogRepositoryInterface;\nuse App\Repositories\GoogleSheets\AuditLogRepository;\n";
    $bindStatement = "        \$this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);\n    }\n";
    
    $content = preg_replace('/class AppServiceProvider extends ServiceProvider/', $useStatement . "\nclass AppServiceProvider extends ServiceProvider", $content);
    $content = preg_replace('/}\s+\/\*\*\s+\*\s+Bootstrap any application services/', $bindStatement . "\n    /**\n     * Bootstrap any application services", $content);
    
    file_put_contents($file, $content);
    echo "Audit Bindings added.\n";
} else {
    echo "Audit Bindings already exist.\n";
}

$dirCtrl = 'app/Http/Controllers/Core';
$auditCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\AuditLogService;

class AuditLogController extends Controller
{
    protected $auditService;

    public function __construct(AuditLogService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $logs = $this->auditService->activity()->take(100); // Take 100 for performance
        return view('audit.index', compact('logs'));
    }

    public function show($id)
    {
        $log = $this->auditService->getById($id);
        if(!$log) abort(404);
        return view('audit.show', compact('log'));
    }

    public function statistics()
    {
        $stats = $this->auditService->statistics();
        return view('audit.statistics', compact('stats'));
    }
}
EOT;
file_put_contents("$dirCtrl/AuditLogController.php", $auditCtrl);

$fileRoute = 'routes/web.php';
$routeContent = file_get_contents($fileRoute);

if (strpos($routeContent, 'AuditLogController') === false) {
    $useStatement = "use App\Http\Controllers\Core\AuditLogController;";
    $routeContent = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/', "use Illuminate\Support\Facades\Route;\n" . $useStatement, $routeContent, 1);

    $routes = <<<'EOT'
        // Audit Engine Routes
        Route::get('/audit/statistics', [AuditLogController::class, 'statistics'])->name('audit.statistics');
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit/{id}', [AuditLogController::class, 'show'])->name('audit.show');
EOT;

    $routeContent = str_replace("Route::resource('employees', EmployeeController::class);", $routes . "\n        Route::resource('employees', EmployeeController::class);", $routeContent);
    file_put_contents($fileRoute, $routeContent);
    echo "Audit Routes added.\n";
} else {
    echo "Audit Routes already exist.\n";
}
?>
