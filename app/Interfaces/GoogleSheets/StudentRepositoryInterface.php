<?php

namespace App\Interfaces\GoogleSheets;

interface StudentRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function findByStudentNumber(string $number);
    public function findByNationalId(string $nationalId);
    public function generateNewId(string $prefix, int $padding = 6): string;
    public function create(array $data);
    public function update(string $id, array $data);
    public function softDelete(string $id);
}
