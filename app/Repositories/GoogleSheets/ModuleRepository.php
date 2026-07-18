<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;

class ModuleRepository extends BaseSheetRepository implements ModuleRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_MODULE';
        $this->cacheKey = 'modules_sheet';
        $this->primaryKey = 'Module_ID';
    }

    public function findById(string $id)
    {
        $modules = $this->fetchAll();
        return $modules->firstWhere($this->primaryKey, $id);
    }
}
