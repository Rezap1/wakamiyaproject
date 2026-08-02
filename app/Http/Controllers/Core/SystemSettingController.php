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
