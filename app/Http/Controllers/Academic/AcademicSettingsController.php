<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SystemSettingService;
use App\Services\Core\AuditLogService;
use Illuminate\Support\Facades\Auth;

class AcademicSettingsController extends Controller
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
        $tabs = [
            'Academic' => ['label' => 'Akademik', 'icon' => '🎓'],
            'Assessment' => ['label' => 'Penilaian', 'icon' => '📊'],
        ];
        $activeTab = $request->query('tab', 'Academic');

        if (!array_key_exists($activeTab, $tabs)) {
            $activeTab = 'Academic';
        }

        $settings = $this->settingService->category($activeTab);
        $parameters = $this->settingService->getParameters()->where('Module', $activeTab);

        return view('academic.settings.index', compact('tabs', 'activeTab', 'settings', 'parameters'));
    }

    public function update(Request $request)
    {
        $userEmail = $this->authenticatedActor();
        $activeTab = $request->input('active_tab', 'Academic');

        $settingsData = $request->input('settings', []);
        $parametersData = $request->input('parameters', []);

        [$settingsData, $validationErrors] = $this->settingService->prepareSettingsForUpdate($settingsData);
        if (!empty($validationErrors)) {
            return back()->withErrors(['error' => implode(' ', $validationErrors)])->withInput();
        }

        $changes = 0;

        foreach ($settingsData as $id => $value) {
            if ($this->settingService->set($id, $value, $userEmail)) {
                $changes++;
                try { $this->auditService->log('Academic_Settings', 'Update_Setting', 'Setting', $id, null, $value); } catch (\Exception $e) {}
            }
        }

        foreach ($parametersData as $id => $value) {
            if ($this->settingService->updateParameter($id, $value)) {
                $changes++;
                try { $this->auditService->log('Academic_Settings', 'Update_Parameter', 'Parameter', $id, null, $value); } catch (\Exception $e) {}
            }
        }

        $this->settingService->reloadCache();

        return redirect()->route('academic.settings.index', ['tab' => $activeTab])->with('success', "$changes pengaturan akademik berhasil diperbarui.");
    }

    private function authenticatedActor(): string
    {
        $user = Auth::user();
        $actor = $user->User_ID ?? $user->Email ?? $user->email ?? null;
        if (!$actor) {
            abort(403, 'Identitas pengguna tidak valid.');
        }

        return (string) $actor;
    }
}
