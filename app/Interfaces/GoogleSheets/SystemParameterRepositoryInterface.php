<?php
namespace App\Interfaces\GoogleSheets;
interface SystemParameterRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function update($id, array $data);
}