<?php

namespace App\Services\Attendance;

use App\Services\Core\SystemSettingService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class AttendanceWindowService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public function __construct(private SystemSettingService $settingService) {}

    public function serverNow(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    /**
     * Resolve the authoritative operational attendance window.
     *
     * @return array{now: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable, start_label: string, end_label: string}
     */
    public function resolve(?CarbonInterface $serverNow = null): array
    {
        $now = $serverNow
            ? CarbonImmutable::instance($serverNow)->setTimezone(self::TIMEZONE)
            : $this->serverNow();

        $startValue = $this->requiredTimeSetting('WORK_START_TIME');
        $endValue = $this->requiredTimeSetting('WORK_END_TIME');
        $start = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $now->toDateString().' '.$startValue.':00',
            self::TIMEZONE
        );
        $end = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $now->toDateString().' '.$endValue.':00',
            self::TIMEZONE
        );

        if (! $start || ! $end || $start->greaterThanOrEqualTo($end)) {
            throw new InvalidArgumentException(
                'Konfigurasi jam absensi tidak valid. Jam selesai harus setelah jam mulai pada hari yang sama.'
            );
        }

        return [
            'now' => $now,
            'start' => $start,
            'end' => $end,
            'start_label' => $startValue,
            'end_label' => $endValue,
        ];
    }

    /**
     * The start and end instants are inclusive.
     *
     * @param  array{now: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable, start_label: string, end_label: string}  $window
     */
    public function assertOpen(array $window): void
    {
        if ($window['now']->lessThan($window['start'])) {
            throw new AttendanceWindowException(
                'TOO_EARLY',
                "Absensi belum dibuka. Absensi dimulai pukul {$window['start_label']}."
            );
        }

        if ($window['now']->greaterThan($window['end'])) {
            throw new AttendanceWindowException(
                'TOO_LATE',
                "Waktu absensi telah berakhir pada pukul {$window['end_label']}."
            );
        }
    }

    /**
     * Classify a student scan only after the operational gate has passed.
     * Student QR attendance has no late state: every accepted scan is present.
     *
     * @param  array{now: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable, start_label: string, end_label: string}  $window
     * @return array{status: string, late_minutes: int}
     */
    public function resolveStudentStatus(array $window): array
    {
        $this->assertOpen($window);

        return [
            'status' => 'PRESENT',
            'late_minutes' => 0,
        ];
    }

    private function requiredTimeSetting(string $key): string
    {
        $value = trim((string) $this->settingService->get($key, ''));
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new InvalidArgumentException(
                "Konfigurasi {$key} belum valid. Presensi ditolak."
            );
        }

        return $value;
    }
}
