<?php

namespace App\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AgendaRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function find(string $id): ?array;

    public function create(array $data): array;

    public function update(string $id, array $data): bool;

    public function delete(string $id, string $actorId): bool;

    public function generateNewId(string $prefix = 'AGD', int $padding = 6): string;

    public function eligiblePics(): Collection;

    public function isEligiblePic(string $userId): bool;
}
