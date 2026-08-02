<?php

namespace App\Helpers;

class GradeHelper
{
    public static function calculate($score)
    {
        $grades = config('assessment.grades');
        $passingScore = config('assessment.passing_score');
        
        $letterGrade = 'E'; // Default
        foreach ($grades as $grade => $range) {
            if ($score >= $range['min'] && $score <= $range['max']) {
                $letterGrade = $grade;
                break;
            }
        }
        
        return [
            'score' => $score,
            'grade' => $letterGrade,
            'pass' => $score >= $passingScore,
            'percentage' => $score . '%'
        ];
    }
}