<?php

namespace App\Support\Finance;

use App\Exceptions\FinancialIntegrityException;

final class PaymentStatus
{
    private const CANONICAL = [
        'waiting verification' => 'Waiting Verification',
        'need revision' => 'Need Revision',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'reversed' => 'Reversed',
    ];

    public static function canonical(mixed $status): string
    {
        $key = strtolower(trim((string) $status));
        if (!isset(self::CANONICAL[$key])) {
            throw new FinancialIntegrityException('Status payment tersimpan tidak valid dan tidak dapat digunakan dalam perhitungan keuangan.');
        }
        return self::CANONICAL[$key];
    }

    public static function is(mixed $status, string $expected): bool
    {
        return self::canonical($status) === self::canonical($expected);
    }

    public static function verified(mixed $status): bool
    {
        return self::is($status, 'Verified');
    }
}
