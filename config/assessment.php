<?php

return [
    'categories' => [
        'Placement Test',
        'Daily Quiz',
        'Assignment',
        'Mid Test',
        'Final Test',
        'Speaking',
        'Listening',
        'Reading',
        'Writing',
        'Interview',
        'Attitude',
        'Attendance Contribution'
    ],
    
    'grades' => [
        'A+' => ['min' => 95, 'max' => 100],
        'A'  => ['min' => 90, 'max' => 94],
        'A-' => ['min' => 85, 'max' => 89],
        'B+' => ['min' => 80, 'max' => 84],
        'B'  => ['min' => 75, 'max' => 79],
        'B-' => ['min' => 70, 'max' => 74],
        'C+' => ['min' => 65, 'max' => 69],
        'C'  => ['min' => 60, 'max' => 64],
        'D'  => ['min' => 50, 'max' => 59],
        'E'  => ['min' => 0,  'max' => 49],
    ],
    
    'passing_score' => 65,
];