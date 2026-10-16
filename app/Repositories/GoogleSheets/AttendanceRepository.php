<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\Attendance\StudentAttendanceLookupInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;

class AttendanceRepository extends BaseSheetRepository implements AttendanceRepositoryInterface, StudentAttendanceLookupInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ATTENDANCE';
        $this->cacheKey = 'attendances_sheet';
        $this->primaryKey = 'Attendance_ID';
    }

    public function findById(string $id)
    {
        return $this->findByIdFresh($id);
    }

    public function findByEmployeeAndSession(string $employeeId, string $sessionId)
    {
        return $this->rowsWhereColumn('Employee_ID', $employeeId)->first(function ($att) use ($sessionId) {
            return ($att['Employee_ID'] ?? '') === $employeeId &&
                   ($att['Session_ID'] ?? '') === $sessionId &&
                   strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
        });
    }

    public function findEmployeeAttendances(string $employeeId)
    {
        return $this->rowsWhereColumn('Employee_ID', $employeeId)->filter(function ($att) {
            return strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
        })->values();
    }

    public function findActiveStudentClassAttendance(
        string $studentId,
        string $classId,
        string $date,
        string $attendanceType = 'CLASS_QR'
    ): ?array {
        return $this->rowsWhereColumn('Student_ID', $studentId)->first(function (array $attendance) use ($studentId, $classId, $date, $attendanceType): bool {
            return strcasecmp(trim((string) ($attendance['Student_ID'] ?? '')), $studentId) === 0
                && trim((string) ($attendance['Class_ID'] ?? '')) === $classId
                && trim((string) ($attendance['Attendance_Date'] ?? '')) === $date
                && strtoupper(trim((string) ($attendance['Attendance_Type'] ?? ''))) === strtoupper($attendanceType)
                && strtoupper(trim((string) ($attendance['Is_Active'] ?? 'TRUE'))) !== 'FALSE';
        });
    }

    public function create(array $data)
    {
        return $this->append($data);
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    public function softDelete($id)
    {
        return $this->updateRow($id, ['Is_Active' => 'FALSE']);
    }
}
