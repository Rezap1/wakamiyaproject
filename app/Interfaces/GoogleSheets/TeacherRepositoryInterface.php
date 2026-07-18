<?php

namespace App\Interfaces\GoogleSheets;

interface TeacherRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function findByEmployeeId(string $employeeId);
    public function generateNewId(string $prefix, int $padding = 6): string;
    public function generateTeacherCode(string $prefix, string $year, int $padding = 3): string;
    public function create(array $data);
    public function update(string $id, array $data);
    public function softDelete(string $id);
}
