<?php

namespace Tests\Unit;

use App\Services\Academic\AttendanceLegacyClassifier;
use Tests\TestCase;

class AttendanceLegacyClassificationTest extends TestCase
{
    private AttendanceLegacyClassifier $classifier;
    private array $classes = [
        ['Class_ID' => 'CLASS-A', 'Class_Name' => 'Kelas A'],
        ['Class_ID' => 'SCH-AMB', 'Class_Name' => 'Kelas Ambiguous'],
    ];
    private array $schedules = [
        ['Schedule_ID' => 'SCHEDULE-1', 'Class_ID' => 'CLASS-A'],
        ['Schedule_ID' => 'SCH-AMB', 'Class_ID' => 'CLASS-A'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new AttendanceLegacyClassifier();
    }

    public function test_explicit_types_take_precedence(): void
    {
        $this->assertSame('CLASS_QR', $this->classify(['Attendance_Type' => 'CLASS_QR', 'Class_ID' => 'CLASS-A'])['classification']);
        $this->assertSame('CLASS_MANUAL', $this->classify(['Attendance_Type' => 'CLASS_MANUAL', 'Class_ID' => 'CLASS-A'])['classification']);
        $this->assertSame('SCHEDULE', $this->classify(['Attendance_Type' => 'SCHEDULE', 'Schedule_ID' => 'SCHEDULE-1'])['classification']);
        $this->assertSame('EMPLOYEE', $this->classify(['Attendance_Type' => 'EMPLOYEE', 'Employee_ID' => 'EMP-1'])['classification']);
    }

    public function test_legacy_schedule_value_matching_class_only_is_class_candidate(): void
    {
        $result = $this->classify(['Student_ID' => 'STU-1', 'Schedule_ID' => 'CLASS-A']);

        $this->assertSame('LEGACY_CLASS_CANDIDATE', $result['classification']);
        $this->assertSame('CLASS-A', $result['class_id']);
        $this->assertNull($result['schedule_id']);
        $this->assertSame('CLASS-A', $result['original_schedule_id']);
        $this->assertSame('CLASS_ONLY', $result['resolution']);
        $this->assertSame('Kelas A', $result['class_name']);
    }

    public function test_legacy_class_candidate_never_invents_schedule(): void
    {
        $result = $this->classify(['Student_ID' => 'STU-1', 'Schedule_ID' => 'CLASS-A']);

        $this->assertNull($result['schedule_id']);
        $this->assertFalse($result['is_schedule_based']);
    }

    public function test_value_existing_as_class_and_schedule_is_ambiguous(): void
    {
        $this->assertSame('AMBIGUOUS', $this->classify(['Student_ID' => 'STU-1', 'Schedule_ID' => 'SCH-AMB'])['classification']);
    }

    public function test_conflicting_class_and_schedule_is_ambiguous(): void
    {
        $this->assertSame('AMBIGUOUS', $this->classify([
            'Student_ID' => 'STU-1', 'Class_ID' => 'CLASS-B', 'Schedule_ID' => 'SCHEDULE-1',
        ])['classification']);
    }

    public function test_valid_schedule_resolves_class(): void
    {
        $result = $this->classify(['Student_ID' => 'STU-1', 'Attendance_Type' => 'SCHEDULE', 'Schedule_ID' => 'SCHEDULE-1']);

        $this->assertSame('SCHEDULE', $result['classification']);
        $this->assertSame('CLASS-A', $result['class_id']);
        $this->assertSame('SCHEDULE-1', $result['schedule_id']);
    }

    public function test_invalid_explicit_schedule_does_not_fallback_to_class(): void
    {
        $result = $this->classify([
            'Student_ID' => 'STU-1', 'Attendance_Type' => 'SCHEDULE',
            'Class_ID' => 'CLASS-A', 'Schedule_ID' => 'CLASS-A',
        ]);

        $this->assertSame('UNKNOWN', $result['classification']);
        $this->assertNull($result['class_id']);
        $this->assertSame('SCHEDULE_UNRESOLVED', $result['resolution']);
    }

    public function test_employee_evidence_is_separated(): void
    {
        $this->assertSame('EMPLOYEE', $this->classify(['Employee_ID' => 'EMP-1', 'Schedule_ID' => 'CLASS-A'])['classification']);
    }

    public function test_missing_student_and_invalid_identifiers_are_unknown(): void
    {
        $this->assertSame('UNKNOWN', $this->classify(['Schedule_ID' => 'NOT-FOUND'])['classification']);
    }

    private function classify(array $row): array
    {
        return $this->classifier->classify($row, $this->classes, $this->schedules);
    }
}
