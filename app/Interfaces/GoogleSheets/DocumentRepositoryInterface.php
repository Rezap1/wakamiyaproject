<?php
namespace App\Interfaces\GoogleSheets;
interface DocumentRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function generateNewId(string $prefix, int $padding = 6): string;
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
