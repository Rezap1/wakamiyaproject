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
    public function clearCache() {
        parent::clearCache();
        \Illuminate\Support\Facades\Cache::forget('system_parameters_all');
    }
}