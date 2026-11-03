<?php

namespace App\Support\Academic;

use App\Helpers\DateHelper;
use Illuminate\Support\Collection;

final class ScoreDateResolver
{
    /**
     * Resolve the business date of a score. Created_At is only a legacy
     * fallback when no dedicated assessment date is present.
     */
    public static function value(array $score): ?string
    {
        $details = is_array($score['Parsed_Details'] ?? null)
            ? $score['Parsed_Details']
            : self::decodeDetails($score['Evaluation_Details'] ?? null);

        $dedicatedCandidates = [
            $score['Assessment_Date'] ?? null,
            $details['assessment_date'] ?? null,
            $score['Date'] ?? null,
        ];

        foreach ($dedicatedCandidates as $candidate) {
            if (trim((string) $candidate) === '') {
                continue;
            }

            return DateHelper::parse($candidate)?->timezone(config('app.timezone'))->format('Y-m-d');
        }

        return DateHelper::parse($score['Created_At'] ?? null)?->timezone(config('app.timezone'))->format('Y-m-d');
    }

    public static function filter(Collection $scores, ?string $dateFrom, ?string $dateTo): Collection
    {
        if (! $dateFrom && ! $dateTo) {
            return $scores;
        }

        return $scores->filter(function ($score) use ($dateFrom, $dateTo) {
            $date = self::value((array) $score);
            if ($date === null) {
                return false;
            }

            return (! $dateFrom || $date >= $dateFrom)
                && (! $dateTo || $date <= $dateTo);
        });
    }

    private static function decodeDetails(mixed $details): array
    {
        if (! is_string($details) || trim($details) === '') {
            return [];
        }

        $decoded = json_decode($details, true);

        return is_array($decoded) ? $decoded : [];
    }
}
