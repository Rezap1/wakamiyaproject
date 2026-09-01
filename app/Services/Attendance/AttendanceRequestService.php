<?php

namespace App\Services\Attendance;

use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class AttendanceRequestService
{
    protected $requestRepo;
    protected $attendanceRepo;
    protected $activityLog;
    protected $studentRepo;
    protected $scheduleRepo;
    protected $notificationService;

    public function __construct(
        AttendanceRequestRepositoryInterface $requestRepo,
        AttendanceRepositoryInterface $attendanceRepo,
        ActivityLogService $activityLog,
        ?StudentRepositoryInterface $studentRepo = null,
        ?ScheduleRepositoryInterface $scheduleRepo = null,
        ?NotificationService $notificationService = null
    ) {
        $this->requestRepo = $requestRepo;
        $this->attendanceRepo = $attendanceRepo;
        $this->activityLog = $activityLog;
        $this->studentRepo = $studentRepo;
        $this->scheduleRepo = $scheduleRepo;
        $this->notificationService = $notificationService;
    }

    public function getAllPending()
    {
        return collect($this->requestRepo->fetchAll())->where('Status', 'PENDING')->values();
    }

    public function getAll()
    {
        return $this->requestRepo->fetchAll();
    }

    public function getStudentRequests($studentId)
    {
        return collect($this->requestRepo->findByStudent($studentId));
    }

    public function findById($requestId)
    {
        return $this->requestRepo->findById($requestId);
    }

    public function createRequest(array $data, $user)
    {
        $data['Attendance_Date'] = $this->normalizeDate($data['Attendance_Date'] ?? '');
        $target = $this->normalizeTarget($data);
        if ($this->studentRepo && $user && isset($user->User_ID)) {
            $student = collect($this->studentRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            $studentId = trim((string) ($student['Student_ID'] ?? ''));
            $studentClassId = trim((string) ($student['Class_ID'] ?? ''));
            if (!$student || $studentId === '' || $studentClassId === '' || $studentId !== ($data['Student_ID'] ?? '')) {
                throw new Exception('Student request tidak sesuai dengan akun yang sedang login.');
            }
            if ($target['Attendance_Type'] === 'CLASS_QR') {
                $target['Class_ID'] = $studentClassId;
            } elseif (!$this->scheduleRepo) {
                throw new Exception('Master schedule tidak tersedia.');
            } else {
                $schedule = collect($this->scheduleRepo->fetchAll())->firstWhere('Schedule_ID', $target['Schedule_ID']);
                if (!$schedule || trim((string) ($schedule['Class_ID'] ?? '')) !== $studentClassId) {
                    throw new Exception('Jadwal tidak valid atau bukan milik kelas student.');
                }
                $target['Class_ID'] = $studentClassId;
            }
        }

        // 1. Validation for Duplicate Request
        $existing = $this->getStudentRequests($data['Student_ID'])->first(function ($item) use ($data, $target) {
            if (($item['Student_ID'] ?? '') !== ($data['Student_ID'] ?? '')
                || ($item['Attendance_Date'] ?? '') !== ($data['Attendance_Date'] ?? '')) {
                return false;
            }

            $itemTarget = $this->normalizeTarget($item, false);
            return $itemTarget['Attendance_Type'] === $target['Attendance_Type']
                && ($target['Attendance_Type'] === 'CLASS_QR'
                    ? (($itemTarget['Class_ID'] ?? '') === ''
                        || ($itemTarget['Class_ID'] ?? '') === ($target['Class_ID'] ?? ''))
                    : ($itemTarget['Schedule_ID'] ?? '') === ($target['Schedule_ID'] ?? ''));
        });
        if ($existing) {
            if (in_array($existing['Status'], ['PENDING', 'APPROVED'])) {
                throw new Exception("Anda sudah memiliki pengajuan aktif untuk presensi ini.");
            }
        }

        // 2. Insert Request
        $requestId = $this->requestRepo->generateNewId('REQ', 7);
        $insertData = [
            'Request_ID' => $requestId,
            'Attendance_ID' => $data['Attendance_ID'],
            'Student_ID' => $data['Student_ID'],
            'Class_ID' => $target['Class_ID'],
            'Schedule_ID' => $target['Schedule_ID'],
            'Attendance_Type' => $target['Attendance_Type'],
            'Attendance_Date' => $data['Attendance_Date'],
            'Request_Type' => $data['Request_Type'], // SAKIT or IZIN
            'Reason' => $data['Reason'],
            'Evidence_URL' => $data['Evidence_URL'],
            'Status' => 'PENDING',
            'Academic_Notes' => '',
            'Reviewed_By' => '',
            'Reviewed_At' => '',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
        ];

        $result = $this->requestRepo->create($insertData);
        $this->requestRepo->clearCache();

        // A pending request is already a valid attendance exception. Persist it
        // immediately so future dates cannot be marked PRESENT/ABSENT before review.
        $attendanceScope = $target['Attendance_Type'] === 'CLASS_QR' ? $target['Class_ID'] : $target['Schedule_ID'];
        $attendanceLockKey = 'attendance_request_sync_' . md5(implode('|', [
            $insertData['Student_ID'], $insertData['Attendance_Date'], $target['Attendance_Type'], $attendanceScope,
        ]));
        Cache::lock($attendanceLockKey, 15)->block(10, function () use ($insertData, $target) {
            $existingAttendance = $this->resolveOfficialAttendance($insertData, $target);
            $this->syncOfficialAttendance($insertData, $target, $insertData['Request_Type'], $existingAttendance);
        });

        // Keep the existing approval semantics, but make a newly submitted
        // request visible to Master immediately.
        if ($this->notificationService) {
            try {
                $this->notificationService->NotifyRole(
                    'MASTER',
                    'PENGAJUAN IZIN SISWA BARU',
                    "Pengajuan {$insertData['Request_ID']} untuk tanggal {$insertData['Attendance_Date']} menunggu review.",
                    'ATTENDANCE',
                    'Normal',
                    '/academic/attendance/requests/' . $insertData['Request_ID'],
                    $user->User_ID ?? null
                );
            } catch (\Throwable $notificationFailure) {
                Log::warning('Failed to notify Master about attendance request', [
                    'request_id' => $requestId,
                    'exception' => get_class($notificationFailure),
                ]);
            }
        }

        // 3. Audit Log
        $this->activityLog->log(
            'ATTENDANCE',
            'ATTENDANCE_REQUEST_CREATED',
            "Student {$data['Student_ID']} created request {$requestId}",
            null,
            ['Request_ID' => $requestId, 'Student_ID' => $data['Student_ID']]
        );

        return $result;
    }

    private function normalizeDate($date): string
    {
        return Carbon::parse($date, config('app.timezone'))->toDateString();
    }

    public function approveRequest($requestId, $statusToApply, $notes, $user)
    {
        $request = $this->findById($requestId);
        if (!$request) {
            throw new Exception("Pengajuan tidak ditemukan.");
        }
        if ($request['Status'] !== 'PENDING') {
            throw new Exception("Pengajuan sudah diproses sebelumnya.");
        }

        if (!in_array($statusToApply, ['SAKIT', 'IZIN'])) {
            throw new Exception("Status approval harus berupa SAKIT atau IZIN.");
        }

        $target = $this->resolveRequestTarget($request);
        $existingAttendance = $this->resolveOfficialAttendance($request, $target);

        // Update Request Status
        $updateData = [
            'Status' => 'APPROVED',
            'Academic_Notes' => $notes,
            'Reviewed_By' => $user->User_ID,
            'Reviewed_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
        ];
        
        $this->requestRepo->update($requestId, $updateData);
        $this->requestRepo->clearCache();

        $this->syncOfficialAttendance($request, $target, $statusToApply, $existingAttendance);

        // Audit Log
        $this->activityLog->log(
            'ATTENDANCE',
            'ATTENDANCE_REQUEST_APPROVED',
            "Academic {$user->User_ID} approved request {$requestId} as {$statusToApply}",
            null,
            ['Request_ID' => $requestId, 'Student_ID' => $request['Student_ID'], 'New_Status' => 'APPROVED', 'Notes' => $notes]
        );

        return true;
    }

    public function rejectRequest($requestId, $notes, $user)
    {
        $request = $this->findById($requestId);
        if (!$request) {
            throw new Exception("Pengajuan tidak ditemukan.");
        }
        if ($request['Status'] !== 'PENDING') {
            throw new Exception("Pengajuan sudah diproses sebelumnya.");
        }

        if (empty(trim($notes))) {
            throw new Exception("Alasan penolakan wajib diisi.");
        }

        $target = $this->resolveRequestTarget($request);
        $existingAttendance = $this->resolveOfficialAttendance($request, $target);

        // Update Request Status
        $updateData = [
            'Status' => 'REJECTED',
            'Academic_Notes' => $notes,
            'Reviewed_By' => $user->User_ID,
            'Reviewed_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
        ];
        
        $this->requestRepo->update($requestId, $updateData);
        $this->requestRepo->clearCache();

        $this->syncOfficialAttendance($request, $target, 'ALPA', $existingAttendance);

        // Audit Log
        $this->activityLog->log(
            'ATTENDANCE',
            'ATTENDANCE_REQUEST_REJECTED',
            "Academic {$user->User_ID} rejected request {$requestId}",
            null,
            ['Request_ID' => $requestId, 'Student_ID' => $request['Student_ID'], 'New_Status' => 'REJECTED', 'Notes' => $notes]
        );

        return true;
    }

    /**
     * Normalize a request target without treating client-provided type/class
     * values as authority. Controllers resolve these values from the student
     * and schedule masters before calling this service.
     */
    private function normalizeTarget(array $data, bool $strict = true): array
    {
        $type = strtoupper(trim((string) ($data['Attendance_Type'] ?? '')));
        $classId = trim((string) ($data['Class_ID'] ?? ''));
        $scheduleId = trim((string) ($data['Schedule_ID'] ?? ''));

        if ($type === '') {
            $type = $scheduleId === '' ? 'CLASS_QR' : 'SCHEDULE';
        }

        if ($type === 'CLASS_MANUAL') {
            $type = 'CLASS_QR';
        }

        if (!in_array($type, ['CLASS_QR', 'SCHEDULE'], true)) {
            throw new Exception('Tipe target attendance tidak valid.');
        }

        if ($type === 'CLASS_QR') {
            if ($strict && $classId === '') {
                throw new Exception('Kelas student wajib tersedia untuk request class-based.');
            }
            return ['Attendance_Type' => 'CLASS_QR', 'Class_ID' => $classId, 'Schedule_ID' => ''];
        }

        if ($scheduleId === '') {
            throw new Exception('Schedule_ID wajib untuk request schedule-based.');
        }

        return ['Attendance_Type' => 'SCHEDULE', 'Class_ID' => $classId, 'Schedule_ID' => $scheduleId];
    }

    private function resolveRequestTarget(array $request): array
    {
        $studentId = trim((string) ($request['Student_ID'] ?? ''));
        if ($studentId === '' || !$this->studentRepo || !$this->scheduleRepo) {
            throw new Exception('Identitas student atau master schedule tidak tersedia.');
        }

        $student = collect($this->studentRepo->fetchAll())->firstWhere('Student_ID', $studentId);
        $studentClassId = trim((string) ($student['Class_ID'] ?? ''));
        if (!$student || $studentClassId === '') {
            throw new Exception('Student tidak memiliki mapping kelas yang valid.');
        }

        $target = $this->normalizeTarget($request, false);
        if ($target['Attendance_Type'] === 'CLASS_QR') {
            $requestedClassId = trim((string) ($request['Class_ID'] ?? ''));
            if ($requestedClassId !== '' && $requestedClassId !== $studentClassId) {
                throw new Exception('Kelas request tidak sesuai dengan kelas student.');
            }
            $target['Class_ID'] = $studentClassId;
            $target['Schedule_ID'] = '';
            return $target;
        }

        $schedule = collect($this->scheduleRepo->fetchAll())->firstWhere('Schedule_ID', $target['Schedule_ID']);
        if (!$schedule || trim((string) ($schedule['Class_ID'] ?? '')) !== $studentClassId) {
            throw new Exception('Jadwal tidak valid atau bukan milik kelas student.');
        }

        $target['Class_ID'] = $studentClassId;
        return $target;
    }

    private function syncOfficialAttendance(array $request, array $target, string $status, ?array $attendance = null): void
    {
        $attendanceId = trim((string) ($request['Attendance_ID'] ?? ''));

        if ($attendance) {
            $this->attendanceRepo->update($attendance['Attendance_ID'], [
                'Status' => $status,
                'Updated_At' => now()->toDateTimeString(),
            ]);
            $this->attendanceRepo->clearCache();
            return;
        }

        $this->attendanceRepo->create([
            'Attendance_ID' => $attendanceId !== '' ? $attendanceId : $this->deterministicAttendanceId($request, $target),
            'Student_ID' => $request['Student_ID'],
            'Class_ID' => $target['Class_ID'],
            'Schedule_ID' => $target['Schedule_ID'],
            'Attendance_Type' => $target['Attendance_Type'],
            'Attendance_Date' => $request['Attendance_Date'],
            'Status' => $status,
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Is_Active' => 'TRUE',
        ]);
        $this->attendanceRepo->clearCache();
    }

    private function resolveOfficialAttendance(array $request, array $target): ?array
    {
        $attendanceId = trim((string) ($request['Attendance_ID'] ?? ''));
        if ($attendanceId !== '') {
            $selected = $this->attendanceRepo->findById($attendanceId);
            if ($selected) {
                $this->assertAttendanceIdentity($selected, $request, $target);
                return $selected;
            }
        }

        return collect($this->attendanceRepo->fetchAll())->first(function ($item) use ($request, $target) {
            return $this->attendanceMatchesTarget($item, $request, $target);
        });
    }

    private function assertAttendanceIdentity(array $attendance, array $request, array $target): void
    {
        if (($attendance['Student_ID'] ?? '') !== ($request['Student_ID'] ?? '')
            || ($attendance['Attendance_Date'] ?? '') !== ($request['Attendance_Date'] ?? '')) {
            throw new Exception('Attendance_ID bukan milik student atau tanggal request.');
        }

        $attendanceType = strtoupper(trim((string) ($attendance['Attendance_Type'] ?? '')));
        if ($attendanceType !== $target['Attendance_Type']) {
            throw new Exception('Attendance_ID tidak sesuai tipe attendance request.');
        }

        if ($target['Attendance_Type'] === 'CLASS_QR'
            && trim((string) ($attendance['Class_ID'] ?? '')) !== $target['Class_ID']) {
            throw new Exception('Attendance_ID tidak sesuai kelas student.');
        }

        if ($target['Attendance_Type'] === 'SCHEDULE'
            && trim((string) ($attendance['Schedule_ID'] ?? '')) !== $target['Schedule_ID']) {
            throw new Exception('Attendance_ID tidak sesuai jadwal request.');
        }
    }

    private function attendanceMatchesTarget(array $attendance, array $request, array $target): bool
    {
        if (($attendance['Student_ID'] ?? '') !== ($request['Student_ID'] ?? '')
            || ($attendance['Attendance_Date'] ?? '') !== ($request['Attendance_Date'] ?? '')
            || strtoupper(trim((string) ($attendance['Is_Active'] ?? 'TRUE'))) === 'FALSE'
            || strtoupper(trim((string) ($attendance['Attendance_Type'] ?? ''))) !== $target['Attendance_Type']) {
            return false;
        }

        return $target['Attendance_Type'] === 'CLASS_QR'
            ? trim((string) ($attendance['Class_ID'] ?? '')) === $target['Class_ID']
            : trim((string) ($attendance['Schedule_ID'] ?? '')) === $target['Schedule_ID'];
    }

    private function deterministicAttendanceId(array $request, array $target): string
    {
        $scope = $target['Attendance_Type'] === 'CLASS_QR' ? $target['Class_ID'] : $target['Schedule_ID'];
        $parts = [$request['Student_ID'] ?? '', $request['Attendance_Date'] ?? '', $scope, $target['Attendance_Type']];
        $safe = array_map(fn ($part) => preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $part), $parts);
        if ($target['Attendance_Type'] === 'SCHEDULE') {
            return 'ATT-' . $safe[0] . '-' . preg_replace('/[^0-9]/', '', (string) ($request['Attendance_Date'] ?? '')) . '-' . $safe[2];
        }
        return 'ATT-' . implode('-', $safe);
    }
}
