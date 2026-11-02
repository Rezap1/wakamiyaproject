<?php

namespace App\Services\Attendance;

use App\Services\Core\SystemSettingService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class AttendanceWindowService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const START_SETTING_KEY = 'STUDENT_ATTENDANCE_START_TIME';

    public const END_SETTING_KEY = 'STUDENT_ATTENDANCE_END_TIME';

    public const DEFAULT_START = '06:00';

    public const DEFAULT_END = '07:00';

    public const SESSION_MINUTES = 60;

    public const LATE_AFTER_MINUTES = 30;

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
        $startValue = $this->requiredTimeSetting(self::START_SETTING_KEY, self::DEFAULT_START);
        $endValue = $this->requiredTimeSetting(self::END_SETTING_KEY, self::DEFAULT_END);

        return $this->resolveForTimes($startValue, $endValue, $serverNow);
    }

    /**
     * Resolve a window supplied by a specific QR while preserving the same
     * parser, timezone, inclusivity, and validation used by global settings.
     */
    public function resolveForTimes(
        string $startValue,
        string $endValue,
        ?CarbonInterface $serverNow = null
    ): array {
        $now = $serverNow
            ? CarbonImmutable::instance($serverNow)->setTimezone(self::TIMEZONE)
            : $this->serverNow();

        $startValue = $this->validateTime($startValue, 'jam mulai absensi QR');
        $endValue = $this->validateTime($endValue, 'jam selesai absensi QR');
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

        // Existing saved closing times remain intact. The student session now
        // lasts exactly one hour from its configured start (WIB).
        $end = $start->addMinutes(self::SESSION_MINUTES);
        if ($end->toDateString() !== $start->toDateString()) {
            throw new InvalidArgumentException('Sesi absensi siswa satu jam harus selesai pada hari yang sama.');
        }
        $endValue = $end->format('H:i');

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
     * The second half of the one-hour student session is late.
     *
     * @param  array{now: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable, start_label: string, end_label: string}  $window
     * @return array{status: string, late_minutes: int}
     */
    public function resolveStudentStatus(array $window): array
    {
        $this->assertOpen($window);

        $elapsedMinutes = (int) $window['start']->diffInMinutes($window['now']);

        return [
            'status' => $elapsedMinutes >= self::LATE_AFTER_MINUTES ? 'LATE' : 'PRESENT',
            'late_minutes' => max(0, $elapsedMinutes - self::LATE_AFTER_MINUTES),
        ];
    }

    private function requiredTimeSetting(string $key, string $default): string
    {
        $value = trim((string) $this->settingService->get($key, $default));

        return $this->validateTime($value, "konfigurasi {$key}");
    }

    private function validateTime(string $value, string $label): string
    {
        $value = trim($value);
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new InvalidArgumentException(ucfirst($label).' belum valid. Presensi ditolak.');
        }

        return $value;
    }
}
