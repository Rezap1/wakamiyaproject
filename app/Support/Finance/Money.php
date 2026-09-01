<?php

namespace App\Support\Finance;

use InvalidArgumentException;

/**
 * Canonical money normalisation for finance code.
 * Values are kept as decimal numbers because the existing Sheets schema is
 * numeric, but all validation and comparisons happen at cent precision.
 */
final class Money
{
    public const SCALE = 2;

    public static function value(mixed $value, string $field = 'Amount', bool $allowZero = true): float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw new InvalidArgumentException("{$field} harus berupa angka.");
        }

        $number = (float) $value;
        if (!is_finite($number)) {
            throw new InvalidArgumentException("{$field} harus berupa angka finite.");
        }
        if ($number < 0 || (!$allowZero && $number <= 0)) {
            $message = $allowZero ? "{$field} tidak boleh negatif." : "{$field} harus lebih besar dari nol.";
            throw new InvalidArgumentException($message);
        }

        return round($number, self::SCALE, PHP_ROUND_HALF_UP);
    }

    public static function cents(mixed $value, string $field = 'Amount', bool $allowZero = true): int
    {
        return (int) round(self::value($value, $field, $allowZero) * (10 ** self::SCALE), 0, PHP_ROUND_HALF_UP);
    }

    public static function equal(mixed $left, mixed $right): bool
    {
        try {
            return self::cents($left) === self::cents($right);
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
