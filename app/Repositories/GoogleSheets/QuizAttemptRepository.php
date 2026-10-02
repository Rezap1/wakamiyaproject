<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\QuizAttemptRepositoryInterface;

class QuizAttemptRepository extends BaseSheetRepository implements QuizAttemptRepositoryInterface
{
    public const HEADERS = ['Attempt_ID', 'Quiz_ID', 'Student_ID', 'Started_At', 'Deadline_At', 'Status', 'Submitted_Answers_JSON', 'Submitted_At', 'Created_At', 'Updated_At'];

    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'QUIZ_ATTEMPT';
        $this->cacheKey = 'quiz_attempts_sheet';
        $this->primaryKey = 'Attempt_ID';
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

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }
}
