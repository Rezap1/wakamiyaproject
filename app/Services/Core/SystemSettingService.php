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
        return $setting ? $setting['Setting_Value'] : $default;
    }

    public function parameter($module, $key, $default = null) {
        $param = $this->getParameters()->where('Module', $module)->firstWhere('Parameter_Key', $key);
        return $param ? $param['Parameter_Value'] : $default;
    }

    public function category($category) {
        return $this->getSettings()->where('Category', $category);
    }

    public function set($id, $value, $updaterEmail) {
        $setting = $this->settingRepo->getById($id);
        if($setting) {
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
}