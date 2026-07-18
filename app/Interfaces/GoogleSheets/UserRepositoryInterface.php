<?php

namespace App\Interfaces\GoogleSheets;

interface UserRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
    public function findByEmail(string $email);
    public function create(array $data);
    public function update(string $id, array $data);
    public function delete(string $id);
    public function softDelete(string $id);
    public function generateNewId(string $prefix, int $padding = 6): string;
}
