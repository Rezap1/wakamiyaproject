<?php

namespace App\Services\Migration;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

final class ValueNormalizer
{
    public function __construct(private readonly string $timezone = 'Asia/Jakarta') {}

    /** @return array{value: mixed, warnings: list<string>} */
    public function normalize(mixed $raw, string $type): array
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return ['value' => null, 'warnings' => []];
        }

        return match ($type) {
            'boolean' => ['value' => $this->boolean($raw), 'warnings' => []],
            'integer' => ['value' => $this->integer($raw), 'warnings' => []],
            'decimal' => ['value' => $this->decimal($raw), 'warnings' => []],
            'date' => ['value' => $this->date($raw), 'warnings' => []],
            'datetime' => ['value' => $this->dateTime($raw), 'warnings' => []],
            'time' => ['value' => $this->time($raw), 'warnings' => []],
            'json_text' => $this->jsonText($raw),
            'identifier' => ['value' => $this->boundedString($raw, 191, 'Identifier'), 'warnings' => []],
            'short_string' => ['value' => $this->boundedString($raw, 512, 'Short string'), 'warnings' => []],
            default => ['value' => $this->string($raw), 'warnings' => []],
        };
    }

    private function string(mixed $raw): string
    {
        if (! is_scalar($raw)) {
            throw new InvalidArgumentException('Value is not scalar.');
        }

        return (string) $raw;
    }

    private function boundedString(mixed $raw, int $maximum, string $label): string
    {
        $value = $this->string($raw);
        if (mb_strlen($value) > $maximum) {
            throw new InvalidArgumentException("{$label} exceeds {$maximum} characters.");
        }

        return $value;
    }

    private function boolean(mixed $raw): int
    {
        if (is_bool($raw)) {
            return $raw ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $raw));
        if (in_array($normalized, ['1', 'true'], true)) {
            return 1;
        }
        if (in_array($normalized, ['0', 'false'], true)) {
            return 0;
        }

        throw new InvalidArgumentException('Expected TRUE/FALSE or 1/0.');
    }

    private function integer(mixed $raw): int
    {
        $value = trim((string) $raw);
        if (! preg_match('/^-?\d+$/', $value)) {
            throw new InvalidArgumentException('Expected an integer.');
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false) {
            throw new InvalidArgumentException('Integer is outside the supported range.');
        }

        return $integer;
    }

    private function decimal(mixed $raw): string
    {
        // A Google numeric cell is decoded as a PHP float. Reject real excess
        // scale, then format the declared DECIMAL scale so binary float noise
        // never reaches reconciliation or SQL.
        if (is_float($raw)) {
            $rounded = round($raw, 4);
            if (abs($raw - $rounded) > 0.00000001) {
                throw new InvalidArgumentException('Decimal has more than four significant fractional digits.');
            }
            $value = number_format($rounded, 4, '.', '');
        } else {
            $value = trim((string) $raw);
        }
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Expected a base-10 decimal.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > 4 && trim(substr($fraction, 4), '0') !== '') {
            throw new InvalidArgumentException('Decimal has more than four significant fractional digits.');
        }

        $integer = ltrim($matches[2], '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > 16) {
            throw new InvalidArgumentException('Decimal exceeds DECIMAL(20,4).');
        }

        $normalized = $integer.'.'.str_pad(substr($fraction, 0, 4), 4, '0');

        return $matches[1] === '-' && $normalized !== '0.0000' ? '-'.$normalized : $normalized;
    }

    private function date(mixed $raw): string
    {
        $value = trim((string) $raw);
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!j/n/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone($this->timezone));
            if ($date !== false && $date->format(substr($format, 1)) === $value) {
                return $date->format('Y-m-d');
            }
        }

        throw new InvalidArgumentException('Expected an unambiguous date.');
    }

    private function dateTime(mixed $raw): string
    {
        $value = trim((string) $raw);
        $zone = new DateTimeZone($this->timezone);

        foreach (['!Y-m-d H:i:s.u', '!Y-m-d H:i:s', '!Y-m-d H:i', '!d/m/Y H:i:s', '!d/m/Y H:i'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $zone);
            if ($date !== false) {
                $errors = DateTimeImmutable::getLastErrors();
                if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $date->format('Y-m-d H:i:s.u');
                }
            }
        }

        try {
            return (new DateTimeImmutable($value, $zone))->setTimezone($zone)->format('Y-m-d H:i:s.u');
        } catch (\Throwable) {
            throw new InvalidArgumentException('Expected a valid datetime.');
        }
    }

    private function time(mixed $raw): string
    {
        $value = trim((string) $raw);
        foreach (['!H:i:s.u', '!H:i:s', '!H:i'] as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone($this->timezone));
            if ($time !== false) {
                return $time->format('H:i:s.u');
            }
        }

        throw new InvalidArgumentException('Expected a valid time.');
    }

    /** @return array{value: string, warnings: list<string>} */
    private function jsonText(mixed $raw): array
    {
        if (is_array($raw) || is_object($raw)) {
            return ['value' => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'warnings' => []];
        }

        $value = (string) $raw;
        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return ['value' => $value, 'warnings' => []];
        } catch (JsonException) {
            return ['value' => $value, 'warnings' => ['invalid_json_preserved_as_text']];
        }
    }
}
