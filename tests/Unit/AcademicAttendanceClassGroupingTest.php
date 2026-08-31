<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Services\Academic\AttendanceService;
use App\Services\Core\EnterpriseEventService;
use Mockery;
use Tests\TestCase;

class AcademicAttendanceClassGroupingTest extends TestCase
{
    public function test_academic_attendance_groups_students_by_class(): void
    {
        $groups = $this->buildGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
            $this->attendance('STU-D', 'CLASS-B', 'CLASS_QR', 'LATE'),
        ]);

        $this->assertSame(['CLASS-A', 'CLASS-B'], $groups->pluck('Class_ID')->all());
        $this->assertSame(['STU-A', 'STU-B', 'STU-C'], $groups[0]['Students']->pluck('Student_ID')->all());
        $this->assertSame(['STU-D', 'STU-E'], $groups[1]['Students']->pluck('Student_ID')->all());
    }

    public function test_students_from_different_classes_are_not_mixed(): void
    {
        $groups = $this->buildGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
            $this->attendance('STU-D', 'CLASS-B', 'CLASS_QR', 'PRESENT'),
        ]);

        $this->assertNotContains('STU-D', $groups[0]['Students']->pluck('Student_ID')->all());
        $this->assertNotContains('STU-A', $groups[1]['Students']->pluck('Student_ID')->all());
    }

    public function test_student_without_attendance_is_displayed_as_belum_absen(): void
    {
        $groups = $this->buildGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
        ]);

        $student = $groups[0]['Students']->firstWhere('Student_ID', 'STU-B');

        $this->assertSame('NOT_ATTENDED', $student['Status_Key']);
        $this->assertSame('Belum Absen', $student['Display_Status']);
        $this->assertNull($student['Attendance']);
    }

    public function test_class_qr_attendance_matches_by_student_class_date_and_type(): void
    {
        $groups = $this->buildGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT', 'SCHEDULE-WRONG'),
        ]);

        $student = $groups[0]['Students']->firstWhere('Student_ID', 'STU-A');

        $this->assertSame('PRESENT', $student['Status_Key']);
        $this->assertSame('ATT-STU-A', $student['Attendance_ID']);
    }

    public function test_class_qr_does_not_depend_on_schedule_id(): void
    {
        $groups = $this->buildGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT', 'SCHEDULE-NOT-FOUND'),
        ]);

        $this->assertSame('Hadir', $groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Display_Status']);
    }

    public function test_schedule_based_attendance_still_resolves_class(): void
    {
        $groups = $this->buildGroups([
            [
                'Attendance_ID' => 'ATT-SCHEDULE',
                'Student_ID' => 'STU-D',
                'Schedule_ID' => 'SCHEDULE-B',
                'Attendance_Date' => '2026-08-30',
                'Attendance_Type' => 'SCHEDULE',
                'Status' => 'PRESENT',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $student = $groups[1]['Students']->firstWhere('Student_ID', 'STU-D');

        $this->assertSame('CLASS-B', $student['Class_ID']);
        $this->assertSame('ATT-SCHEDULE', $student['Attendance_ID']);
    }

    public function test_class_summary_uses_roster_count_not_attendance_row_count(): void
    {
        $groups = $this->buildGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
        ]);

        $this->assertSame(3, $groups[0]['Total']);
        $this->assertSame(1, $groups[0]['Hadir']);
        $this->assertSame(2, $groups[0]['Belum_Absen']);
    }

    public function test_employee_attendance_is_excluded_from_academic_student_attendance(): void
    {
        $groups = $this->buildGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
            [
                'Attendance_ID' => 'ATT-EMPLOYEE',
                'Employee_ID' => 'EMP-1',
                'Attendance_Type' => 'EMPLOYEE',
                'Attendance_Date' => '2026-08-30',
                'Status' => 'PRESENT',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $this->assertSame(1, $groups->sum('Hadir'));
        $this->assertNotContains('ATT-EMPLOYEE', $groups[0]['Students']->pluck('Attendance_ID')->all());
    }

    public function test_invalid_class_filter_cannot_escape_authorized_class_scope(): void
    {
        $groups = $this->buildGroups([], 'CLASS-NOT-AUTHORIZED');

        $this->assertCount(0, $groups);
    }

    private function buildGroups(array $attendances, ?string $classFilter = null)
    {
        $service = new AttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        return $service->buildClassAttendanceGroups(
            $this->classes(),
            $this->students(),
            $attendances,
            [
                ['Schedule_ID' => 'SCHEDULE-B', 'Class_ID' => 'CLASS-B'],
            ],
            '2026-08-30',
            null,
            $classFilter
        );
    }

    private function attendance(string $studentId, string $classId, string $type, string $status, string $scheduleId = ''): array
    {
        return [
            'Attendance_ID' => 'ATT-' . $studentId,
            'Student_ID' => $studentId,
            'Class_ID' => $classId,
            'Schedule_ID' => $scheduleId,
            'Attendance_Date' => '2026-08-30',
            'Attendance_Type' => $type,
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }

    private function classes(): array
    {
        return [
            ['Class_ID' => 'CLASS-A', 'Class_Name' => 'Kelas A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLASS-B', 'Class_Name' => 'Kelas B', 'Is_Active' => 'TRUE'],
        ];
    }

    private function students(): array
    {
        return [
            ['Student_ID' => 'STU-A', 'Full_Name' => 'Andi', 'Class_ID' => 'CLASS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-B', 'Full_Name' => 'Budi', 'Class_ID' => 'CLASS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-C', 'Full_Name' => 'Citra', 'Class_ID' => 'CLASS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-D', 'Full_Name' => 'Deni', 'Class_ID' => 'CLASS-B', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-E', 'Full_Name' => 'Eko', 'Class_ID' => 'CLASS-B', 'Is_Active' => 'TRUE'],
        ];
    }
}
