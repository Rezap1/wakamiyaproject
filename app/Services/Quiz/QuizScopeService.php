<?php

namespace App\Services\Quiz;

use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;

class QuizScopeService
{
    public function __construct(
        private TeacherRepositoryInterface $teachers,
        private StudentRepositoryInterface $students,
        private ScheduleRepositoryInterface $schedules,
        private ClassRepositoryInterface $classes,
    ) {}

    public function teacherForUser(object $user): array
    {
        $userId = trim((string) ($user->User_ID ?? ''));
        $teacher = collect($this->teachers->fetchAll())->first(
            fn ($row) => trim((string) ($row['User_ID'] ?? '')) === $userId && $this->active((array) $row)
        );
        abort_unless($teacher && trim((string) ($teacher['Teacher_ID'] ?? '')) !== '', 403, 'Profil pengajar aktif tidak ditemukan.');

        return (array) $teacher;
    }

    public function studentForUser(object $user): array
    {
        $userId = trim((string) ($user->User_ID ?? ''));
        $student = collect($this->students->fetchAll())->first(
            fn ($row) => trim((string) ($row['User_ID'] ?? '')) === $userId && $this->active((array) $row)
        );
        abort_unless($student && trim((string) ($student['Student_ID'] ?? '')) !== '' && trim((string) ($student['Class_ID'] ?? '')) !== '', 403, 'Profil siswa aktif atau kelas siswa tidak ditemukan.');

        return (array) $student;
    }

    public function classesForTeacher(string $teacherId)
    {
        $classIds = collect($this->schedules->fetchAll())
            ->filter(fn ($row) => trim((string) ($row['Teacher_ID'] ?? '')) === trim($teacherId) && $this->active((array) $row))
            ->pluck('Class_ID')->map(fn ($id) => trim((string) $id))->filter()->unique()->values();

        return collect($this->classes->fetchAll())
            ->filter(fn ($row) => $classIds->contains(trim((string) ($row['Class_ID'] ?? ''))) && $this->active((array) $row))
            ->sortBy(fn ($row) => mb_strtolower(trim((string) ($row['Class_Name'] ?? $row['Class_ID'] ?? ''))))
            ->values();
    }

    public function studentsForClass(string $classId)
    {
        return collect($this->students->fetchAll())
            ->filter(fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($classId))
            ->filter(fn ($row) => SheetValue::isOperationalStudent((array) $row))
            ->sortBy(fn ($row) => mb_strtolower(trim((string) ($row['Full_Name'] ?? $row['Student_Name'] ?? $row['Student_ID'] ?? ''))))
            ->values();
    }

    public function assertTeacherClass(string $teacherId, string $classId): void
    {
        abort_unless($this->classesForTeacher($teacherId)->contains(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($classId)
        ), 403, 'Kelas berada di luar jadwal mengajar Anda.');
    }

    private function active(array $row): bool
    {
        foreach (['Is_Active', 'Status'] as $field) {
            if (in_array(strtoupper(trim((string) ($row[$field] ?? ''))), ['FALSE', 'INACTIVE', 'NONACTIVE', 'NON_ACTIVE', 'ARCHIVED', 'DROPPED', 'CANCELLED'], true)) {
                return false;
            }
        }

        return true;
    }
}
