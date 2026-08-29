<?php

namespace App\Interfaces\GoogleSheets;

interface AssessmentConfigRepositoryInterface
{
    public function getAll();
    public function getByCategory($categoryId);
}
