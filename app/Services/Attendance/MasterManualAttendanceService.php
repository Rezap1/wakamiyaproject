<?php

namespace App\Services\Attendance;

use App\Helpers\AttendanceStatusHelper;
use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AttendanceService;
use Carbon\CarbonImmutable;
use Exception;

final class MasterManualAttendanceService
{
    public const ATTENDANCE_TYPE = 'CLASS_QR';

    public function __construct(
        private AttendanceRepositoryInterface $attendanceRepository,
        private AttendanceRequestRepositoryInterface $requestRepository,
        private StudentRepositoryInterface $studentRepository,
        private ClassRepositoryInterface $classRepository,
        private BatchRepositoryInterface $batchRepository,
        private AttendanceService $attendanceService,
    ) {}

    /** @return array{batches:array<int,array>,classes:array<int,array>} */
    public function options(): array
    {
        $batches = collect($this->batchRepository->fetchAll())
            ->filter(fn (array $row): bool => SheetValue::isActive($row) && $this->id($row['Batch_ID'] ?? '') !== '')
            ->values()
            ->all();
        $activeBatchIds = collect($batches)->map(fn (array $row): string => $this->id($row['Batch_ID'] ?? ''));
        $classes = collect($this->classRepository->fetchAll())
            ->filter(fn (array $row): bool => SheetValue::isActive($row)
                && $this->id($row['Class_ID'] ?? '') !== ''
                && $activeBatchIds->contains($this->id($row['Batch_ID'] ?? '')))
            ->values()
            ->all();

        return ['batches' => $batches, 'classes' => $classes];
    }

    /** @return array{date:string,batch:array,class:array,eligible:array<int,array>,blocked:array<int,array>} */
    public function roster(string $batchId, string $classId, string $date): array
    {
        $context = $this->resolveContext($batchId, $classId, $date);
        $students = collect($this->studentRepository->fetchAll())
            ->filter(fn (array $row): bool => SheetValue::isOperationalStudent($row)
                && $this->id($row['Batch_ID'] ?? '') === $this->id($context['batch']['Batch_ID'] ?? '')
                && $this->id($row['Class_ID'] ?? '') === $this->id($context['class']['Class_ID'] ?? ''))
            ->values();
        $studentIds = $students->map(fn (array $row): string => $this->id($row['Student_ID'] ?? ''))->filter();

        $officialByStudent = collect($this->attendanceRepository->fetchAll())
            ->filter(fn (array $row): bool => $this->isOfficialClassAttendance($row, $context['class']['Class_ID'], $context['date'])
                && $studentIds->contains($this->id($row['Student_ID'] ?? '')))
            ->keyBy(fn (array $row): string => $this->id($row['Student_ID'] ?? ''));
        $activeRequestsByStudent = collect($this->requestRepository->fetchAll())
            ->filter(fn (array $row): bool => $this->isActiveClassRequest($row, $context['class']['Class_ID'], $context['date'])
                && $studentIds->contains($this->id($row['Student_ID'] ?? '')))
            ->sortBy(fn (array $row): int => $this->requestStatus($row) === 'APPROVED' ? 2 : 1)
            ->keyBy(fn (array $row): string => $this->id($row['Student_ID'] ?? ''));

        $eligible = [];
        $blocked = [];
        foreach ($students as $student) {
            $studentId = $this->id($student['Student_ID'] ?? '');
            if ($studentId === '') {
                continue;
            }

            $base = [
                'Student_ID' => trim((string) ($student['Student_ID'] ?? '')),
                'Student_Number' => trim((string) ($student['Student_Number'] ?? $student['NIS'] ?? '')),
                'Full_Name' => trim((string) ($student['Full_Name'] ?? $student['Student_Name'] ?? $studentId)),
            ];
            if ($official = $officialByStudent->get($studentId)) {
                $status = AttendanceStatusHelper::normalize($official['Status'] ?? '', 'UNKNOWN');
                $blocked[] = $base + [
                    'source' => 'OFFICIAL_ATTENDANCE',
                    'status' => $status,
                    'status_label' => AttendanceStatusHelper::label($status),
                    'reason' => 'Sudah memiliki status absensi resmi untuk sesi harian ini.',
                ];

                continue;
            }

            if ($request = $activeRequestsByStudent->get($studentId)) {
                $status = $this->requestStatus($request);
                $blocked[] = $base + [
                    'source' => 'ATTENDANCE_REQUEST',
                    'status' => $status,
                    'status_label' => $status === 'APPROVED' ? 'Pengajuan Disetujui' : 'Pengajuan Menunggu Persetujuan',
                    'reason' => $status === 'APPROVED'
                        ? 'Pengajuan izin/sakit telah disetujui dan tidak boleh ditimpa manual.'
                        : 'Pengajuan izin/sakit masih pending; status tidak diubah atau disetujui otomatis.',
                ];

                continue;
            }

            $eligible[] = $base;
        }

        return $context + ['eligible' => $eligible, 'blocked' => $blocked];
    }

    /** @param list<string> $studentIds */
    public function record(
        string $batchId,
        string $classId,
        string $date,
        array $studentIds,
        string $reason,
        string $actorId
    ): int {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new Exception('Alasan pencatatan manual wajib diisi minimal 5 karakter.');
        }

        $selectedIds = collect($studentIds)
            ->map(fn ($id): string => $this->id($id))
            ->filter()
            ->unique()
            ->values();
        if ($selectedIds->isEmpty() || $selectedIds->count() !== count($studentIds)) {
            throw new Exception('Pilihan siswa kosong atau mengandung data ganda/tidak valid.');
        }

        $initial = $this->roster($batchId, $classId, $date);
        $this->assertStillEligible($initial, $selectedIds->all());
        $rows = $selectedIds->map(fn (string $studentId): array => [
            'Student_ID' => $studentId,
            'Status' => 'PRESENT',
            'Notes' => $reason,
        ])->all();

        return $this->attendanceService->recordManualBatch(
            $initial['class']['Class_ID'],
            $initial['date'],
            $rows,
            $actorId,
            function () use ($batchId, $classId, $date, $selectedIds): void {
                $this->assertStillEligible($this->roster($batchId, $classId, $date), $selectedIds->all());
            }
        );
    }

    /** @return array{date:string,batch:array,class:array} */
    private function resolveContext(string $batchId, string $classId, string $date): array
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', trim($date), 'Asia/Jakarta');
        } catch (\Throwable) {
            $parsed = null;
        }
        if (! $parsed || $parsed->format('Y-m-d') !== trim($date)) {
            throw new Exception('Tanggal absensi tidak valid.');
        }
        if ($parsed->isAfter(CarbonImmutable::today('Asia/Jakarta'))) {
            throw new Exception('Absensi manual tidak dapat dicatat untuk tanggal masa depan.');
        }

        $batch = collect($this->batchRepository->fetchAll())->first(fn (array $row): bool => SheetValue::isActive($row)
            && $this->id($row['Batch_ID'] ?? '') === $this->id($batchId));
        $class = collect($this->classRepository->fetchAll())->first(fn (array $row): bool => SheetValue::isActive($row)
            && $this->id($row['Class_ID'] ?? '') === $this->id($classId));
        if (! $batch || ! $class || $this->id($class['Batch_ID'] ?? '') !== $this->id($batch['Batch_ID'] ?? '')) {
            throw new Exception('Batch atau kelas tidak aktif, tidak ditemukan, atau tidak saling terkait.');
        }

        return ['date' => $parsed->toDateString(), 'batch' => $batch, 'class' => $class];
    }

    private function isOfficialClassAttendance(array $row, string $classId, string $date): bool
    {
        if (SheetValue::isInactive($row)
            || trim((string) ($row['Attendance_Date'] ?? '')) !== $date
            || $this->id($row['Student_ID'] ?? '') === '') {
            return false;
        }

        $type = strtoupper(trim((string) ($row['Attendance_Type'] ?? '')));
        $storedClass = $this->id($row['Class_ID'] ?? '');
        $legacyScope = $this->id($row['Schedule_ID'] ?? '');

        return in_array($type, ['', 'CLASS_QR', 'CLASS_MANUAL'], true)
            && ($storedClass === $this->id($classId) || ($storedClass === '' && $legacyScope === $this->id($classId)));
    }

    private function isActiveClassRequest(array $row, string $classId, string $date): bool
    {
        if (trim((string) ($row['Attendance_Date'] ?? '')) !== $date
            || $this->id($row['Student_ID'] ?? '') === '') {
            return false;
        }

        $type = strtoupper(trim((string) ($row['Attendance_Type'] ?? '')));
        $scheduleId = trim((string) ($row['Schedule_ID'] ?? ''));
        if ($type === '') {
            $type = $scheduleId === '' ? self::ATTENDANCE_TYPE : 'SCHEDULE';
        }
        $requestClass = $this->id($row['Class_ID'] ?? '');

        return $type === self::ATTENDANCE_TYPE
            && ($requestClass === '' || $requestClass === $this->id($classId))
            && in_array($this->requestStatus($row), ['PENDING', 'APPROVED'], true);
    }

    private function requestStatus(array $row): string
    {
        $status = strtoupper(str_replace([' ', '-'], '_', trim((string) ($row['Status'] ?? 'PENDING'))));

        return in_array($status, ['', 'WAITING', 'WAITING_APPROVAL', 'WAITING_REVIEW', 'SUBMITTED'], true)
            ? 'PENDING'
            : $status;
    }

    /** @param list<string> $selectedIds */
    private function assertStillEligible(array $roster, array $selectedIds): void
    {
        $eligibleIds = collect($roster['eligible'])->map(fn (array $row): string => $this->id($row['Student_ID'] ?? ''));
        $invalid = collect($selectedIds)->reject(fn (string $studentId): bool => $eligibleIds->contains($studentId));
        if ($invalid->isEmpty()) {
            return;
        }

        $reasons = collect($roster['blocked'])
            ->filter(fn (array $row): bool => $invalid->contains($this->id($row['Student_ID'] ?? '')))
            ->map(fn (array $row): string => ($row['Full_Name'] ?? $row['Student_ID']).': '.($row['reason'] ?? 'sudah tidak dapat dicatat'))
            ->values()
            ->all();

        throw new Exception('Pencatatan dibatalkan karena data telah berubah. '.implode(' ', $reasons));
    }

    private function id(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
