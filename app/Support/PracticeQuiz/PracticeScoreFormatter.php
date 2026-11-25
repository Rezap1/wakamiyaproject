<?php

namespace App\Support\PracticeQuiz;

final class PracticeScoreFormatter
{
    /**
     * Format a practice-quiz score with at most two decimal places.
     */
    public static function format(mixed $value, string $fallback = '-'): string
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Format a practice-quiz score difference while retaining its sign.
     */
    public static function signed(mixed $value, string $fallback = '-'): string
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $numeric = (float) $value;

        return ($numeric > 0 ? '+' : '').self::format($numeric, $fallback);
    }
}
