<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Safely parse any date/time string, handling slash-separated formats (DD/MM/YYYY).
     */
    public static function parse($date): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date;
        }

        try {
            $dateStr = str_replace('/', '-', trim((string)$date));
            return Carbon::parse($dateStr);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Format a date string safely into desired format, or return fallback string.
     */
    public static function format($date, string $format = 'd M Y, H:i', string $fallback = '-'): string
    {
        $parsed = static::parse($date);
        return $parsed ? $parsed->format($format) : $fallback;
    }
}
