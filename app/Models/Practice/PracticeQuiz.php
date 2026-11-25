<?php

namespace App\Models\Practice;

class PracticeQuiz extends PracticeModel
{
    protected $table = 'PRACTICE_QUIZ';

    protected $primaryKey = 'Practice_Quiz_ID';

    protected $casts = ['Created_At' => 'datetime', 'Updated_At' => 'datetime', 'Published_At' => 'datetime', 'Deleted_At' => 'datetime'];
}
