<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class NotificationRetentionService
{
    public const RETENTION_DAYS = 30;

    public const TIMEZONE = 'Asia/Jakarta';

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function prune(?CarbonInterface $now = null): array
    {
        $clock = $now
            ? CarbonImmutable::instance($now)->setTimezone(self::TIMEZONE)
            : CarbonImmutable::now(self::TIMEZONE);
        $cutoff = $clock->subDays(self::RETENTION_DAYS);
        $expiredIds = [];
        $malformed = 0;

        $rows = method_exists($this->notifications, 'getAllFresh')
            ? $this->notifications->getAllFresh()
            : $this->notifications->getAll();

        foreach ($rows as $notification) {
            $id = trim((string) ($notification['Notification_ID'] ?? ''));
            $createdAt = $this->parseCreatedAt($notification['Created_At'] ?? null);
            if ($id === '' || $createdAt === null) {
                $malformed++;

                continue;
            }

            // Strictly older only. A row exactly at the cutoff is preserved.
            if ($createdAt->lt($cutoff)) {
                $expiredIds[] = $id;
            }
        }

        if (! method_exists($this->notifications, 'hardDeleteMany')) {
            throw new \RuntimeException('Notification repository does not support safe physical batch deletion.');
        }
        $deleted = $this->notifications->hardDeleteMany($expiredIds);
        $result = [
            'cutoff' => $cutoff->toDateTimeString(),
            'candidates' => count(array_unique($expiredIds)),
            'deleted' => $deleted,
            'malformed_skipped' => $malformed,
        ];

        Log::info('MASTER_NOTIFICATION retention completed', $result);

        return $result;
    }

    private function parseCreatedAt(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, self::TIMEZONE);
            $errors = CarbonImmutable::getLastErrors();
        } catch (\Throwable) {
            return null;
        }
        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d H:i:s') !== $value) {
            return null;
        }

        return $date;
    }
}
