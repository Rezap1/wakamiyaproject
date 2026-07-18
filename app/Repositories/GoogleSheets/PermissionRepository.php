<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\PermissionRepositoryInterface;

class PermissionRepository extends BaseSheetRepository implements PermissionRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_PERMISSION';
        $this->cacheKey = 'permissions_sheet';
        $this->primaryKey = 'Permission_ID';
    }

    public function findById(string $id)
    {
        $permissions = $this->fetchAll();
        return $permissions->firstWhere($this->primaryKey, $id);
    }

    public function findByRoleAndModule(string $roleId, string $moduleId)
    {
        $permissions = $this->fetchAll();
        return $permissions->first(function ($item) use ($roleId, $moduleId) {
            return ($item['Role_ID'] ?? '') === $roleId && ($item['Module_ID'] ?? '') === $moduleId;
        });
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
