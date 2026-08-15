<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface;
use App\Interfaces\GoogleSheets\SystemParameterRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class SystemSettingService
{
    protected $settingRepo;
    protected $paramRepo;

    public function __construct(SystemSettingRepositoryInterface $settingRepo, SystemParameterRepositoryInterface $paramRepo)
    {
        $this->settingRepo = $settingRepo;
        $this->paramRepo = $paramRepo;
    }

    public function getSettings() {
        return Cache::rememberForever('system_settings_all', function() {
            return $this->settingRepo->getAll();
        });
    }

    public function getParameters() {
        return Cache::rememberForever('system_parameters_all', function() {
            return $this->paramRepo->getAll();
        });
    }

    public function get($key, $default = null) {
        $setting = $this->getSettings()->firstWhere('Setting_Key', $key);
        if ($setting && isset($setting['Setting_Value']) && $setting['Setting_Value'] !== '') {
            return $setting['Setting_Value'];
        }
        return $default;
    }

    public function parameter($module, $key, $default = null) {
        $param = $this->getParameters()->where('Module', $module)->firstWhere('Parameter_Key', $key);
        if ($param && isset($param['Parameter_Value']) && $param['Parameter_Value'] !== '') {
            return $param['Parameter_Value'];
        }
        return $default;
    }

    public function category($category) {
        return $this->getSettings()->where('Category', $category);
    }

    public function set($id, $value, $updaterEmail) {
        $setting = $this->settingRepo->getById($id);
        if($setting) {
            if (isset($setting['Setting_Value']) && (string)$setting['Setting_Value'] === (string)$value) {
                return true;
            }
            $setting['Setting_Value'] = $value;
            $setting['Updated_By'] = $updaterEmail;
            $setting['Updated_At'] = now()->toDateTimeString();
            $this->settingRepo->update($id, $setting);
            $this->reloadCache();
            return true;
        }
        return false;
    }

    public function updateParameter($id, $value) {
        $param = $this->paramRepo->getById($id);
        if($param) {
            if (isset($param['Parameter_Value']) && (string)$param['Parameter_Value'] === (string)$value) {
                return true;
            }
            $param['Parameter_Value'] = $value;
            $param['Updated_At'] = now()->toDateTimeString();
            $this->paramRepo->update($id, $param);
            $this->reloadCache();
            return true;
        }
        return false;
    }

    public function clearCache() {
        Cache::forget('system_settings_all');
        Cache::forget('system_parameters_all');
        $this->settingRepo->clearCache();
        $this->paramRepo->clearCache();
    }

    public function reloadCache() {
        $this->clearCache();
        $this->getSettings();
        $this->getParameters();
    }

    public function getInvoiceCategories() {
        $settings = $this->getSettings();
        $categorySetting = $settings->firstWhere('Setting_Name', 'INVOICE_CATEGORIES');
        if ($categorySetting && !empty($categorySetting['Setting_Value'])) {
            return array_map('trim', explode(',', $categorySetting['Setting_Value']));
        }
        return ['Pendidikan', 'Medical', 'JFT', 'JLPT', 'Dormitory', 'Air Ticket', 'Administration', 'SSW', 'Equipment', 'Other'];
    }

    public function getDefaultTuitionFee() {
        $settings = $this->getSettings();
        $feeSetting = $settings->firstWhere('Setting_Name', 'DEFAULT_TUITION_FEE');
        if ($feeSetting && is_numeric($feeSetting['Setting_Value'])) {
            return (float)$feeSetting['Setting_Value'];
        }
        return 0;
    }

    /**
     * Get centralized Company Profile configuration (H8.1–H8.3).
     * Reads from MASTER_SYSTEM_SETTING with safe fallback defaults.
     *
     * @return array{company: array, bank: array, document: array}
     */
    public function getCompanyProfile(): array
    {
        return [
            'company' => [
                'name'     => $this->get('COMPANY_NAME', 'WAKAMIYA MANAGEMENT SYSTEM'),
                'tagline'  => $this->get('COMPANY_TAGLINE', 'Enterprise Human Resource Engine'),
                'logo'     => $this->get('COMPANY_LOGO', ''),
                'address'  => $this->get('COMPANY_ADDRESS', 'Jl. Raya Wakamiya No. 88, Jakarta Selatan 12930'),
                'phone'    => $this->get('COMPANY_PHONE', '(021) 8000-9999'),
                'whatsapp' => $this->get('COMPANY_WA', ''),
                'email'    => $this->get('COMPANY_EMAIL', 'hr@wakamiya.ac.id'),
                'website'  => $this->get('COMPANY_WEB', 'https://wakamiya.ac.id'),
                'npwp'     => $this->get('COMPANY_NPWP', ''),
            ],
            'bank' => [
                'name'           => $this->get('COMPANY_BANK_NAME', ''),
                'account_number' => $this->get('COMPANY_BANK_ACCOUNT', ''),
                'account_holder' => $this->get('COMPANY_BANK_HOLDER', ''),
            ],
            'document' => [
                'signature_url' => $this->get('COMPANY_SIGNATURE_URL', ''),
                'stamp_url'     => $this->get('COMPANY_STAMP_URL', ''),
                'signer_name'   => $this->get('COMPANY_SIGNER_NAME', ''),
                'signer_title'  => $this->get('COMPANY_SIGNER_TITLE', ''),
            ],
        ];
    }
}