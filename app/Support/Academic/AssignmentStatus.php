<?php

namespace App\Support\Academic;

/**
 * Canonical assignment status values used by MASTER_ASSIGNMENT.Status.
 * Labels are intentionally kept separate from values so storage never
 * depends on translated presentation text.
 */
final class AssignmentStatus
{
    public const PUBLISHED = 'PUBLISHED';
    public const DRAFT = 'DRAFT';
    public const CLOSED = 'CLOSED';
    public const ACTIVE = 'ACTIVE';
    public const ARCHIVED = 'ARCHIVED';

    public static function values(): array
    {
        return [self::PUBLISHED, self::DRAFT, self::CLOSED, self::ACTIVE, self::ARCHIVED];
    }

    public static function normalize(mixed $value): ?string
    {
        $key = strtoupper(trim((string) $value));
        if ($key === '') {
            return null;
        }

        return match ($key) {
            'PUBLISHED', 'PUBLISH', 'TERPUBLIKASI', 'TERPUBLISHASI', 'DIPUBLIKASIKAN' => self::PUBLISHED,
            'DRAFT', 'DRAF', 'BELUM DIPUBLIKASIKAN' => self::DRAFT,
            'CLOSED', 'CLOSE', 'DITUTUP' => self::CLOSED,
            'ACTIVE', 'AKTIF' => self::ACTIVE,
            'ARCHIVED', 'ARSIP', 'DIARSIPKAN' => self::ARCHIVED,
            default => null,
        };
    }

    /**
     * Resolve the first status-like input value from a request payload.
     *
     * Accepts direct field names and their legacy aliases.
     */
    public static function extractFrom(array $input): mixed
    {
        foreach ([
            'Status',
            'status',
            'publication_status',
            'Publication_Status',
            'assignment_status',
            'Assignment_Status',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                return $input[$key];
            }
        }

        foreach ($input as $key => $value) {
            $normalizedKey = strtolower(preg_replace('/[^a-z]/', '', (string) $key));
            if (in_array($normalizedKey, ['status', 'publicationstatus', 'assignmentstatus'], true)) {
                return $value;
            }
        }

        return null;
    }

    public static function label(mixed $value, string $fallback = '-'): string
    {
        return match (self::normalize($value)) {
            self::PUBLISHED => 'Terpublikasi',
            self::DRAFT => 'Draf',
            self::CLOSED => 'Ditutup',
            self::ACTIVE => 'Aktif',
            self::ARCHIVED => 'Diarsipkan',
            default => $fallback,
        };
    }
}
