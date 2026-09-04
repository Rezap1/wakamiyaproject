<?php

namespace App\Support\Reporting;

use Illuminate\Support\Collection;

class HumanReadableResolver
{
    public static function indexBy($rows, string $key): Collection
    {
        return collect($rows)
            ->filter(fn ($row) => trim((string) (self::arrayRow($row)[$key] ?? '')) !== '')
            ->keyBy($key);
    }

    public static function value($value, string $fallback = '-'): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    public static function studentName(?string $id, $studentsById): string
    {
        $row = self::row($studentsById, $id);

        return self::rowValue($row, ['Full_Name', 'Student_Name', 'Name', 'Username'], 'Data siswa tidak ditemukan');
    }

    public static function studentNumber(?string $id, $studentsById): string
    {
        $row = self::row($studentsById, $id);

        return self::rowValue($row, ['Student_Number', 'NIS', 'NISN'], '-');
    }

    public static function className(?string $id, $classesById): string
    {
        $row = self::row($classesById, $id);

        return self::rowValue($row, ['Class_Name', 'Class_Code', 'Name'], 'Kelas tidak ditemukan');
    }

    public static function subjectName(?string $id, $subjectsById): string
    {
        $row = self::row($subjectsById, $id);

        return self::rowValue($row, ['Subject_Name', 'Subject_Code', 'Name', 'Title'], 'Mata pelajaran tidak ditemukan');
    }

    public static function teacherName(?string $id, $teachersById): string
    {
        $row = self::row($teachersById, $id);

        return self::rowValue($row, ['Full_Name', 'Teacher_Name', 'Name', 'Username'], 'Pengajar tidak ditemukan');
    }

    public static function employeeName(?string $id, $employeesById): string
    {
        $row = self::row($employeesById, $id);

        return self::rowValue($row, ['Full_Name', 'Employee_Name', 'Name', 'Username'], 'Data pegawai tidak ditemukan');
    }

    public static function userName(?string $id, $usersById): string
    {
        $row = self::row($usersById, $id);

        return self::rowValue($row, ['Full_Name', 'Name', 'Username', 'Email'], 'Pengguna tidak ditemukan');
    }

    public static function companyName(?string $id, $companiesById): string
    {
        $row = self::row($companiesById, $id);

        return self::rowValue($row, ['Company_Name', 'Name'], 'Data perusahaan tidak ditemukan');
    }

    public static function accountName(?string $id, $accountsById): string
    {
        $row = self::row($accountsById, $id);
        $name = self::rowValue($row, ['Account_Name', 'Name'], '');
        $code = self::rowValue($row, ['Account_Code', 'Code'], '');

        if ($name !== '' && $code !== '') {
            return $code . ' - ' . $name;
        }

        return $name !== '' ? $name : ($code !== '' ? $code : 'Akun tidak ditemukan');
    }

    public static function scheduleLabel(?string $id, $schedulesById, $classesById, $subjectsById, $teachersById = null): string
    {
        $schedule = self::row($schedulesById, $id);
        if (!$schedule) {
            return 'Jadwal tidak ditemukan';
        }

        $parts = array_filter([
            self::subjectName($schedule['Subject_ID'] ?? '', $subjectsById),
            self::className($schedule['Class_ID'] ?? '', $classesById),
            self::scheduleTime($schedule),
        ], fn ($part) => $part !== '' && $part !== '-');

        if ($teachersById !== null) {
            $teacherName = self::teacherName($schedule['Teacher_ID'] ?? '', $teachersById);
            if ($teacherName !== 'Pengajar tidak ditemukan') {
                $parts[] = $teacherName;
            }
        }

        return implode(' | ', $parts) ?: 'Jadwal tidak ditemukan';
    }

    public static function assessmentTitle(?string $id, $assessmentsById): string
    {
        $row = self::row($assessmentsById, $id);

        return self::rowValue($row, ['Title', 'Assessment_Name', 'Name'], 'Penilaian tidak ditemukan');
    }

    public static function assignmentTitle(?string $id, $assignmentsById): string
    {
        $row = self::row($assignmentsById, $id);

        return self::rowValue($row, ['Title', 'Assignment_Name', 'Name'], 'Tugas tidak ditemukan');
    }

    public static function studentOrCompanyPayer(array $row, $studentsById, $companiesById): string
    {
        $invoiceType = strtoupper(trim((string) ($row['Invoice_Type'] ?? 'STUDENT')));
        if ($invoiceType === 'COMPANY') {
            $companyName = trim((string) ($row['Company_Name'] ?? ''));
            if ($companyName !== '') {
                return $companyName;
            }

            return self::companyName($row['Company_ID'] ?? '', $companiesById);
        }

        $studentName = trim((string) ($row['Student_Name'] ?? ''));
        if ($studentName !== '') {
            return $studentName;
        }

        return self::studentName($row['Student_ID'] ?? '', $studentsById);
    }

    private static function scheduleTime(array $schedule): string
    {
        $day = trim((string) ($schedule['Day_Of_Week'] ?? $schedule['Day'] ?? ''));
        $start = trim((string) ($schedule['Start_Time'] ?? ''));
        $end = trim((string) ($schedule['End_Time'] ?? ''));
        $time = trim($start . ($start !== '' || $end !== '' ? ' - ' : '') . $end);

        return trim($day . ($day !== '' && $time !== '' ? ', ' : '') . $time);
    }

    private static function row($rowsById, ?string $id): ?array
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }

        $rows = $rowsById instanceof Collection ? $rowsById : collect($rowsById);
        $row = $rows->get($id);

        return $row ? self::arrayRow($row) : null;
    }

    private static function arrayRow($row): array
    {
        if (is_array($row)) {
            return $row;
        }

        return is_object($row) ? (array) $row : [];
    }

    private static function rowValue(?array $row, array $keys, string $fallback): string
    {
        if (!$row) {
            return $fallback;
        }

        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}
