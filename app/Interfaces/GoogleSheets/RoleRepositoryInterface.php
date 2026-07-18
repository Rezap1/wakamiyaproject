<?php

namespace App\Interfaces\GoogleSheets;

interface RoleRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function create(array $data);
    public function update(string $id, array $data);
}
