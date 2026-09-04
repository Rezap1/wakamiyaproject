<?php

namespace App\Support\Academic;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class AcademicYearResolver
{
    public static function current(?CarbonInterface $date = null): array
    {
        $date = $date ? Carbon::instance($date) : Carbon::now(config('app.timezone'));
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        $startYear = $month >= 7 ? $year : $year - 1;
        $endYear = $startYear + 1;
        $semester = $month >= 7 ? 'Ganjil' : 'Genap';

        return [
            'Academic_Year_ID' => self::id($startYear, $endYear, $semester),
            'Name' => "{$startYear}/{$endYear}",
            'Semester' => $semester,
            'Start_Date' => sprintf('%04d-07-01', $startYear),
            'End_Date' => sprintf('%04d-06-30', $endYear),
            'Is_Active' => 'TRUE',
            'Notes' => 'AUTO_CURRENT_ACADEMIC_YEAR',
        ];
    }

    public static function currentId(?CarbonInterface $date = null): string
    {
        return self::current($date)['Academic_Year_ID'];
    }

    public static function isCurrentId(string $id): bool
    {
        return strcasecmp(trim($id), self::currentId()) === 0;
    }

    private static function id(int $startYear, int $endYear, string $semester): string
    {
        return sprintf('ACY-%04d-%04d-%s', $startYear, $endYear, strtoupper($semester));
    }
}
