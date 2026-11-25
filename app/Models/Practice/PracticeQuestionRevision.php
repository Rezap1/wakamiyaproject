<?php

namespace App\Models\Practice;

class PracticeQuestionRevision extends PracticeModel
{
    protected $table = 'PRACTICE_QUESTION_REVISION';

    protected $primaryKey = 'Question_ID';

    protected $casts = ['Point' => 'decimal:2', 'Created_At' => 'datetime'];
}
