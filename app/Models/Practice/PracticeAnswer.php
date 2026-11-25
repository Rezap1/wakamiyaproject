<?php

namespace App\Models\Practice;

class PracticeAnswer extends PracticeModel
{
    protected $table = 'PRACTICE_ANSWER';

    protected $primaryKey = 'Answer_ID';

    protected $casts = ['Saved_At' => 'datetime'];
}
