<?php
$file = 'app/Providers/AppServiceProvider.php';
$content = file_get_contents($file);

if (strpos($content, 'SystemSettingRepositoryInterface') === false) {
    $useStatement = "use App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface;\nuse App\Repositories\GoogleSheets\SystemSettingRepository;\nuse App\Interfaces\GoogleSheets\SystemParameterRepositoryInterface;\nuse App\Repositories\GoogleSheets\SystemParameterRepository;\n";
    $bindStatement = "        \$this->app->bind(SystemSettingRepositoryInterface::class, SystemSettingRepository::class);\n        \$this->app->bind(SystemParameterRepositoryInterface::class, SystemParameterRepository::class);\n    }\n";
    
    $content = preg_replace('/class AppServiceProvider extends ServiceProvider/', $useStatement . "\nclass AppServiceProvider extends ServiceProvider", $content);
    $content = preg_replace('/}\s+\/\*\*\s+\*\s+Bootstrap any application services/', $bindStatement . "\n    /**\n     * Bootstrap any application services", $content);
    
    file_put_contents($file, $content);
    echo "Settings Bindings added.\n";
} else {
    echo "Settings Bindings already exist.\n";
}

$dirCtrl = 'app/Http/Controllers/Core';
$setCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SystemSettingService;
use App\Services\Core\AuditLogService;
use Illuminate\Support\Facades\Auth;

class SystemSettingController extends Controller
{
    protected $settingService;
    protected $auditService;

    public function __construct(SystemSettingService $settingService, AuditLogService $auditService)
    {
        $this->settingService = $settingService;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $categories = ['General', 'Company', 'Academic', 'Finance', 'Payroll', 'Attendance', 'Assessment', 'Notification', 'Workflow', 'Document', 'Security', 'System'];
        $activeTab = $request->query('tab', 'General');
        
        $settings = $this->settingService->category($activeTab);
        $parameters = $this->settingService->getParameters()->where('Module', $activeTab);

        return view('system.settings.index', compact('categories', 'activeTab', 'settings', 'parameters'));
    }

    public function update(Request $request)
    {
        $userEmail = Auth::user()->email ?? 'System';
        $activeTab = $request->input('active_tab', 'General');
        
        $settingsData = $request->input('settings', []);
        $parametersData = $request->input('parameters', []);
        
        $changes = 0;

        foreach($settingsData as $id => $value) {
            if($this->settingService->set($id, $value, $userEmail)) {
                $changes++;
                try { $this->auditService->log('System_Settings', 'Update_Setting', 'Setting', $id, null, $value); } catch(\Exception $e) {}
            }
        }

        foreach($parametersData as $id => $value) {
            if($this->settingService->updateParameter($id, $value)) {
                $changes++;
                try { $this->auditService->log('System_Settings', 'Update_Parameter', 'Parameter', $id, null, $value); } catch(\Exception $e) {}
            }
        }

        return redirect()->route('settings.index', ['tab' => $activeTab])->with('success', "$changes settings updated successfully.");
    }
}
EOT;
file_put_contents("$dirCtrl/SystemSettingController.php", $setCtrl);

$fileRoute = 'routes/web.php';
$routeContent = file_get_contents($fileRoute);

if (strpos($routeContent, 'SystemSettingController') === false) {
    $useStatement = "use App\Http\Controllers\Core\SystemSettingController;";
    $routeContent = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/', "use Illuminate\Support\Facades\Route;\n" . $useStatement, $routeContent, 1);

    $routes = <<<'EOT'
        // System Settings Routes
        Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings/update', [SystemSettingController::class, 'update'])->name('settings.update');
EOT;

    $routeContent = str_replace("Route::resource('employees', EmployeeController::class);", $routes . "\n        Route::resource('employees', EmployeeController::class);", $routeContent);
    file_put_contents($fileRoute, $routeContent);
    echo "Settings Routes added.\n";
} else {
    echo "Settings Routes already exist.\n";
}
?>
