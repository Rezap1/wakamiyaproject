<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;

class QuizQuestionRepository extends BaseSheetRepository implements QuizQuestionRepositoryInterface
{
    public const HEADERS = ['Question_ID', 'Quiz_ID', 'Question_Text', 'Option_A', 'Option_B', 'Option_C', 'Option_D', 'Correct_Option', 'Point', 'Sort_Order', 'Created_At', 'Updated_At'];

    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'QUIZ_QUESTION';
        $this->cacheKey = 'quiz_questions_sheet';
        $this->primaryKey = 'Question_ID';
        $this->expectedHeaders = self::HEADERS;
    }

    public function findById(string $id)
    {
        return $this->fetchAll()->firstWhere('Question_ID', $id);
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
