<?php

namespace App\Interfaces\GoogleSheets;

interface ModuleRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function findByCode(string $code);
    public function findByName(string $name);
    public function create(array $data);
    public function update(string $id, array $data);
    public function softDelete(string $id);
    public function generateNewId(string $prefix, int $padding = 6): string;
}
