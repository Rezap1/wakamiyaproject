<?php
namespace App\Services\Academic;

class GradeCalculationService
{
    public function calculateGrade($score)
    {
        $score = (float) $score;
        if ($score >= 90) return 'A';
        if ($score >= 85) return 'B+';
        if ($score >= 80) return 'B';
        if ($score >= 75) return 'C+';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'E';
    }
}