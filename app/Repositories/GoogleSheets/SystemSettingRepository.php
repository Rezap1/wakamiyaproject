<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface;

class SystemSettingRepository extends BaseSheetRepository implements SystemSettingRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_SYSTEM_SETTING';
        $this->cacheKey = 'system_setting_sheet';
        $this->primaryKey = 'Setting_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function clearCache() {
        parent::clearCache();
        \Illuminate\Support\Facades\Cache::forget('system_settings_all');
    }
}