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
        $categories = ['General', 'Company', 'Company_Bank', 'Company_Document', 'Academic', 'Finance', 'Payroll', 'Attendance', 'Assessment', 'Notification', 'Workflow', 'Document', 'Security', 'System'];
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

        // Handle file uploads for settings with Value_Type = 'file'
        if ($request->hasFile('setting_files')) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            foreach ($request->file('setting_files') as $settingId => $file) {
                if (!$file->isValid()) {
                    continue;
                }

                // Validate file type (security: no executables)
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    continue;
                }

                // Validate file size
                if ($file->getSize() > $maxSize) {
                    continue;
                }

                // Determine storage subdirectory based on setting key
                $setting = $this->settingService->getSettings()->firstWhere('Setting_ID', $settingId);
                $settingKey = $setting['Setting_Key'] ?? $settingId;
                
                $subDir = 'companies/documents';
                if (str_contains($settingKey, 'LOGO')) {
                    $subDir = 'companies/logos';
                } elseif (str_contains($settingKey, 'STAMP')) {
                    $subDir = 'companies/stamps';
                } elseif (str_contains($settingKey, 'SIGNATURE')) {
                    $subDir = 'companies/signatures';
                }

                $filename = strtolower(str_replace(['SET_', 'COMP_', 'DOC_'], '', $settingId)) . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs($subDir, $filename, 'public');
                $storedPath = 'storage/' . $subDir . '/' . $filename;

                // Update the setting value with the new file path
                if ($this->settingService->set($settingId, $storedPath, $userEmail)) {
                    $changes++;
                    try { $this->auditService->log('System_Settings', 'Upload_File', 'Setting', $settingId, null, $storedPath); } catch(\Exception $e) {}
                }
            }
        }

        // Handle text/number settings
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

        return redirect()->route('settings.index', ['tab' => $activeTab])->with('success', "$changes pengaturan berhasil diperbarui.");
    }
}
