<?php

namespace App\Interfaces\GoogleSheets;

interface QuizResultRepositoryInterface
{
    public function fetchAll();

    public function fetchAllFresh();

    public function findById(string $id);

    public function findByIdFresh($id);

    public function create(array $data);

    public function hardDeleteMany(array $ids): int;
}
