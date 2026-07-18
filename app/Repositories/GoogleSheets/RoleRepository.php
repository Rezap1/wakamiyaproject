<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\RoleRepositoryInterface;

class RoleRepository extends BaseSheetRepository implements RoleRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ROLE';
        $this->cacheKey = 'roles_sheet';
        $this->primaryKey = 'Role_ID';
    }

    public function findById(string $id)
    {
        $roles = $this->fetchAll();
        return $roles->firstWhere('id', $id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
