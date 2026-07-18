<?php

namespace App\Interfaces\GoogleSheets;

interface JobOrderRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function findByCompany(string $companyId);
    public function create(array $data);
    public function update(string $id, array $data);
    public function softDelete(string $id);
    public function generateNewId(string $prefix, int $padding = 6): string;
}
