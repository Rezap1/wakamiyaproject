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

    public function findByCode(string $code)
    {
        $modules = $this->fetchAll();
        return $modules->first(function ($module) use ($code) {
            return strtolower($module['Module_Code'] ?? '') === strtolower($code);
        });
    }

    public function findByName(string $name)
    {
        $modules = $this->fetchAll();
        return $modules->first(function ($module) use ($name) {
            return strtolower($module['Module_Name'] ?? '') === strtolower($name);
        });
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
