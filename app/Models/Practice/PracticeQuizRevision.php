<?php

namespace App\Models\Practice;

class PracticeQuizRevision extends PracticeModel
{
    protected $table = 'PRACTICE_QUIZ_REVISION';

    protected $primaryKey = 'Revision_ID';

    protected $casts = ['Created_At' => 'datetime'];
}
