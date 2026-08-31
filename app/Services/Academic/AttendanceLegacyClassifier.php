<?php

namespace App\Services\Academic;

/**
 * Read-only classification for historical attendance rows.
 *
 * This class never mutates attendance data and never invents a schedule for a
 * value that is only known to be a class identifier.
 */
class AttendanceLegacyClassifier
{
    public function classify(array $attendance, $classes, $schedules): array
    {
        $classesById = collect($classes)->keyBy(fn ($row) => trim((string) ($row['Class_ID'] ?? '')));
        $schedulesById = collect($schedules)->keyBy(fn ($row) => trim((string) ($row['Schedule_ID'] ?? '')));
        $finish = function (string $classification, ?string $classId, ?string $scheduleId, string $originalScheduleId, string $resolution) use ($attendance, $classesById): array {
            $result = $this->result($attendance, $classification, $classId, $scheduleId, $originalScheduleId, $resolution);
            if ($classId !== null && $classesById->has($classId)) {
                $result['class_name'] = $classesById->get($classId)['Class_Name'] ?? $classId;
            }
            return $result;
        };

        $type = strtoupper(trim((string) ($attendance['Attendance_Type'] ?? '')));
        $studentId = trim((string) ($attendance['Student_ID'] ?? ''));
        $employeeId = trim((string) ($attendance['Employee_ID'] ?? ''));
        $classId = trim((string) ($attendance['Class_ID'] ?? ''));
        $rawScheduleId = trim((string) ($attendance['Schedule_ID'] ?? ''));

        if ($type === 'CLASS_QR' || $type === 'CLASS_MANUAL') {
            return $finish($type, $classId, null, $rawScheduleId, $classId !== '' ? 'CLASS_ONLY' : 'UNRESOLVED');
        }
        if ($type === 'SCHEDULE') {
            $schedule = $schedulesById->get($rawScheduleId);
            if (!$schedule) {
                return $finish('UNKNOWN', null, null, $rawScheduleId, 'SCHEDULE_UNRESOLVED');
            }
            $scheduleClassId = trim((string) ($schedule['Class_ID'] ?? ''));
            if ($classId !== '' && $scheduleClassId !== '' && $classId !== $scheduleClassId) {
                return $finish('AMBIGUOUS', null, null, $rawScheduleId, 'CONFLICTING_CLASS');
            }
            return $finish('SCHEDULE', $scheduleClassId !== '' ? $scheduleClassId : $classId, $rawScheduleId, $rawScheduleId, 'SCHEDULE_RESOLVED');
        }
        if ($type === 'EMPLOYEE' || $employeeId !== '') {
            return $finish('EMPLOYEE', null, null, $rawScheduleId, 'EMPLOYEE_EVIDENCE');
        }

        if ($studentId === '') {
            return $finish('UNKNOWN', null, null, $rawScheduleId, 'NO_STUDENT_ID');
        }

        $classExists = $classId !== '' && $classesById->has($classId);
        $scheduleExists = $rawScheduleId !== '' && $schedulesById->has($rawScheduleId);
        $rawValueIsClass = $rawScheduleId !== '' && $classesById->has($rawScheduleId);
        $scheduleClassId = $scheduleExists ? trim((string) ($schedulesById->get($rawScheduleId)['Class_ID'] ?? '')) : '';

        if ($classId !== '' && $rawScheduleId !== '' && $scheduleExists && $scheduleClassId !== '' && $classId !== $scheduleClassId) {
            return $finish('AMBIGUOUS', null, null, $rawScheduleId, 'CONFLICTING_CLASS');
        }
        if ($classId !== '' && $classExists && $rawScheduleId === '') {
            return $finish('LEGACY_CLASS_CANDIDATE', $classId, null, '', 'CLASS_ONLY');
        }
        if ($rawScheduleId !== '' && $rawValueIsClass && $scheduleExists) {
            return $finish('AMBIGUOUS', null, null, $rawScheduleId, 'CLASS_AND_SCHEDULE_MATCH');
        }
        if ($rawScheduleId !== '' && $rawValueIsClass && !$scheduleExists) {
            return $finish('LEGACY_CLASS_CANDIDATE', $rawScheduleId, null, $rawScheduleId, 'CLASS_ONLY');
        }
        if ($rawScheduleId !== '' && $scheduleExists) {
            return $finish('SCHEDULE', $scheduleClassId, $rawScheduleId, $rawScheduleId, 'SCHEDULE_RESOLVED');
        }

        return $finish('UNKNOWN', null, null, $rawScheduleId, 'UNRESOLVED');
    }

    private function result(array $attendance, string $classification, ?string $classId, ?string $scheduleId, string $originalScheduleId, string $resolution): array
    {
        return [
            'attendance' => $attendance,
            'classification' => $classification,
            'is_legacy' => in_array($classification, ['LEGACY_CLASS_CANDIDATE', 'AMBIGUOUS', 'UNKNOWN'], true),
            'is_class_based' => in_array($classification, ['CLASS_QR', 'CLASS_MANUAL', 'LEGACY_CLASS_CANDIDATE'], true),
            'is_schedule_based' => $classification === 'SCHEDULE',
            'class_id' => $classId,
            'schedule_id' => $scheduleId,
            'original_schedule_id' => $originalScheduleId,
            'resolution' => $resolution,
        ];
    }
}
