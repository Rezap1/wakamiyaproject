<?php

namespace App\Support\Academic;

final class ScoreValueFormatter
{
    /**
     * Format a score for Indonesian presentation without changing its value.
     */
    public static function format(mixed $value, string $fallback = '-'): string
    {
        $canonical = self::canonical($value);

        return $canonical === null ? $fallback : str_replace('.', ',', $canonical);
    }

    /**
     * Format a score for an HTML number input (dot decimal separator).
     */
    public static function forInput(mixed $value, string $fallback = ''): string
    {
        return self::canonical($value) ?? $fallback;
    }

    private static function canonical(mixed $value): ?string
    {
        if ($value === null || is_bool($value) || is_array($value) || is_object($value)) {
            return null;
        }

        if (is_float($value) && ! is_finite($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Score inputs and persisted score values use either a dot or comma as
        // the decimal separator. Thousands separators are not valid for scores.
        $normalized = str_replace(',', '.', $raw);
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $normalized, $matches)) {
            return $raw;
        }

        $sign = $matches[1] ?? '';
        $integer = ltrim($matches[2], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim($matches[3] ?? '', '0');

        if ($integer === '0' && $fraction === '') {
            $sign = '';
        }

        return $sign.$integer.($fraction !== '' ? '.'.$fraction : '');
    }
}
