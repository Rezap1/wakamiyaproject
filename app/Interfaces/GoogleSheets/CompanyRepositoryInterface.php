<?php

namespace App\Interfaces\GoogleSheets;

interface CompanyRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function findByCode(string $code);
    public function generateNewId(string $prefix, int $padding = 6): string;
    public function create(array $data);
    public function update(string $id, array $data);
    public function softDelete(string $id);
}
