<?php

namespace App\Services\Attendance;

use App\Helpers\AttendanceStatusHelper;
use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use App\Support\Academic\TeacherScopeResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;
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
    protected $teacherRepo;
    protected $classRepo;
    protected $teacherScopeResolver;

    public function __construct(
        AttendanceRequestRepositoryInterface $requestRepo,
        AttendanceRepositoryInterface $attendanceRepo,
        ActivityLogService $activityLog,
        ?StudentRepositoryInterface $studentRepo = null,
        ?ScheduleRepositoryInterface $scheduleRepo = null,
        ?NotificationService $notificationService = null,
        ?TeacherRepositoryInterface $teacherRepo = null,
        ?ClassRepositoryInterface $classRepo = null,
        ?TeacherScopeResolver $teacherScopeResolver = null
    ) {
        $this->requestRepo = $requestRepo;
        $this->attendanceRepo = $attendanceRepo;
        $this->activityLog = $activityLog;
        $this->studentRepo = $studentRepo;
        $this->scheduleRepo = $scheduleRepo;
        $this->notificationService = $notificationService;
        $this->teacherRepo = $teacherRepo;
        $this->classRepo = $classRepo;
        $this->teacherScopeResolver = $teacherScopeResolver;
    }

    public function getAllPending()
    {
        return collect($this->requestRepo->fetchAll())
            ->filter(fn ($row) => $this->isPendingRequestStatus($row['Status'] ?? ''))
            ->values();
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

    public function getTeacherRequests($user)
    {
        $scope = $this->teacherScopeForUser($user);

        return collect($this->requestRepo->fetchAll())
            ->filter(fn ($request) => $this->requestAllowedForTeacherFromSnapshot($request, $scope))
            ->values();
    }

    public function assertTeacherCanReviewRequest($requestId, $user): array
    {
        $request = $this->findById($requestId);
        if (!$request) {
            throw new Exception('Pengajuan tidak ditemukan.');
        }

        $scope = $this->teacherScopeForUser($user);
        if (!$this->requestAllowedForTeacherFromSnapshot($request, $scope)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk memproses pengajuan ini.');
        }

        return $request;
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

        $attendanceScope = $target['Attendance_Type'] === 'CLASS_QR' ? $target['Class_ID'] : $target['Schedule_ID'];
        $createLockKey = 'attendance_request_create_' . md5(implode('|', [
            $data['Student_ID'] ?? '',
            $data['Attendance_Date'] ?? '',
            $target['Attendance_Type'],
            $attendanceScope,
        ]));

        return Cache::lock($createLockKey, 15)->block(10, function () use ($data, $target, $attendanceScope, $user) {
            $existing = $this->getStudentRequests($data['Student_ID'])->first(function ($item) use ($data, $target) {
                if (($item['Student_ID'] ?? '') !== ($data['Student_ID'] ?? '')
                    || ($item['Attendance_Date'] ?? '') !== ($data['Attendance_Date'] ?? '')) {
                    return false;
                }

                try {
                    $itemTarget = $this->normalizeTarget($item, false);
                } catch (\Throwable) {
                    return false;
                }

                return $itemTarget['Attendance_Type'] === $target['Attendance_Type']
                    && ($target['Attendance_Type'] === 'CLASS_QR'
                        ? (($itemTarget['Class_ID'] ?? '') === ''
                            || ($itemTarget['Class_ID'] ?? '') === ($target['Class_ID'] ?? ''))
                        : ($itemTarget['Schedule_ID'] ?? '') === ($target['Schedule_ID'] ?? ''));
            });

            if ($existing && $this->isActiveDuplicateStatus($existing['Status'] ?? '')) {
                throw new Exception('Anda sudah memiliki pengajuan aktif untuk presensi ini.');
            }

            $requestType = strtoupper(trim((string) ($data['Request_Type'] ?? '')));
            if (!in_array($requestType, ['SAKIT', 'IZIN'], true)) {
                throw new Exception('Tipe pengajuan harus SAKIT atau IZIN.');
            }

            $requestId = $this->requestRepo->generateNewId('REQ', 7);
            $insertData = [
                'Request_ID' => $requestId,
                'Attendance_ID' => $data['Attendance_ID'],
                'Student_ID' => $data['Student_ID'],
                'Class_ID' => $target['Class_ID'],
                'Schedule_ID' => $target['Schedule_ID'],
                'Attendance_Type' => $target['Attendance_Type'],
                'Attendance_Date' => $data['Attendance_Date'],
                'Request_Type' => $requestType,
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
            if ($result === false) {
                throw new Exception('Pengajuan gagal disimpan.');
            }
            $this->requestRepo->clearCache();

            if ($this->notificationService) {
                try {
                    $this->notificationService->NotifyRole(
                        'MASTER',
                        'PENGAJUAN IZIN SISWA BARU',
                        "Pengajuan {$insertData['Request_ID']} untuk tanggal {$insertData['Attendance_Date']} menunggu review guru.",
                        'ATTENDANCE',
                        'Normal',
                        '/academic/attendance/requests/' . $insertData['Request_ID'],
                        $this->actorId($user)
                    );
                } catch (\Throwable $notificationFailure) {
                    Log::warning('Failed to notify Master about attendance request', [
                        'request_id' => $requestId,
                        'exception' => get_class($notificationFailure),
                    ]);
                }
            }

            $this->activityLog->log(
                'ATTENDANCE',
                'ATTENDANCE_REQUEST_CREATED',
                "Student {$data['Student_ID']} created request {$requestId}",
                null,
                ['Request_ID' => $requestId, 'Student_ID' => $data['Student_ID']]
            );

            return $result;
        });
    }

    private function normalizeDate($date): string
    {
        return Carbon::parse($date, config('app.timezone'))->toDateString();
    }

    public function approveRequest($requestId, $statusToApply, $notes, $user)
    {
        $statusToApply = strtoupper(trim((string) $statusToApply));
        if (!in_array($statusToApply, ['SAKIT', 'IZIN'], true)) {
            throw new Exception('Status approval harus berupa SAKIT atau IZIN.');
        }

        return Cache::lock('attendance_request_status_' . md5((string) $requestId), 15)->block(10, function () use ($requestId, $statusToApply, $notes, $user) {
            $request = $this->assertTeacherCanReviewRequest($requestId, $user);
            $currentStatus = $this->canonicalRequestStatus($request['Status'] ?? '');
            $target = $this->resolveRequestTarget($request);
            $existingAttendance = $this->resolveOfficialAttendance($request, $target);

            if ($currentStatus === 'APPROVED') {
                if ($existingAttendance && !$this->attendanceStatusMatches($existingAttendance['Status'] ?? '', $statusToApply)) {
                    throw new Exception('Pengajuan sudah disetujui dengan status presensi berbeda.');
                }

                $this->syncOfficialAttendance($request, $target, $statusToApply, $existingAttendance);
                return true;
            }

            if ($currentStatus === 'REJECTED') {
                throw new Exception('Pengajuan sudah ditolak sebelumnya.');
            }

            $updateData = [
                'Status' => 'APPROVED',
                'Academic_Notes' => $notes,
                'Reviewed_By' => $this->actorId($user),
                'Reviewed_At' => now()->toDateTimeString(),
                'Updated_At' => now()->toDateTimeString(),
            ];

            $updated = $this->requestRepo->update($requestId, $updateData);
            if ($updated === false) {
                throw new Exception('Status pengajuan gagal diperbarui.');
            }
            $this->requestRepo->clearCache();

            $this->syncOfficialAttendance($request, $target, $statusToApply, $existingAttendance);

            $this->notifyStudentDecision($request, 'APPROVED', $statusToApply, (string) $notes, $user);

            $this->activityLog->log(
                'ATTENDANCE',
                'ATTENDANCE_REQUEST_APPROVED',
                'Teacher ' . $this->actorId($user) . " approved request {$requestId} as {$statusToApply}",
                null,
                ['Request_ID' => $requestId, 'Student_ID' => $request['Student_ID'], 'New_Status' => 'APPROVED', 'Notes' => $notes]
            );

            return true;
        });
    }

    public function rejectRequest($requestId, $notes, $user)
    {
        if (empty(trim($notes))) {
            throw new Exception('Alasan penolakan wajib diisi.');
        }

        return Cache::lock('attendance_request_status_' . md5((string) $requestId), 15)->block(10, function () use ($requestId, $notes, $user) {
            $request = $this->assertTeacherCanReviewRequest($requestId, $user);
            $currentStatus = $this->canonicalRequestStatus($request['Status'] ?? '');
            $target = $this->resolveRequestTarget($request);
            $existingAttendance = $this->resolveOfficialAttendance($request, $target);

            if ($currentStatus === 'REJECTED') {
                $this->syncOfficialAttendance($request, $target, 'ALPA', $existingAttendance);
                return true;
            }

            if ($currentStatus === 'APPROVED') {
                throw new Exception('Pengajuan sudah disetujui sebelumnya.');
            }

            $updateData = [
                'Status' => 'REJECTED',
                'Academic_Notes' => $notes,
                'Reviewed_By' => $this->actorId($user),
                'Reviewed_At' => now()->toDateTimeString(),
                'Updated_At' => now()->toDateTimeString(),
            ];

            $updated = $this->requestRepo->update($requestId, $updateData);
            if ($updated === false) {
                throw new Exception('Status pengajuan gagal diperbarui.');
            }
            $this->requestRepo->clearCache();

            $this->syncOfficialAttendance($request, $target, 'ALPA', $existingAttendance);

            $this->notifyStudentDecision($request, 'REJECTED', 'ALPA', (string) $notes, $user);

            $this->activityLog->log(
                'ATTENDANCE',
                'ATTENDANCE_REQUEST_REJECTED',
                'Teacher ' . $this->actorId($user) . " rejected request {$requestId}",
                null,
                ['Request_ID' => $requestId, 'Student_ID' => $request['Student_ID'], 'New_Status' => 'REJECTED', 'Notes' => $notes]
            );

            return true;
        });
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
            if ($this->attendanceStatusMatches($attendance['Status'] ?? '', $status)) {
                return;
            }

            $updated = $this->attendanceRepo->update($attendance['Attendance_ID'], [
                'Status' => $status,
                'Updated_At' => now()->toDateTimeString(),
            ]);
            if ($updated === false) {
                throw new Exception('Data presensi resmi gagal diperbarui.');
            }
            $this->attendanceRepo->clearCache();
            return;
        }

        $created = $this->attendanceRepo->create([
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
        if ($created === false) {
            throw new Exception('Data presensi resmi gagal disimpan.');
        }
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

    private function teacherScopeForUser($user): array
    {
        if (!$this->isTeacherActor($user)) {
            throw new AuthorizationException('Hanya guru yang dapat memproses pengajuan izin/sakit siswa.');
        }

        if (!$this->teacherRepo || !$this->studentRepo || !$this->scheduleRepo || !$this->classRepo) {
            throw new AuthorizationException('Master scope guru tidak tersedia.');
        }

        $userId = $this->actorId($user);
        $teacher = collect($this->teacherRepo->fetchAll())->firstWhere('User_ID', $userId);
        $teacherId = trim((string) ($teacher['Teacher_ID'] ?? ''));
        if (!$teacher || $teacherId === '') {
            throw new AuthorizationException('Profil guru tidak ditemukan.');
        }

        if ($this->teacherScopeResolver) {
            $resolved = $this->teacherScopeResolver->resolveForTeacherId($teacherId);

            return [
                'teacher_id' => $teacherId,
                'schedule_ids' => collect($resolved['schedule_ids'] ?? []),
                'class_ids' => collect($resolved['class_ids'] ?? []),
                'students_by_id' => collect($resolved['students'] ?? collect())->keyBy('Student_ID'),
                'schedules_by_id' => collect($resolved['schedules'] ?? collect())->keyBy('Schedule_ID'),
                'students_by_class' => $resolved['students_by_class'] ?? [],
            ];
        }

        $schedules = collect($this->scheduleRepo->fetchAll());
        $classes = collect($this->classRepo->fetchAll());
        $students = collect($this->studentRepo->fetchAll());
        $teacherSchedules = $schedules
            ->where('Teacher_ID', $teacherId)
            ->filter(fn ($schedule) => strtoupper(trim((string) ($schedule['Is_Active'] ?? 'TRUE'))) !== 'FALSE')
            ->values();
        $scheduleClassIds = $teacherSchedules->pluck('Class_ID')->filter()->unique();
        $activeClassIds = $classes
            ->filter(fn ($class) => strtoupper(trim((string) ($class['Is_Active'] ?? 'TRUE'))) !== 'FALSE')
            ->pluck('Class_ID')
            ->filter()
            ->unique();
        $classIds = $scheduleClassIds->intersect($activeClassIds)->filter()->unique()->values();
        $studentsByClass = [];
        foreach ($students as $student) {
            $classId = trim((string) ($student['Class_ID'] ?? ''));
            $studentId = trim((string) ($student['Student_ID'] ?? ''));
            if ($studentId !== '' && $classIds->contains($classId)) {
                $studentsByClass[$classId][] = $studentId;
            }
        }

        return [
            'teacher_id' => $teacherId,
            'schedule_ids' => $teacherSchedules->pluck('Schedule_ID')->filter()->unique()->values(),
            'class_ids' => $classIds,
            'students_by_id' => $students->keyBy('Student_ID'),
            'schedules_by_id' => $schedules->keyBy('Schedule_ID'),
            'students_by_class' => collect($studentsByClass)->map(fn ($ids) => array_values(array_unique($ids)))->all(),
        ];
    }

    private function requestAllowedForTeacherFromSnapshot(array $request, array $scope): bool
    {
        $studentId = trim((string) ($request['Student_ID'] ?? ''));
        $student = $scope['students_by_id']->get($studentId);
        if (!$student) {
            return false;
        }

        $studentClassIds = collect($scope['students_by_class'] ?? [])
            ->filter(fn ($studentIds) => in_array($studentId, $studentIds, true))
            ->keys()
            ->values();
        $directClassId = trim((string) ($student['Class_ID'] ?? ''));
        if ($directClassId !== '' && $scope['class_ids']->contains($directClassId)) {
            $studentClassIds = $studentClassIds->push($directClassId)->unique()->values();
        }
        if ($studentClassIds->isEmpty()) {
            return false;
        }

        try {
            $target = $this->normalizeTarget($request, false);
        } catch (\Throwable) {
            return false;
        }

        if ($target['Attendance_Type'] === 'CLASS_QR') {
            $requestedClassId = trim((string) ($request['Class_ID'] ?? ''));
            return $requestedClassId === '' || $studentClassIds->contains($requestedClassId);
        }

        $schedule = $scope['schedules_by_id']->get($target['Schedule_ID'] ?? '');
        if (!$schedule) {
            return false;
        }

        return $scope['schedule_ids']->contains($target['Schedule_ID'] ?? '')
            && $studentClassIds->contains(trim((string) ($schedule['Class_ID'] ?? '')));
    }

    private function isTeacherActor($user): bool
    {
        $role = $this->roleNameForUser($user);

        return str_contains($role, 'TEACHER') || str_contains($role, 'GURU');
    }

    private function roleNameForUser($user): string
    {
        if (!$user) {
            return '';
        }

        $role = strtoupper(trim((string) ($user->Role ?? $user->Role_Name ?? '')));
        if ($role !== '') {
            return $role;
        }

        $roleId = trim((string) ($user->Role_ID ?? ''));
        if ($roleId === '') {
            return '';
        }

        try {
            return strtoupper(trim((string) (app(\App\Services\Core\RoleService::class)->getRoleById($roleId)['Role_Name'] ?? '')));
        } catch (\Throwable) {
            return '';
        }
    }

    private function actorId($user): string
    {
        return trim((string) ($user->User_ID ?? $user->Email ?? $user->email ?? ''));
    }

    private function isPendingRequestStatus(?string $status): bool
    {
        try {
            return $this->canonicalRequestStatus($status) === 'PENDING';
        } catch (\Throwable) {
            return false;
        }
    }

    private function isActiveDuplicateStatus(?string $status): bool
    {
        try {
            return in_array($this->canonicalRequestStatus($status), ['PENDING', 'APPROVED'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function canonicalRequestStatus(?string $status): string
    {
        $value = strtoupper(trim((string) $status));
        $value = str_replace([' ', '-'], '_', $value);

        if ($value === '' || in_array($value, ['PENDING', 'WAITING', 'WAITING_APPROVAL', 'WAITING_REVIEW', 'SUBMITTED'], true)) {
            return 'PENDING';
        }

        if (in_array($value, ['APPROVED', 'REJECTED'], true)) {
            return $value;
        }

        throw new Exception('Status pengajuan tidak valid.');
    }

    private function attendanceStatusMatches(?string $current, string $expected): bool
    {
        return AttendanceStatusHelper::normalize($current, '') === AttendanceStatusHelper::normalize($expected, '');
    }

    private function notifyStudentDecision(array $request, string $decision, string $attendanceStatus, string $notes, $user): void
    {
        if (!$this->notificationService || !$this->studentRepo) {
            return;
        }

        $student = collect($this->studentRepo->fetchAll())->firstWhere('Student_ID', $request['Student_ID'] ?? '');
        $studentUserId = trim((string) ($student['User_ID'] ?? ''));
        if ($studentUserId === '') {
            return;
        }

        $isApproved = $decision === 'APPROVED';
        $statusLabel = AttendanceStatusHelper::label($attendanceStatus);
        $date = $request['Attendance_Date'] ?? '-';
        $message = $isApproved
            ? "Pengajuan presensi tanggal {$date} disetujui sebagai {$statusLabel}."
            : "Pengajuan presensi tanggal {$date} ditolak. Status presensi menjadi {$statusLabel}.";
        if (trim($notes) !== '') {
            $message .= ' Catatan: ' . trim($notes);
        }

        try {
            $this->notificationService->NotifyUser(
                $studentUserId,
                $isApproved ? 'PENGAJUAN PRESENSI DISETUJUI' : 'PENGAJUAN PRESENSI DITOLAK',
                $message,
                'ATTENDANCE',
                'Normal',
                '/student/attendance/requests',
                $this->actorId($user)
            );
        } catch (\Throwable $notificationFailure) {
            Log::warning('Failed to notify student about attendance request decision', [
                'request_id' => $request['Request_ID'] ?? null,
                'student_id' => $request['Student_ID'] ?? null,
                'exception' => get_class($notificationFailure),
            ]);
        }
    }
}
