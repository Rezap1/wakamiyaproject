<?php

namespace App\Support;

/**
 * Parse geofence coordinates from spreadsheet settings.
 *
 * Spreadsheet users occasionally enter a decimal coordinate with thousands
 * separators (for example, -6.812.391).  That value is unambiguous as a
 * six-decimal coordinate, but is not accepted by PHP's numeric parser.
 */
final class CoordinateNormalizer
{
    public static function parse(mixed $value, float $minimum, float $maximum): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            // Accept only repeated three-digit grouping after the integer
            // part.  Do not turn arbitrary malformed input into a coordinate.
            if (!preg_match('/^(-?)(\d{1,3})(?:\.(\d{3})){2,}$/', $raw)) {
                return null;
            }

            $parts = explode('.', ltrim($raw, '-'));
            $normalized = (str_starts_with($raw, '-') ? '-' : '')
                . array_shift($parts) . '.' . implode('', $parts);
        }

        $coordinate = (float) $normalized;
        if (!is_finite($coordinate) || $coordinate < $minimum || $coordinate > $maximum) {
            return null;
        }

        return $coordinate;
    }
}
