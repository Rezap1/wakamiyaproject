<?php

return [
    'timezone' => 'Asia/Jakarta',
    'period_anchor' => env('QUIZ_PERIOD_ANCHOR', '2026-10-01 00:00:00'),
    'period_days' => 14,
    'review_minutes' => 4,
    'max_duration_minutes' => 480,
    // Bound both client rendering work and the accepted server payload size.
    'max_questions' => 100,
    'cleanup_batch_size' => 50,
];
