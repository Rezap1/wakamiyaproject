<?php

namespace App\Services\Academic;

use App\Helpers\AttendanceStatusHelper;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Exception;

class AttendanceService
{
    protected $repository;
    protected $enterpriseEvent;

    public function __construct(AttendanceRepositoryInterface $repository, EnterpriseEventService $enterpriseEvent)
    {
        $this->repository = $repository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAll()
    {
        return $this->repository->fetchAll();
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
            throw new Exception("Cannot open attendance for future dates.");
        }
        
        // Cannot open twice for the same schedule and date
        $existing = $this->getAll()->first(function($item) use ($data) {
            return $item['Schedule_ID'] === ($data['Schedule_ID'] ?? '') 
                && $item['Attendance_Date'] === ($data['Attendance_Date'] ?? '')
                && ($item['Session_Status'] ?? '') === 'OPEN';
        });

        if ($existing) {
            throw new Exception("Attendance session is already open for this schedule and date.");
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
        $lockKey = 'student_attendance_' . sha1("{$studentId}|{$attendanceDate}|{$scope}");

        return Cache::lock($lockKey, 30)->block(5, function () use ($data) {
            $existing = $this->findExistingStudentAttendance($data);

            if ($existing && !empty($existing['Attendance_ID'])) {
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

        if (!AttendanceStatusHelper::isPresentLike($status)) {
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
}
