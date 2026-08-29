<?php

namespace App\Interfaces\GoogleSheets;

interface OvertimeRepositoryInterface
{
    public function fetchAll();
    public function getAll();
    public function getById(string $id): ?array;
    public function findByEmployee(string $employeeId);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function hardDelete($id);
    public function clearCache();
}
