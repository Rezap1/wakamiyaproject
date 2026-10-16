<?php

namespace App\Interfaces\Attendance;

interface StudentAttendanceLookupInterface
{
    public function findActiveStudentClassAttendance(
        string $studentId,
        string $classId,
        string $date,
        string $attendanceType = 'CLASS_QR'
    ): ?array;
}
