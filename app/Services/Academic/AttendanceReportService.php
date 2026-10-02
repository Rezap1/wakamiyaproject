<?php

namespace App\Services\Academic;

use App\Helpers\AttendanceStatusHelper;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Support\Academic\TeacherScopeResolver;
use App\Support\Presentation\IndonesianPresentation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only reporting projection over the canonical MASTER_ATTENDANCE data.
 *
 * This service deliberately does not infer ABSENT from a missing row. It only
 * counts statuses already persisted by the existing attendance engine.
 */
class AttendanceReportService
{
    private const TIMEZONE = 'Asia/Jakarta';

    private const STATUS_LABELS = [
        'PRESENT' => 'Hadir',
        'LATE' => 'Terlambat',
        'PERMITTED' => 'Izin',
        'SICK' => 'Sakit',
        'ABSENT' => 'Alpa',
    ];

    private ?Collection $classSnapshot = null;

    private ?Collection $studentSnapshot = null;

    private ?Collection $enrollmentSnapshot = null;

    private ?Collection $scheduleSnapshot = null;

    private ?Collection $attendanceSnapshot = null;

    public function __construct(
        private AttendanceRepositoryInterface $attendanceRepository,
        private ClassRepositoryInterface $classRepository,
        private StudentRepositoryInterface $studentRepository,
        private ClassEnrollmentRepositoryInterface $classEnrollmentRepository,
        private ScheduleRepositoryInterface $scheduleRepository,
        private TeacherScopeResolver $teacherScopeResolver,
        private AttendanceLegacyClassifier $legacyClassifier
    ) {}

    public function availableClasses($user, string $roleName): Collection
    {
        $classes = $this->activeClasses();
        if ($this->isAdministrator($roleName)) {
            return $classes;
        }

        if (strtoupper(trim($roleName)) !== 'TEACHER') {
            abort(403, 'Anda tidak memiliki hak akses ke Laporan Absensi.');
        }

        $scope = $this->teacherScopeResolver->resolveForUser($user);
        $allowed = $scope['class_ids'] ?? [];

        return $classes
            ->filter(fn ($class) => in_array(trim((string) ($class['Class_ID'] ?? '')), $allowed, true))
            ->values();
    }

    public function availableStudentsByClass($user, string $roleName, iterable $classes): array
    {
        $scope = null;
        if (! $this->isAdministrator($roleName)) {
            if (strtoupper(trim($roleName)) !== 'TEACHER') {
                abort(403, 'Anda tidak memiliki hak akses ke Laporan Absensi.');
            }

            $scope = $this->teacherScopeResolver->resolveForUser($user);
        }

        $students = $scope === null ? $this->studentRows() : collect($scope['students'] ?? []);
        $enrollments = $scope === null ? $this->enrollmentRows() : collect();

        return collect($classes)->mapWithKeys(function ($class) use ($students, $enrollments, $scope) {
            $classId = trim((string) ($class['Class_ID'] ?? ''));
            $options = $this->classRoster($classId, $students, $enrollments, $scope)
                ->map(function ($student) {
                    $studentId = trim((string) ($student['Student_ID'] ?? ''));
                    $number = $this->studentNumber((array) $student);
                    $name = $this->studentName((array) $student);

                    return [
                        'id' => $studentId,
                        'name' => $name,
                        'number' => $number,
                        'label' => $name.($number !== '-' ? ' — '.$number : ''),
                    ];
                })->values()->all();

            return [$classId => $options];
        })->all();
    }

    public function build($user, string $roleName, array $filters): array
    {
        $classId = trim((string) ($filters['class_id'] ?? ''));
        $allClasses = $this->activeClasses();
        $class = $allClasses->first(fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === $classId);
        if (! $class) {
            abort(404, 'Kelas tidak ditemukan atau tidak aktif.');
        }

        $teacherName = null;
        $scope = null;
        if (! $this->isAdministrator($roleName)) {
            if (strtoupper(trim($roleName)) !== 'TEACHER') {
                abort(403, 'Anda tidak memiliki hak akses ke Laporan Absensi.');
            }

            $scope = $this->teacherScopeResolver->resolveForUser($user);
            if (! $this->teacherScopeResolver->classAllowed($scope, $classId)) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
            $teacherName = trim((string) ($user->Full_Name ?? $user->Name ?? '')) ?: null;
        }

        $period = $this->resolvePeriod($filters);
        $students = $scope === null ? $this->studentRows() : collect($scope['students'] ?? []);
        $enrollments = $scope === null ? $this->enrollmentRows() : collect();
        $roster = $this->classRoster($classId, $students, $enrollments, $scope);
        $studentId = trim((string) ($filters['student_id'] ?? ''));
        $selectedStudent = $studentId === ''
            ? null
            : $roster->first(fn ($student) => trim((string) ($student['Student_ID'] ?? '')) === $studentId);
        if ($studentId !== '' && ! $selectedStudent) {
            abort(403, 'Siswa berada di luar kelas yang dipilih.');
        }
        $reportRoster = $selectedStudent ? collect([$selectedStudent]) : $roster;
        $rosterById = $reportRoster->keyBy(fn ($student) => trim((string) ($student['Student_ID'] ?? '')));
        $schedules = $this->scheduleRows();

        $rows = $this->attendanceRows()
            ->map(function ($attendance) use ($allClasses, $schedules) {
                $attendance = is_array($attendance) ? $attendance : (array) $attendance;
                $classified = $this->legacyClassifier->classify($attendance, $allClasses, $schedules);

                return [$attendance, $classified];
            })
            ->filter(function ($pair) use ($classId, $period, $rosterById) {
                [$attendance, $classified] = $pair;
                $studentId = trim((string) ($attendance['Student_ID'] ?? ''));
                $isActive = strtoupper(trim((string) ($attendance['Is_Active'] ?? 'TRUE'))) !== 'FALSE';
                $date = $this->attendanceDate($attendance['Attendance_Date'] ?? null);

                return $isActive
                    && $studentId !== ''
                    && $rosterById->has($studentId)
                    && ! in_array($classified['classification'] ?? '', ['EMPLOYEE', 'UNKNOWN', 'AMBIGUOUS'], true)
                    && trim((string) ($classified['class_id'] ?? '')) === $classId
                    && $date !== null
                    && $date >= $period['start']
                    && $date <= $period['end'];
            })
            ->map(function ($pair) use ($rosterById) {
                [$attendance] = $pair;
                $studentId = trim((string) ($attendance['Student_ID'] ?? ''));
                $student = (array) $rosterById->get($studentId, []);
                $status = AttendanceStatusHelper::normalize($attendance['Status'] ?? '');

                return [
                    'attendance_id' => trim((string) ($attendance['Attendance_ID'] ?? '')),
                    'student_id' => $studentId,
                    'student_number' => trim((string) ($student['Student_Number'] ?? $student['NIS'] ?? '')) ?: '-',
                    'student_name' => trim((string) ($student['Full_Name'] ?? $student['Name'] ?? '')) ?: $studentId,
                    'date' => $this->attendanceDate($attendance['Attendance_Date'] ?? null)->format('Y-m-d'),
                    'check_in' => $this->timeValue($attendance['Check_In_Time'] ?? $attendance['Time_In'] ?? null),
                    'check_out' => $this->timeValue($attendance['Check_Out_Time'] ?? $attendance['Time_Out'] ?? null),
                    'status_key' => $status,
                    'status' => AttendanceStatusHelper::label($status),
                    'notes' => trim((string) ($attendance['Notes'] ?? $attendance['Description'] ?? '')) ?: '-',
                ];
            })
            ->sortBy(fn ($row) => implode('|', [$row['date'], $row['student_name'], $row['check_in'], $row['attendance_id']]))
            ->values();

        $summary = collect(self::STATUS_LABELS)->mapWithKeys(
            fn ($label, $status) => [$status => [
                'label' => $label,
                'count' => $rows->where('status_key', $status)->count(),
            ]]
        )->all();

        $studentRecap = $period['type'] === 'harian'
            ? collect()
            : $reportRoster->map(function ($student) use ($rows) {
                $studentId = trim((string) ($student['Student_ID'] ?? ''));
                $studentRows = $rows->where('student_id', $studentId);
                $counts = [];
                foreach (self::STATUS_LABELS as $status => $label) {
                    $counts[$status] = $studentRows->where('status_key', $status)->count();
                }

                return [
                    'student_id' => $studentId,
                    'student_number' => trim((string) ($student['Student_Number'] ?? $student['NIS'] ?? '')) ?: '-',
                    'student_name' => trim((string) ($student['Full_Name'] ?? $student['Name'] ?? '')) ?: $studentId,
                    'counts' => $counts,
                    'total' => array_sum($counts),
                ];
            })->sortBy('student_name')->values();

        return [
            'class' => [
                'id' => $classId,
                'name' => $this->className((array) $class),
            ],
            'report_type' => $period['type'],
            'report_type_label' => $period['type_label'],
            'title' => $selectedStudent ? 'REKAP ABSENSI SISWA' : $period['title'],
            'period' => $period,
            'student_count' => $reportRoster->count(),
            'student' => $selectedStudent ? [
                'name' => $this->studentName((array) $selectedStudent),
                'number' => $this->studentNumber((array) $selectedStudent),
            ] : null,
            'is_individual' => $selectedStudent !== null,
            'teacher_name' => $teacherName,
            'summary' => $summary,
            'total_records' => $rows->count(),
            'student_recap' => $studentRecap,
            'rows' => $rows,
            'status_labels' => self::STATUS_LABELS,
            'printed_at' => CarbonImmutable::now(self::TIMEZONE),
            'absence_note' => 'Alpa hanya dihitung dari status ABSENT/Alpa yang tersimpan. Siswa tanpa baris absensi tidak otomatis dianggap Alpa.',
        ];
    }

    public function resolvePeriod(array $filters): array
    {
        $type = trim((string) ($filters['report_type'] ?? ''));

        if ($type === 'harian') {
            $start = $this->strictDate($filters['daily_date'] ?? null);
            $end = $start;
            $label = IndonesianPresentation::date($start, 'd F Y');
            $typeLabel = 'Harian';
            $title = 'LAPORAN ABSENSI HARIAN';
        } elseif ($type === 'mingguan') {
            $anchor = $this->strictDate($filters['weekly_anchor'] ?? null);
            $start = $anchor->startOfWeek(CarbonImmutable::MONDAY);
            $end = $anchor->endOfWeek(CarbonImmutable::SUNDAY);
            $label = $this->rangeLabel($start, $end);
            $typeLabel = 'Mingguan';
            $title = 'REKAP ABSENSI MINGGUAN';
        } elseif ($type === 'bulanan') {
            $month = (int) ($filters['month'] ?? 0);
            $year = (int) ($filters['year'] ?? 0);
            $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE)->startOfMonth();
            $end = $start->endOfMonth();
            $label = IndonesianPresentation::date($start, 'F Y');
            $typeLabel = 'Bulanan';
            $title = 'REKAP ABSENSI BULANAN';
        } elseif ($type === 'rentang') {
            $start = $this->strictDate($filters['date_start'] ?? null);
            $end = $this->strictDate($filters['date_end'] ?? null);
            $label = IndonesianPresentation::date($start, 'd F Y').' s.d. '.IndonesianPresentation::date($end, 'd F Y');
            $typeLabel = 'Rentang Tanggal';
            $title = 'LAPORAN ABSENSI';
        } else {
            abort(422, 'Jenis rekap tidak valid.');
        }

        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'title' => $title,
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'label' => $label,
        ];
    }

    private function activeClasses(): Collection
    {
        return $this->classRows()
            ->filter(fn ($row) => trim((string) ($row['Class_ID'] ?? '')) !== '' && $this->isActive($row))
            ->sortBy(fn ($row) => $this->className((array) $row))
            ->values();
    }

    private function classRoster(string $classId, Collection $students, Collection $enrollments, ?array $teacherScope): Collection
    {
        if ($teacherScope !== null) {
            $allowedIds = $teacherScope['students_by_class'][$classId] ?? [];

            return $students
                ->filter(fn ($student) => $this->isActive($student)
                    && in_array(trim((string) ($student['Student_ID'] ?? '')), $allowedIds, true))
                ->unique(fn ($student) => trim((string) ($student['Student_ID'] ?? '')))
                ->sortBy(fn ($student) => $this->studentName((array) $student))
                ->values();
        }

        $enrolledIds = $enrollments
            ->filter(fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === $classId && $this->isActive($row))
            ->pluck('Student_ID')
            ->map(fn ($id) => trim((string) $id))
            ->filter();
        $directIds = $students
            ->filter(fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === $classId && $this->isActive($row))
            ->pluck('Student_ID')
            ->map(fn ($id) => trim((string) $id))
            ->filter();
        $studentIds = $enrolledIds->merge($directIds)->unique()->values();

        return $students
            ->filter(fn ($student) => $this->isActive($student) && $studentIds->contains(trim((string) ($student['Student_ID'] ?? ''))))
            ->unique(fn ($student) => trim((string) ($student['Student_ID'] ?? '')))
            ->sortBy(fn ($student) => trim((string) ($student['Full_Name'] ?? $student['Name'] ?? $student['Student_ID'] ?? '')))
            ->values();
    }

    private function strictDate($value): CarbonImmutable
    {
        $value = trim((string) $value);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, self::TIMEZONE);
        if (! $date || $date->format('Y-m-d') !== $value) {
            abort(422, 'Tanggal laporan tidak valid.');
        }

        return $date;
    }

    private function attendanceDate($value): ?CarbonImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(str_replace('/', '-', trim((string) $value)), self::TIMEZONE)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function timeValue($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        if (preg_match('/^(\d{1,2}:\d{2})(?::\d{2})?/', $value, $match)) {
            return $match[1];
        }

        return $value;
    }

    private function rangeLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        if ($start->month === $end->month && $start->year === $end->year) {
            return $start->day.'-'.IndonesianPresentation::date($end, 'd F Y');
        }

        return IndonesianPresentation::date($start, 'd F Y').' - '.IndonesianPresentation::date($end, 'd F Y');
    }

    private function className(array $class): string
    {
        $name = trim((string) ($class['Class_Name'] ?? $class['Class_ID'] ?? 'Kelas'));
        $code = trim((string) ($class['Class_Code'] ?? ''));

        return $name.($code !== '' ? ' ('.$code.')' : '');
    }

    private function isActive($row): bool
    {
        $row = is_array($row) ? $row : (array) $row;
        foreach (['Is_Active', 'Status', 'Enrollment_Status'] as $field) {
            $value = strtoupper(trim((string) ($row[$field] ?? '')));
            if (in_array($value, ['FALSE', 'INACTIVE', 'NONACTIVE', 'NON_ACTIVE', 'CANCELLED', 'DROPPED', 'ARCHIVED', 'ALUMNI'], true)) {
                return false;
            }
        }

        return true;
    }

    private function isAdministrator(string $roleName): bool
    {
        return in_array(strtoupper(trim($roleName)), ['MASTER', 'ADMINISTRATOR'], true);
    }

    private function studentName(array $student): string
    {
        return trim((string) ($student['Full_Name'] ?? $student['Name'] ?? '')) ?: 'Siswa';
    }

    private function studentNumber(array $student): string
    {
        return trim((string) ($student['Student_Number'] ?? $student['NIS'] ?? $student['Registration_Number'] ?? '')) ?: '-';
    }

    private function classRows(): Collection
    {
        return $this->classSnapshot ??= collect($this->classRepository->fetchAll());
    }

    private function studentRows(): Collection
    {
        return $this->studentSnapshot ??= collect($this->studentRepository->fetchAll());
    }

    private function enrollmentRows(): Collection
    {
        return $this->enrollmentSnapshot ??= collect($this->classEnrollmentRepository->fetchAll());
    }

    private function scheduleRows(): Collection
    {
        return $this->scheduleSnapshot ??= collect($this->scheduleRepository->fetchAll());
    }

    private function attendanceRows(): Collection
    {
        return $this->attendanceSnapshot ??= collect($this->attendanceRepository->fetchAll());
    }
}
