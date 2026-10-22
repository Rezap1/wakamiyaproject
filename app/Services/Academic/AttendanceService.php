<?php

namespace App\Services\Academic;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Helpers\AttendanceStatusHelper;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Repositories\MySql\BaseMySqlRepository;
use App\Services\Core\EnterpriseEventService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    protected $repository;

    protected $enterpriseEvent;

    protected $legacyClassifier;

    public function __construct(AttendanceRepositoryInterface $repository, EnterpriseEventService $enterpriseEvent, ?AttendanceLegacyClassifier $legacyClassifier = null)
    {
        $this->repository = $repository;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->legacyClassifier = $legacyClassifier ?: new AttendanceLegacyClassifier;
    }

    public function getAll()
    {
        return $this->repository->fetchAll();
    }

    /**
     * Build the Academic Attendance read model from the class roster.
     *
     * The roster is deliberately the base dataset so students without an
     * attendance row are represented as "Belum Absen". Attendance rows are
     * indexed in memory after their class has been resolved according to the
     * persisted attendance type.
     */
    public function buildClassAttendanceGroups(
        $classes,
        $students,
        $attendances,
        $schedules,
        ?string $dateFilter = null,
        ?string $dateEndFilter = null,
        ?string $classFilter = null,
        ?string $statusFilter = null,
        ?string $search = null
    ): Collection {
        $classes = collect($classes);
        $students = collect($students);
        $attendances = collect($attendances);
        $schedules = collect($schedules)->keyBy(fn ($schedule) => trim((string) ($schedule['Schedule_ID'] ?? '')));

        $activeClasses = $classes
            ->filter(function ($class) {
                $isActive = strtoupper(trim((string) ($class['Is_Active'] ?? '')));

                return ($isActive === 'TRUE' || $isActive === '')
                    && trim((string) ($class['Class_ID'] ?? '')) !== '';
            })
            ->values();
        $classesById = $activeClasses->keyBy(fn ($class) => trim((string) $class['Class_ID']));

        $classFilter = trim((string) $classFilter);
        if ($classFilter !== '' && ! $classesById->has($classFilter)) {
            return collect();
        }

        $search = strtolower(trim((string) $search));
        $studentsByClass = $students
            ->filter(function ($student) use ($classesById) {
                $studentId = trim((string) ($student['Student_ID'] ?? ''));
                $classId = trim((string) ($student['Class_ID'] ?? ''));
                $isActive = strtoupper(trim((string) ($student['Is_Active'] ?? 'TRUE')));

                return $studentId !== ''
                    && $classId !== ''
                    && $classesById->has($classId)
                    && $isActive !== 'FALSE';
            })
            ->groupBy(fn ($student) => trim((string) $student['Class_ID']));

        $attendanceByStudentClass = [];
        foreach ($attendances as $attendance) {
            $studentId = trim((string) ($attendance['Student_ID'] ?? ''));
            $attendanceType = strtoupper(trim((string) ($attendance['Attendance_Type'] ?? '')));
            $isEmployee = $attendanceType === 'EMPLOYEE' || $studentId === '';
            if ($isEmployee) {
                continue;
            }

            $attendanceDate = trim((string) ($attendance['Attendance_Date'] ?? ''));
            if ($attendanceDate === '') {
                continue;
            }

            try {
                $normalizedDate = Carbon::parse(str_replace('/', '-', $attendanceDate))->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }

            if ($dateEndFilter !== null && $dateEndFilter !== '') {
                if ($normalizedDate < (string) $dateFilter || $normalizedDate > $dateEndFilter) {
                    continue;
                }
            } elseif ($dateFilter !== null && $dateFilter !== '' && $normalizedDate !== $dateFilter) {
                continue;
            }

            $classified = $this->legacyClassifier->classify($attendance, $activeClasses, $schedules);
            if (in_array($classified['classification'], ['EMPLOYEE', 'UNKNOWN', 'AMBIGUOUS'], true)) {
                continue;
            }
            $classId = trim((string) ($classified['class_id'] ?? ''));
            if ($classId === '' || ! $classesById->has($classId)) {
                continue;
            }
            if ($classFilter !== '' && $classId !== $classFilter) {
                continue;
            }

            $key = implode('|', [$studentId, $classId, $normalizedDate, $attendanceType]);
            $attendanceByStudentClass[$studentId.'|'.$classId][$key] = $attendance + [
                'Resolved_Class_ID' => $classId,
                'Resolved_Schedule_ID' => $classified['schedule_id'],
                'Original_Schedule_ID' => $classified['original_schedule_id'],
                'Legacy_Type' => $classified['classification'],
                'Normalized_Attendance_Date' => $normalizedDate,
            ];
        }

        $dateDisplay = (string) $dateFilter;
        if ($dateEndFilter !== null && $dateEndFilter !== '' && $dateFilter !== $dateEndFilter) {
            $dateDisplay .= ' - '.$dateEndFilter;
        }

        return $activeClasses
            ->filter(fn ($class) => $classFilter === '' || trim((string) $class['Class_ID']) === $classFilter)
            ->map(function ($class) use ($studentsByClass, $attendanceByStudentClass, $statusFilter, $search, $dateDisplay) {
                $classId = trim((string) $class['Class_ID']);
                $classStudents = $studentsByClass->get($classId, collect());
                $rows = [];

                foreach ($classStudents as $student) {
                    $studentId = trim((string) $student['Student_ID']);
                    $studentName = (string) ($student['Full_Name'] ?? $student['Name'] ?? 'Data siswa tidak ditemukan');
                    $studentNumber = trim((string) ($student['Student_Number'] ?? $student['NIS'] ?? ''));
                    if ($search !== ''
                        && ! str_contains(strtolower($studentId), $search)
                        && ! str_contains(strtolower($studentName), $search)) {
                        continue;
                    }

                    $candidates = collect(array_values($attendanceByStudentClass[$studentId.'|'.$classId] ?? []));
                    if ($statusFilter !== null && trim($statusFilter) !== '') {
                        $wantedStatus = AttendanceStatusHelper::normalize($statusFilter);
                        $candidates = $candidates->filter(fn ($attendance) => AttendanceStatusHelper::normalize($attendance['Status'] ?? '') === $wantedStatus
                        );
                    }
                    $attendance = $candidates->first();

                    if ($attendance === null && $statusFilter !== null && trim($statusFilter) !== '') {
                        continue;
                    }

                    $statusKey = $attendance === null
                        ? 'NOT_ATTENDED'
                        : AttendanceStatusHelper::normalize($attendance['Status'] ?? '');
                    $rows[] = [
                        'Student_ID' => $studentId,
                        'Student_Number' => $studentNumber,
                        'Student_Name' => $studentName,
                        'Class_ID' => $classId,
                        'Status' => $attendance['Status'] ?? '',
                        'Status_Key' => $statusKey,
                        'Display_Status' => $attendance === null
                            ? 'Belum Absen'
                            : AttendanceStatusHelper::label($attendance['Status'] ?? ''),
                        'Badge_Color' => $attendance === null
                            ? 'slate'
                            : AttendanceStatusHelper::badgeColor($attendance['Status'] ?? ''),
                        'Attendance' => $attendance,
                        'Attendance_ID' => $attendance['Attendance_ID'] ?? null,
                        'Check_In_Time' => $attendance['Check_In_Time'] ?? $attendance['Time_In'] ?? null,
                        'Check_Out_Time' => $attendance['Check_Out_Time'] ?? $attendance['Time_Out'] ?? null,
                        'Notes' => $attendance['Notes'] ?? null,
                    ];
                }

                $rows = collect($rows)->values();

                return [
                    'Class_ID' => $classId,
                    'Class_Code' => trim((string) ($class['Class_Code'] ?? '')),
                    'Class_Name' => (trim((string) ($class['Class_Name'] ?? '')) ?: 'Kelas tidak ditemukan')
                        .(! empty($class['Class_Code']) ? ' ('.$class['Class_Code'].')' : ''),
                    'Date_Display' => $dateDisplay,
                    'Total' => $rows->count(),
                    'Hadir' => $rows->where('Status_Key', 'PRESENT')->count(),
                    'Terlambat' => $rows->where('Status_Key', 'LATE')->count(),
                    'Sakit' => $rows->where('Status_Key', 'SICK')->count(),
                    'Izin' => $rows->where('Status_Key', 'PERMITTED')->count(),
                    'Alpha' => $rows->where('Status_Key', 'ABSENT')->count(),
                    'Belum_Absen' => $rows->where('Status_Key', 'NOT_ATTENDED')->count(),
                    'Students' => $rows,
                ];
            })
            ->values();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    public function generateId()
    {
        return $this->repository->generateNewId('ATT', 6);
    }

    public function openSession(array $data)
    {
        // $data contains: Schedule_ID, Teacher_ID, Attendance_Date, Semester, Academic_Year, Grace_Period
        // Validation: Teacher can only open attendance for today or past (not future)
        if (strtotime($data['Attendance_Date']) > strtotime(date('Y-m-d'))) {
            throw new Exception('Cannot open attendance for future dates.');
        }

        // Cannot open twice for the same schedule and date
        $existing = $this->getAll()->first(function ($item) use ($data) {
            return $item['Schedule_ID'] === ($data['Schedule_ID'] ?? '')
                && $item['Attendance_Date'] === ($data['Attendance_Date'] ?? '')
                && ($item['Session_Status'] ?? '') === 'OPEN';
        });

        if ($existing) {
            throw new Exception('Attendance session is already open for this schedule and date.');
        }

        // Simulating opening a session by inserting a master record (or just directly inserting absent default for all students)
        // Since we don't have a separate SESSION table, we'll assume "Open Session" inserts default 'Absent' for all enrolled students
        // We just return success and let the controller handle student list
        return true;
    }

    public function markAttendance(array $data)
    {
        $data = $this->normalizeAttendanceData($data);
        $studentId = trim((string) ($data['Student_ID'] ?? ''));
        $attendanceDate = trim((string) ($data['Attendance_Date'] ?? ''));
        if ($studentId === '' || $attendanceDate === '') {
            throw new Exception('Student_ID dan Attendance_Date wajib diisi untuk absensi siswa.');
        }

        $scope = $data['Class_ID'] ?? $data['Schedule_ID'] ?? '';
        $lockKey = 'student_attendance_'.sha1("{$studentId}|{$attendanceDate}|{$scope}");

        return Cache::lock($lockKey, 30)->block(5, function () use ($data) {
            return $this->runtimeTransaction(function () use ($data) {
                $existing = $this->findExistingStudentAttendance($data);

                if ($existing && ! empty($existing['Attendance_ID'])) {
                    $data['Updated_At'] = now()->toDateTimeString();
                    $result = $this->repository->update($existing['Attendance_ID'], $data);
                    $this->repository->clearCache();

                    return $result;
                }

                if (empty($data['Attendance_ID'])) {
                    $data['Attendance_ID'] = $this->generateId();
                }

                $data['Created_At'] = now()->toDateTimeString();
                $data['Updated_At'] = now()->toDateTimeString();
                $data['Is_Active'] = $data['Is_Active'] ?? 'TRUE';

                $result = $this->repository->create($data);
                $this->repository->clearCache();

                return $result;
            });
        });
    }

    /**
     * Record a MASTER fallback batch without changing any attendance that is
     * already official for the same student, class and date.
     *
     * @param  list<array{Student_ID:string,Status:string,Notes?:string}>  $students
     */
    public function recordManualBatch(
        string $classId,
        string $attendanceDate,
        array $students,
        string $actorId
    ): int {
        $classId = trim($classId);
        $attendanceDate = trim($attendanceDate);
        $actorId = trim($actorId);
        if ($classId === '' || $attendanceDate === '' || $actorId === '') {
            throw new Exception('Kelas, tanggal, dan identitas pencatat wajib tersedia.');
        }

        $normalized = collect($students)->map(function (array $student): array {
            $studentId = trim((string) ($student['Student_ID'] ?? ''));
            $status = AttendanceStatusHelper::normalize($student['Status'] ?? '', '');
            if ($studentId === '' || ! in_array($status, ['PRESENT', 'LATE', 'SICK', 'PERMITTED', 'ABSENT'], true)) {
                throw new Exception('Data siswa atau status absensi manual tidak valid.');
            }

            return [
                'Student_ID' => $studentId,
                'Status' => $status,
                'Notes' => trim((string) ($student['Notes'] ?? '')),
            ];
        })->values();

        if ($normalized->isEmpty() || $normalized->pluck('Student_ID')->unique()->count() !== $normalized->count()) {
            throw new Exception('Daftar absensi manual kosong atau memuat siswa ganda.');
        }

        $lockKey = 'manual_attendance_batch_'.sha1("{$classId}|{$attendanceDate}");

        try {
            $count = Cache::lock($lockKey, 30)->block(5, function () use ($normalized, $classId, $attendanceDate, $actorId): int {
                return $this->runtimeTransaction(function () use ($normalized, $classId, $attendanceDate, $actorId): int {
                    $existingStudentIds = collect($this->repository->fetchAll())
                        ->filter(fn (array $attendance): bool => $this->isActiveStudentClassAttendance(
                            $attendance,
                            $classId,
                            $attendanceDate
                        ))
                        ->pluck('Student_ID')
                        ->map(fn ($studentId): string => trim((string) $studentId))
                        ->filter()
                        ->unique()
                        ->values();

                    $conflicts = $normalized->pluck('Student_ID')->intersect($existingStudentIds)->values();
                    if ($conflicts->isNotEmpty()) {
                        throw new Exception(
                            'Absensi manual dibatalkan karena ada siswa yang sudah memiliki status pada tanggal tersebut.'
                        );
                    }

                    $now = now('Asia/Jakarta');
                    foreach ($normalized as $student) {
                        $studentId = $student['Student_ID'];
                        $status = $student['Status'];
                        $record = [
                            'Attendance_ID' => 'ATT-MAN-'.strtoupper(substr(hash('sha256', "{$studentId}|{$attendanceDate}|{$classId}|CLASS_DAILY"), 0, 20)),
                            'Student_ID' => $studentId,
                            'Class_ID' => $classId,
                            'Schedule_ID' => '',
                            // Manual MASTER is a fallback writer for the same
                            // daily class session as QR, not a parallel status.
                            'Attendance_Type' => 'CLASS_QR',
                            'Attendance_Date' => $attendanceDate,
                            'Status' => $status,
                            'Check_In_Time' => AttendanceStatusHelper::isPresentLike($status) ? $now->format('H:i:s') : '',
                            'Check_Out_Time' => '',
                            'Verification_Method' => 'MASTER_MANUAL',
                            'Notes' => $student['Notes'],
                            'Is_Active' => 'TRUE',
                            'Created_By' => $actorId,
                            'Updated_By' => $actorId,
                            'Created_At' => $now->toDateTimeString(),
                            'Updated_At' => $now->toDateTimeString(),
                        ];

                        try {
                            $created = $this->repository->create($record);
                        } catch (DuplicatePrimaryKeyException $exception) {
                            throw new Exception(
                                'Absensi manual berkonflik dengan permintaan lain yang baru diproses. Muat ulang daftar siswa.',
                                0,
                                $exception
                            );
                        }
                        if ($created === false || $created === null) {
                            throw new Exception('Absensi manual gagal disimpan. Tidak ada perubahan parsial yang dipertahankan.');
                        }
                    }

                    return $normalized->count();
                });
            });
        } catch (LockTimeoutException $exception) {
            throw new Exception('Absensi kelas sedang diproses oleh permintaan lain. Silakan muat ulang.', 0, $exception);
        }

        try {
            $this->enterpriseEvent->dispatch(
                'ACADEMIC',
                'CREATE',
                'MANUAL_ATTENDANCE_BATCH',
                $classId.'|'.$attendanceDate,
                $actorId,
                ['ACADEMIC', 'ADMINISTRATOR'],
                $normalized->pluck('Student_ID')->all(),
                ['Class_ID' => $classId, 'Attendance_Date' => $attendanceDate, 'Count' => $count]
            );
        } catch (\Throwable $failure) {
            Log::error('Manual attendance post-commit event failed', [
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'exception_class' => $failure::class,
            ]);
        }

        return $count;
    }

    private function runtimeTransaction(callable $callback)
    {
        return $this->repository instanceof BaseMySqlRepository
            ? $this->repository->transaction($callback)
            : $callback();
    }

    public function update($id, array $data)
    {
        $data = $this->normalizeAttendanceData($data, false);
        $data['Updated_At'] = now()->toDateTimeString();
        $result = $this->repository->update($id, $data);
        $this->repository->clearCache();

        return $result;
    }

    public function delete($id)
    {
        $result = $this->repository->delete($id);
        $this->repository->clearCache();

        return $result;
    }

    private function normalizeAttendanceData(array $data, bool $isCreate = true): array
    {
        if (array_key_exists('Status', $data)) {
            $data['Status'] = AttendanceStatusHelper::normalize($data['Status']);
        } elseif ($isCreate) {
            $data['Status'] = 'PRESENT';
        }

        foreach (['Student_ID', 'Employee_ID', 'User_ID', 'Class_ID', 'Schedule_ID', 'Teacher_ID', 'Notes'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $status = $data['Status'] ?? null;
        if ($isCreate && AttendanceStatusHelper::isPresentLike($status) && empty($data['Check_In_Time'])) {
            $data['Check_In_Time'] = now()->format('H:i:s');
        }

        if (! AttendanceStatusHelper::isPresentLike($status)) {
            $data['Check_In_Time'] = $data['Check_In_Time'] ?? '';
            $data['Check_Out_Time'] = $data['Check_Out_Time'] ?? '';
        }

        return $data;
    }

    private function findExistingStudentAttendance(array $data): ?array
    {
        $studentId = trim((string) ($data['Student_ID'] ?? ''));
        $date = trim((string) ($data['Attendance_Date'] ?? ''));
        $classId = trim((string) ($data['Class_ID'] ?? ''));
        $scheduleId = trim((string) ($data['Schedule_ID'] ?? ''));

        if ($studentId === '' || $date === '') {
            return null;
        }

        return $this->getAll()->first(function ($attendance) use ($studentId, $date, $classId, $scheduleId) {
            if (($attendance['Student_ID'] ?? '') !== $studentId
                || ($attendance['Attendance_Date'] ?? '') !== $date
                || strtoupper(trim($attendance['Is_Active'] ?? 'TRUE')) === 'FALSE') {
                return false;
            }

            $existingClass = trim((string) ($attendance['Class_ID'] ?? ''));
            $existingSchedule = trim((string) ($attendance['Schedule_ID'] ?? ''));

            return ($classId !== '' && $existingClass === $classId)
                || ($scheduleId !== '' && $existingSchedule === $scheduleId)
                || ($classId === '' && $scheduleId === '');
        });
    }

    private function isActiveStudentClassAttendance(array $attendance, string $classId, string $date): bool
    {
        if (trim((string) ($attendance['Student_ID'] ?? '')) === ''
            || trim((string) ($attendance['Attendance_Date'] ?? '')) !== $date
            || strtoupper(trim((string) ($attendance['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
            return false;
        }

        $storedClassId = trim((string) ($attendance['Class_ID'] ?? ''));
        $legacyScope = trim((string) ($attendance['Schedule_ID'] ?? ''));

        return $storedClassId === $classId || ($storedClassId === '' && $legacyScope === $classId);
    }
}
