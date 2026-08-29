<?php

namespace App\Helpers;

class AttendanceStatusHelper
{
    private const ALIASES = [
        'PRESENT' => ['present', 'hadir', 'masuk', 'tepat waktu'],
        'LATE' => ['late', 'terlambat', 'telat'],
        'SICK' => ['sick', 'sakit'],
        'PERMITTED' => ['permitted', 'permission', 'leave', 'izin', 'excused'],
        'ABSENT' => ['absent', 'alpha', 'alpa', 'tidak hadir'],
    ];

    public static function normalize(?string $status, string $default = 'PRESENT'): string
    {
        $value = strtolower(trim((string) $status));
        if ($value === '') {
            return $default;
        }

        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array($value, $aliases, true)) {
                return $canonical;
            }
        }

        return strtoupper(trim((string) $status));
    }

    public static function label(?string $status): string
    {
        return match (self::normalize($status)) {
            'PRESENT' => 'Hadir',
            'LATE' => 'Terlambat',
            'SICK' => 'Sakit',
            'PERMITTED' => 'Izin',
            'ABSENT' => 'Alpa',
            default => ucfirst(strtolower(trim((string) $status) ?: 'Hadir')),
        };
    }

    public static function badgeColor(?string $status): string
    {
        return match (self::normalize($status)) {
            'PRESENT' => 'green',
            'LATE' => 'yellow',
            'SICK' => 'yellow',
            'PERMITTED' => 'blue',
            'ABSENT' => 'red',
            default => 'slate',
        };
    }

    public static function isPresentLike(?string $status): bool
    {
        return in_array(self::normalize($status), ['PRESENT', 'LATE'], true);
    }
}
