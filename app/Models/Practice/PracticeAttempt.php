<?php

namespace App\Models\Practice;

class PracticeAttempt extends PracticeModel
{
    protected $table = 'PRACTICE_ATTEMPT';

    protected $primaryKey = 'Attempt_ID';

    protected $casts = ['Started_At' => 'datetime', 'Deadline_At' => 'datetime', 'Completed_At' => 'datetime', 'Created_At' => 'datetime', 'Updated_At' => 'datetime'];
}
