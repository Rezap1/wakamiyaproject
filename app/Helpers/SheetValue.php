<?php

namespace App\Helpers;

class SheetValue
{
    public static function id($value): string
    {
        return strtoupper(trim((string) $value));
    }

    public static function isInactive(array $row): bool
    {
        $inactiveValues = [
            'FALSE',
            '0',
            'NO',
            'N',
            'INACTIVE',
            'NONAKTIF',
            'TIDAK AKTIF',
            'NONACTIVE',
            'NON_ACTIVE',
            'DELETED',
            'ARCHIVED',
            'CANCELLED',
            'DROPPED',
        ];

        if (array_key_exists('Is_Active', $row)) {
            $active = self::id($row['Is_Active']);
            if ($active !== '' && in_array($active, $inactiveValues, true)) {
                return true;
            }
        }

        foreach (['Class_Status', 'Status', 'Enrollment_Status', 'Teaching_Status'] as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $status = self::id($row[$field]);
            if ($field === 'Enrollment_Status' && $status === 'ALUMNI') {
                return true;
            }
            if ($status !== '' && in_array($status, $inactiveValues, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isAlumniEnrollment(array $row): bool
    {
        return self::id($row['Enrollment_Status'] ?? '') === 'ALUMNI';
    }

    public static function isOperationalStudent(array $row): bool
    {
        return ! self::isInactive($row) && ! self::isAlumniEnrollment($row);
    }

    public static function isActive(array $row): bool
    {
        return ! self::isInactive($row);
    }
}
