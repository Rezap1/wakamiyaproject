<?php

namespace App\Interfaces\GoogleSheets;

interface MatchingRepositoryInterface
{
    public function fetchAll(bool $forceRefresh = false): array;
    public function findById(string $id): ?array;
    public function create(array $data): bool;
    public function update(string $id, array $data): bool;
    public function softDelete(string $id, string $deletedBy = 'system');
    public function generateNewId(string $prefix = 'MTC', int $padding = 6): string;
}
