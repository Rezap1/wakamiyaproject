<?php

namespace App\Support\Academic;

use App\Helpers\SheetValue;

class AcademicSheetMapper
{
    public static function normalizeScheduleRow(array $row): array
    {
        $row['Schedule_ID'] = self::stringValue($row, 'Schedule_ID');
        $row['Academic_Year_ID'] = self::stringValue($row, 'Academic_Year_ID');
        $row['Day_Of_Week'] = self::stringValue($row, 'Day_Of_Week');
        $row['Room'] = self::stringValue($row, 'Room');
        $row['Is_Active'] = self::activeValue($row);

        return $row;
    }

    public static function normalizeSubjectRow(array $row): array
    {
        $row['Subject_ID'] = self::stringValue($row, 'Subject_ID');
        $row['Subject_Code'] = self::stringValue($row, 'Subject_Code');
        $row['Subject_Name'] = self::stringValue($row, 'Subject_Name');
        $row['Credit'] = self::stringValue($row, 'Credit');
        $row['Duration'] = self::stringValue($row, 'Duration');
        $row['Is_Active'] = self::activeValue($row);

        return $row;
    }

    public static function normalizeAcademicYearRow(array $row): array
    {
        $row['Academic_Year_ID'] = self::stringValue($row, 'Academic_Year_ID');
        $row['Name'] = self::stringValue($row, 'Name');
        $row['Semester'] = self::stringValue($row, 'Semester');
        $row['Is_Active'] = self::activeValue($row);

        return $row;
    }

    public static function timeForStorage($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 5);
    }

    private static function stringValue(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    private static function activeValue(array $row): string
    {
        if (array_key_exists('Is_Active', $row)) {
            $value = trim((string) $row['Is_Active']);
            if ($value !== '') {
                return self::normalizeActive($value);
            }
        }

        return 'TRUE';
    }

    private static function normalizeActive(string $value): string
    {
        return SheetValue::isInactive(['Status' => $value]) ? 'FALSE' : 'TRUE';
    }
}
