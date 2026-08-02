<?php
namespace App\Interfaces\GoogleSheets;
interface AuditLogRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
}