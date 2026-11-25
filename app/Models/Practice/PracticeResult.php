<?php

namespace App\Models\Practice;

class PracticeResult extends PracticeModel
{
    protected $table = 'PRACTICE_RESULT';

    protected $primaryKey = 'Result_ID';

    protected $casts = ['Raw_Score' => 'decimal:2', 'Max_Score' => 'decimal:2', 'Normalized_Score' => 'decimal:2', 'Started_At' => 'datetime', 'Completed_At' => 'datetime', 'Created_At' => 'datetime'];
}
