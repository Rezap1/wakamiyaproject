<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;

class DepartmentRepository extends BaseSheetRepository implements DepartmentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_DEPARTMENT';
        $this->cacheKey = 'departments_sheet';
        $this->primaryKey = 'Department_ID';
    }

    public function findById(string $id)
    {
        $departments = $this->fetchAll();
        return $departments->firstWhere($this->primaryKey, $id);
    }

    public function findByCode(string $code)
    {
        $departments = $this->fetchAll();
        return $departments->first(function ($department) use ($code) {
            return strtolower($department['Department_Code'] ?? '') === strtolower($code);
        });
    }

    public function findByName(string $name)
    {
        $departments = $this->fetchAll();
        return $departments->first(function ($department) use ($name) {
            return strtolower($department['Department_Name'] ?? '') === strtolower($name);
        });
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
