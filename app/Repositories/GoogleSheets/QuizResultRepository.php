<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\QuizResultRepositoryInterface;

class QuizResultRepository extends BaseSheetRepository implements QuizResultRepositoryInterface
{
    public const HEADERS = ['Result_ID', 'Quiz_ID', 'Quiz_Title', 'Attempt_ID', 'Student_ID', 'Student_Name', 'Class_ID', 'Teacher_ID', 'Raw_Score', 'Maximum_Score', 'Normalized_Score', 'Started_At', 'Completed_At', 'Created_At'];

    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'QUIZ_RESULT';
        $this->cacheKey = 'quiz_results_sheet';
        $this->primaryKey = 'Result_ID';
        $this->expectedHeaders = self::HEADERS;
    }

    public function findById(string $id)
    {
        return $this->findByIdFresh($id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
