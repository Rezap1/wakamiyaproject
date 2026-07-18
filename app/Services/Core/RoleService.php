<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\RoleRepositoryInterface;

class RoleService
{
    protected $roleRepository;

    public function __construct(RoleRepositoryInterface $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    public function getAllRoles()
    {
        return $this->roleRepository->fetchAll();
    }

    public function getRoleById($id)
    {
        return $this->roleRepository->findById($id);
    }

    public function createRole(array $data)
    {
        $data['created_at'] = now()->toDateTimeString();
        return $this->roleRepository->create($data);
    }
}
