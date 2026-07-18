<?php

namespace App\Interfaces\GoogleSheets;

interface ActivityLogRepositoryInterface
{
    public function fetchAll();
    public function create(array $data);
    public function generateNewId(string $prefix, int $padding = 6): string;
}
