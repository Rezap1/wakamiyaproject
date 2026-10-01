<?php

namespace App\Interfaces\GoogleSheets;

interface QuizQuestionRepositoryInterface
{
    public function fetchAll();

    public function fetchAllFresh();

    public function findById(string $id);

    public function create(array $data);

    public function update(string $id, array $data);

    public function hardDeleteMany(array $ids): int;
}
