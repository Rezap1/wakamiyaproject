<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SystemSettingService;
use App\Services\Core\AuditLogService;
use Illuminate\Support\Facades\Auth;

class FinanceSettingsController extends Controller
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
            'Finance' => ['label' => 'Keuangan', 'icon' => '💰'],
            'Company_Bank' => ['label' => 'Rekening Bank', 'icon' => '🏦'],
        ];
        $activeTab = $request->query('tab', 'Finance');

        if (!array_key_exists($activeTab, $tabs)) {
            $activeTab = 'Finance';
        }

        $settings = $this->settingService->category($activeTab);
        $parameters = $this->settingService->getParameters()->where('Module', $activeTab);

        return view('finance.settings.index', compact('tabs', 'activeTab', 'settings', 'parameters'));
    }

    public function update(Request $request)
    {
        $userEmail = $this->authenticatedActor();
        $activeTab = $request->input('active_tab', 'Finance');

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
                try { $this->auditService->log('Finance_Settings', 'Update_Setting', 'Setting', $id, null, $value); } catch (\Exception $e) {}
            }
        }

        foreach ($parametersData as $id => $value) {
            if ($this->settingService->updateParameter($id, $value)) {
                $changes++;
                try { $this->auditService->log('Finance_Settings', 'Update_Parameter', 'Parameter', $id, null, $value); } catch (\Exception $e) {}
            }
        }

        $this->settingService->reloadCache();

        return redirect()->route('finance.settings.index', ['tab' => $activeTab])->with('success', "$changes pengaturan finance berhasil diperbarui.");
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
