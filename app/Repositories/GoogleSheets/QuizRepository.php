<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\QuizRepositoryInterface;

class QuizRepository extends BaseSheetRepository implements QuizRepositoryInterface
{
    public const HEADERS = ['Quiz_ID', 'Teacher_ID', 'Class_ID', 'Title', 'Start_At', 'End_At', 'Duration_Minutes', 'Status', 'Created_At', 'Updated_At', 'Created_By', 'Updated_By'];

    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'QUIZ';
        $this->cacheKey = 'quiz_sheet';
        $this->primaryKey = 'Quiz_ID';
        $this->expectedHeaders = self::HEADERS;
    }

    public function findById(string $id)
    {
        return $this->fetchAll()->firstWhere('Quiz_ID', $id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }
}
