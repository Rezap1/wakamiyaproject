<?php

namespace App\Interfaces\GoogleSheets;

interface PermanentQrRepositoryInterface
{
    public function fetchAll();
    public function fetchActive();
    public function findByIdentifier(string $identifier);
    public function findById(string $id);
    public function generateNewId(string $prefix, int $padding = 6): string;
    public function create(array $data);
    public function update(string $id, array $data);
    public function deactivate(string $id, string $actorUserId);
    public function delete(string $id);
    public function clearCache();
}
