<?php

namespace App\Interfaces\GoogleSheets;

interface PermissionRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function findByRoleAndModule(string $roleId, string $moduleId);
    public function generateNewId(string $prefix, int $padding = 6): string;
    public function create(array $data);
    public function update(string $id, array $data);
    public function softDelete(string $id);
}
