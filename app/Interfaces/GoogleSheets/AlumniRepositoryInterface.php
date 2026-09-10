<?php

namespace App\Interfaces\GoogleSheets;

interface AlumniRepositoryInterface
{
    public function fetchAll();
    public function fetchAllFresh();
    public function findById(string $id);
    public function findByIdFresh(string $id);
    public function findByStudentId(string $studentId);
    public function generateNewId(string $prefix, int $padding = 6): string;
    public function create(array $data);
    public function update(string $id, array $data);
    public function softDelete(string $id);
}
