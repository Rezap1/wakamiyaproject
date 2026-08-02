<?php
namespace App\Interfaces\GoogleSheets;
interface ApprovalHistoryRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
}