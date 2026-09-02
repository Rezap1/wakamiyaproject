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
        $categories = [
            'General', 'Branding', 'Company', 'Company_Document',
            'Notification', 'Email_Delivery', 'Security', 'Workflow', 'System'
        ];
        $activeTab = $request->query('tab', 'General');
        
        $settings = $this->settingService->category($activeTab);
        $parameters = $this->settingService->getParameters()->where('Module', $activeTab);
        $emailConfig = $this->settingService->getEmailDeliveryConfig();

        return view('system.settings.index', compact('categories', 'activeTab', 'settings', 'parameters', 'emailConfig'));
    }

    public function sendTestEmail(Request $request)
    {
        $recipient = trim($request->input('recipient', ''));
        if (empty($recipient)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat email penerima tidak boleh kosong.'
            ], 422);
        }

        $emailDeliveryService = app(\App\Services\Core\EmailDeliveryService::class);
        $result = $emailDeliveryService->sendTestEmail($recipient);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result, 200);
    }

    public function clearCache(Request $request)
    {
        try {
            $this->settingService->reloadCache();
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            return redirect()->back()->with('success', 'Cache pengaturan sistem & tampilan Blade berhasil dibersihkan (Invalidated & Reloaded).');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Gagal membersihkan cache: ' . $this->safeExceptionMessage($e)]);
        }
    }

    public function resetBranding(Request $request)
    {
        $userEmail = $this->authenticatedActor();
        $defaultBrandings = [
            'BRAND_PRIMARY_COLOR' => '#38BDF8',
            'BRAND_SECONDARY_COLOR' => '#0F172A',
            'BRAND_ACCENT_COLOR' => '#0EA5E9',
            'BRAND_SIDEBAR_BG' => '#111827',
            'BRAND_SIDEBAR_TEXT' => '#94A3B8',
            'BRAND_SIDEBAR_ACTIVE_BG' => '#1E293B',
            'BRAND_SIDEBAR_ACTIVE_TEXT' => '#38BDF8',
            'BRAND_TOPBAR_BG' => '#111827',
            'BRAND_TOPBAR_TEXT' => '#F8FAFC',
            'BRAND_THEME_MODE' => 'dark',
        ];

        foreach ($defaultBrandings as $key => $val) {
            $this->settingService->set($key, $val, $userEmail);
        }
        $this->settingService->reloadCache();

        return redirect()->route('settings.index', ['tab' => 'Branding'])->with('success', 'Skema warna branding berhasil dikembalikan ke Wakamiya Brand Palette resmi (#111827 Navy & #38BDF8 Sky Blue).');
    }

    public function update(Request $request)
    {
        $userEmail = $this->authenticatedActor();
        $activeTab = $request->input('active_tab', 'General');
        
        $settingsData = $request->input('settings', []);
        $parametersData = $request->input('parameters', []);

        [$settingsData, $validationErrors] = $this->settingService->prepareSettingsForUpdate($settingsData);
        if (!empty($validationErrors)) {
            return back()->withErrors(['error' => implode(' ', $validationErrors)])->withInput();
        }

        $changes = 0;

        // Handle file uploads for settings with Value_Type = 'file'
        if ($request->hasFile('setting_files')) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $maxSize = (int) config('upload.max_bytes', 5 * 1024 * 1024);

            foreach ($request->file('setting_files') as $settingId => $file) {
                if (!$file->isValid()) {
                    return back()->withErrors(['error' => "File untuk {$settingId} gagal diunggah."])->withInput();
                }

                // Validate file type (security: no executables)
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    return back()->withErrors(['error' => "File untuk {$settingId} harus berupa JPG, PNG, atau WEBP."])->withInput();
                }

                // Validate file size
                if ($file->getSize() > $maxSize) {
                    return back()->withErrors(['error' => "File untuk {$settingId} maksimal 5MB."])->withInput();
                }

                // Determine storage subdirectory based on setting key
                $setting = $this->settingService->getSettings()->firstWhere('Setting_ID', $settingId);
                if (!$setting || strtolower(trim((string) ($setting['Value_Type'] ?? ''))) !== 'file') {
                    return back()->withErrors(['error' => "Pengaturan file {$settingId} tidak valid."])->withInput();
                }

                $settingKey = $setting['Setting_Key'] ?? $settingId;
                
                $subDir = 'companies/documents';
                if (str_contains($settingKey, 'LOGO')) {
                    $subDir = 'companies/logos';
                } elseif (str_contains($settingKey, 'STAMP')) {
                    $subDir = 'companies/stamps';
                } elseif (str_contains($settingKey, 'SIGNATURE')) {
                    $subDir = 'companies/signatures';
                }

                $safeName = strtolower((string) str_replace(['SET_', 'COMP_', 'DOC_'], '', $settingId));
                $safeName = preg_replace('/[^a-z0-9_-]/', '_', $safeName) ?: 'setting_file';
                $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
                $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
                $filename = $safeName . '_' . time() . '.' . $extension;
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

        $this->settingService->reloadCache();
        try { \Illuminate\Support\Facades\Artisan::call('view:clear'); } catch (\Throwable $e) {}

        return redirect()->route('settings.index', ['tab' => $activeTab])->with('success', "$changes pengaturan berhasil diperbarui.");
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
