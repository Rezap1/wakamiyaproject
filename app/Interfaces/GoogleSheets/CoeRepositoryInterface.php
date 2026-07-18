<?php

namespace App\Interfaces\GoogleSheets;

interface CoeRepositoryInterface
{
    public function fetchAll(bool $forceRefresh = false): array;
    public function findById(string $id): ?array;
    public function create(array $data): bool;
    public function update(string $id, array $data): bool;
    public function softDelete(string $id, string $deletedBy = 'system');
    public function generateNewId(string $prefix = 'COE', int $padding = 6): string;
}
