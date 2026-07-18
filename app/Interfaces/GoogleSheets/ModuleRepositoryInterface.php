<?php

namespace App\Interfaces\GoogleSheets;

interface ModuleRepositoryInterface
{
    public function fetchAll();
    public function findById(string $id);
}
