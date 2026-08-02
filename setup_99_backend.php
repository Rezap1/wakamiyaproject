<?php
$dirInterface = 'app/Interfaces/GoogleSheets';
$dirRepo = 'app/Repositories/GoogleSheets';
$dirService = 'app/Services/Core';

// 1. Interfaces
$setInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface SystemSettingRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function update($id, array $data);
}
EOT;
file_put_contents("$dirInterface/SystemSettingRepositoryInterface.php", $setInterface);

$paramInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface SystemParameterRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function update($id, array $data);
}
EOT;
file_put_contents("$dirInterface/SystemParameterRepositoryInterface.php", $paramInterface);

// 2. Repositories
$setRepo = <<<'EOT'
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
}
EOT;
file_put_contents("$dirRepo/SystemSettingRepository.php", $setRepo);

$paramRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\SystemParameterRepositoryInterface;

class SystemParameterRepository extends BaseSheetRepository implements SystemParameterRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_SYSTEM_PARAMETER';
        $this->cacheKey = 'system_parameter_sheet';
        $this->primaryKey = 'Parameter_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
}
EOT;
file_put_contents("$dirRepo/SystemParameterRepository.php", $paramRepo);

// 3. Service
$setService = <<<'EOT'
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
}
EOT;
file_put_contents("$dirService/SystemSettingService.php", $setService);

echo "Settings Backend Created.\n";
?>
