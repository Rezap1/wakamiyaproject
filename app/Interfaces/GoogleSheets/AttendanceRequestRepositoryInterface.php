<?php

namespace App\Interfaces\GoogleSheets;

interface AttendanceRequestRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function findByStudent(string $studentId);
    public function create(array $data);
    public function update($id, array $data);
    public function clearCache();
    public function generateNewId(string $prefix, int $padding = 6): string;
}
