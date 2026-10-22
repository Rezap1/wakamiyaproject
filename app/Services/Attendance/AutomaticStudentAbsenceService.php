<?php

namespace App\Services\Attendance;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Repositories\MySql\BaseMySqlRepository;
use App\Services\Core\PermanentQrService;
use App\Services\Core\SystemSettingService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class AutomaticStudentAbsenceService
{
    public function __construct(
        private AttendanceRepositoryInterface $attendanceRepository,
        private StudentRepositoryInterface $studentRepository,
        private PermanentQrRepositoryInterface $permanentQrRepository,
        private SystemSettingService $settingService,
        private PermanentQrService $permanentQrService,
    ) {}

    /**
     * @return array{date:string,cutoff:string,created:int,skipped:int,dry_run:bool,state:string}
     */
    public function run(?CarbonInterface $serverNow = null, ?string $date = null, bool $dryRun = false): array
    {
        $now = $serverNow
            ? CarbonImmutable::instance($serverNow)->setTimezone(AttendanceWindowService::TIMEZONE)
            : CarbonImmutable::now(AttendanceWindowService::TIMEZONE);
        $targetDate = $date === null ? $now->toDateString() : $this->strictDate($date);
        if ($targetDate > $now->toDateString()) {
            throw new InvalidArgumentException('Tanggal Alpa otomatis tidak boleh berada di masa depan.');
        }

        $cutoffLabel = $this->effectiveCutoff($targetDate);
        $cutoff = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $targetDate.' '.$cutoffLabel.':00',
            AttendanceWindowService::TIMEZONE
        );
        if ($targetDate === $now->toDateString() && $now->lessThanOrEqualTo($cutoff)) {
            return $this->result($targetDate, $cutoffLabel, 0, 0, $dryRun, 'TOO_EARLY');
        }

        return Cache::lock('automatic_student_absence_'.$targetDate, 120)->block(5, function () use ($targetDate, $cutoffLabel, $dryRun): array {
            $operation = function () use ($targetDate, $cutoffLabel, $dryRun): array {
                $existing = collect($this->attendanceRepository->fetchAll())
                    ->filter(fn (array $row): bool => trim((string) ($row['Student_ID'] ?? '')) !== ''
                        && trim((string) ($row['Attendance_Date'] ?? '')) === $targetDate
                        && strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE')
                    ->mapWithKeys(fn (array $row): array => [
                        strtolower(trim((string) ($row['Student_ID'] ?? ''))) => true,
                    ]);

                $eligible = collect($this->studentRepository->fetchAll())
                    ->filter(fn (array $student): bool => $this->isEligible($student))
                    ->values();
                $created = 0;
                $skipped = 0;
                $createdAt = CarbonImmutable::now(AttendanceWindowService::TIMEZONE)->toDateTimeString();

                foreach ($eligible as $student) {
                    $studentId = trim((string) $student['Student_ID']);
                    $classId = trim((string) $student['Class_ID']);
                    $studentKey = strtolower($studentId);
                    if ($existing->has($studentKey)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $created++;

                        continue;
                    }

                    $record = [
                        'Attendance_ID' => 'ATT-ALP-'.strtoupper(substr(hash('sha256', "{$studentId}|{$targetDate}|{$classId}|CLASS_DAILY"), 0, 20)),
                        'Student_ID' => $studentId,
                        'Batch_ID' => trim((string) ($student['Batch_ID'] ?? '')),
                        'Class_ID' => $classId,
                        'Schedule_ID' => '',
                        'Attendance_Type' => 'CLASS_QR',
                        'Attendance_Date' => $targetDate,
                        'Check_In_Time' => '',
                        'Check_Out_Time' => '',
                        'Status' => 'ABSENT',
                        'Verification_Method' => 'SYSTEM_AUTO_ABSENT',
                        'Notes' => "Alpa otomatis setelah batas presensi {$cutoffLabel} WIB.",
                        'Is_Active' => 'TRUE',
                        'Created_By' => 'SYSTEM',
                        'Updated_By' => 'SYSTEM',
                        'Created_At' => $createdAt,
                        'Updated_At' => $createdAt,
                    ];

                    try {
                        $result = $this->attendanceRepository->create($record);
                    } catch (DuplicatePrimaryKeyException) {
                        $skipped++;

                        continue;
                    }
                    if ($result === false || $result === null) {
                        throw new \RuntimeException('Penulisan Alpa otomatis gagal; transaksi dibatalkan.');
                    }
                    $existing->put($studentKey, true);
                    $created++;
                }

                return $this->result($targetDate, $cutoffLabel, $created, $skipped, $dryRun, 'COMPLETED');
            };

            return $this->attendanceRepository instanceof BaseMySqlRepository
                ? $this->attendanceRepository->transaction($operation)
                : $operation();
        });
    }

    private function effectiveCutoff(string $targetDate): string
    {
        $latest = (new AttendanceWindowService($this->settingService))
            ->resolve(CarbonImmutable::parse($targetDate.' 12:00:00', AttendanceWindowService::TIMEZONE))['end_label'];
        $dayStart = CarbonImmutable::parse($targetDate.' 00:00:00', AttendanceWindowService::TIMEZONE);
        $dayEnd = $dayStart->endOfDay();

        foreach ($this->permanentQrRepository->fetchAll() as $qr) {
            if (strtoupper(trim((string) ($qr['QR_TYPE'] ?? ''))) !== 'STUDENT'
                || strtoupper(trim((string) ($qr['STATUS'] ?? ''))) !== 'ACTIVE') {
                continue;
            }

            $lifecycle = $this->permanentQrService->getLifecycleConfiguration($qr);
            $window = $this->permanentQrService->getAttendanceWindowConfiguration($qr);
            if (! $lifecycle['valid'] || ! $window['valid']
                || $lifecycle['from']->greaterThan($dayEnd)
                || $lifecycle['until']->lessThan($dayStart)) {
                continue;
            }

            $latest = max($latest, (string) $window['end']);
        }

        return $latest;
    }

    private function isEligible(array $student): bool
    {
        $active = strtoupper(trim((string) ($student['Is_Active'] ?? 'TRUE')));
        $enrollment = strtoupper(trim((string) ($student['Enrollment_Status'] ?? '')));
        $graduation = strtoupper(trim((string) ($student['Graduation_Status'] ?? '')));

        return trim((string) ($student['Student_ID'] ?? '')) !== ''
            && trim((string) ($student['Class_ID'] ?? '')) !== ''
            && trim((string) ($student['Class_ID'] ?? '')) !== '-'
            && $active !== 'FALSE'
            && $graduation !== 'LULUS'
            && ! str_contains($enrollment, 'ALUMNI')
            && ! str_contains($enrollment, 'CUTI')
            && ! str_contains($enrollment, 'DROPOUT')
            && ! str_contains($enrollment, 'NON-AKTIF')
            && ! str_contains($enrollment, 'OUT');
    }

    private function strictDate(string $date): string
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', trim($date), AttendanceWindowService::TIMEZONE);
        if (! $parsed || $parsed->format('Y-m-d') !== trim($date)) {
            throw new InvalidArgumentException('Tanggal Alpa otomatis tidak valid.');
        }

        return $parsed->toDateString();
    }

    private function result(string $date, string $cutoff, int $created, int $skipped, bool $dryRun, string $state): array
    {
        return [
            'date' => $date,
            'cutoff' => $cutoff,
            'created' => $created,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'state' => $state,
        ];
    }
}
