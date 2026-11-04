<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithPdf;
use Tests\Support\PdfDatabaseFixture;
use Tests\TestCase;

class TeacherTransferredStudentScorePdfTest extends TestCase
{
    use InteractsWithPdf, PdfDatabaseFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 17:00:00');
        $this->createPdfFixture();
        $this->createTransferredStudentFixture();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge();
        parent::tearDown();
    }

    public function test_old_teacher_student_pdf_keeps_only_authorized_historical_scores(): void
    {
        $this->actAsTeacher('USR-TEACHER-D', 'Guru Kelas D');
        $this->get(route('teacher.workspace.scores'))
            ->assertOk()
            ->assertSee('Syahwal Pindahan');

        $text = $this->teacherPdf('student', ['student_id' => 'STU-MOVED'], 'transfer-old-teacher-student');

        $this->assertMatchesRegularExpression('/Total Data(?: Nilai)?\s*:\s*2\b/', $text);
        $this->assertStringContainsString('NILAI-LAMA-BERJADWAL-D', $text);
        $this->assertStringContainsString('NILAI-LAMA-TANPA-JADWAL-D', $text);
        $this->assertStringContainsString('KELAS D HISTORIS', strtoupper($text));
        $this->assertStringNotContainsString('NILAI-BARU-KELAS-C', $text);
        $this->assertStringNotContainsString('DI-LUAR-PERIODE', $text);
    }

    public function test_new_teacher_student_pdf_does_not_inherit_old_class_scores(): void
    {
        $this->actAsTeacher('USR-TEACHER-C', 'Guru Kelas C');

        $text = $this->teacherPdf('student', ['student_id' => 'STU-MOVED'], 'transfer-new-teacher-student');

        $this->assertMatchesRegularExpression('/Total Data(?: Nilai)?\s*:\s*1\b/', $text);
        $this->assertStringContainsString('NILAI-BARU-KELAS-C', $text);
        $this->assertStringContainsString('KELAS C SEKARANG', strtoupper($text));
        $this->assertStringNotContainsString('NILAI-LAMA-BERJADWAL-D', $text);
        $this->assertStringNotContainsString('NILAI-LAMA-TANPA-JADWAL-D', $text);
    }

    public function test_class_pdfs_use_historical_class_and_keep_non_transferred_students(): void
    {
        $this->actAsTeacher('USR-TEACHER-D', 'Guru Kelas D');
        $oldClass = $this->teacherPdf('class', ['class_id' => 'CLS-D'], 'transfer-old-class');
        $this->assertMatchesRegularExpression('/Total Siswa\s*:\s*2\b/', $oldClass);
        $this->assertMatchesRegularExpression('/Total Data(?: Nilai)?\s*:\s*3\b/', $oldClass);
        $this->assertStringContainsString('SYAHWAL PINDAHAN', strtoupper($oldClass));
        $this->assertStringContainsString('SISWA TETAP D', strtoupper($oldClass));
        $this->assertStringContainsString('NILAI-SISWA-TIDAK-PINDAH', $oldClass);
        $this->assertStringNotContainsString('NILAI-BARU-KELAS-C', $oldClass);
        $this->get($this->teacherReportRoute('class', ['class_id' => 'CLS-C']))->assertForbidden();

        $this->actAsTeacher('USR-TEACHER-C', 'Guru Kelas C');
        $newClass = $this->teacherPdf('class', ['class_id' => 'CLS-C'], 'transfer-new-class');
        $this->assertMatchesRegularExpression('/Total Siswa\s*:\s*1\b/', $newClass);
        $this->assertMatchesRegularExpression('/Total Data(?: Nilai)?\s*:\s*1\b/', $newClass);
        $this->assertStringContainsString('NILAI-BARU-KELAS-C', $newClass);
        $this->assertStringNotContainsString('NILAI-LAMA-BERJADWAL-D', $newClass);
        $this->assertStringNotContainsString('NILAI-LAMA-TANPA-JADWAL-D', $newClass);
        $this->get($this->teacherReportRoute('class', ['class_id' => 'CLS-D']))->assertForbidden();
    }

    public function test_academic_and_administrator_can_export_complete_student_history(): void
    {
        foreach (['ACADEMIC', 'ADMINISTRATOR'] as $role) {
            $this->pdfActor($role);
            $text = $this->pdfText($this->get(route('scores.export-pdf', [
                'search' => 'STU-MOVED',
                'date_from' => '2026-09-04',
                'date_to' => '2026-11-04',
            ])), 'transfer-complete-history-'.strtolower($role));

            $this->assertMatchesRegularExpression('/Total Data Nilai\s*:\s*3\b/', $text);
            $this->assertStringContainsString('NILAI-LAMA-BERJADWAL-D', $text);
            $this->assertMatchesRegularExpression('/NILAI-LAMA-TANPA-\s*JADWAL-D/', $text);
            $this->assertStringContainsString('NILAI-BARU-KELAS-C', $text);
            $this->assertStringNotContainsString('DI-LUAR-PERIODE', $text);
        }
    }

    private function teacherPdf(string $mode, array $entity, string $name): string
    {
        return $this->pdfText($this->get($this->teacherReportRoute($mode, $entity)), $name);
    }

    private function teacherReportRoute(string $mode, array $entity): string
    {
        return route('teacher.workspace.reports.scores-pdf', $entity + [
            'mode' => $mode,
            'date_from' => '2026-09-04',
            'date_to' => '2026-11-04',
        ]);
    }

    private function actAsTeacher(string $userId, string $name): void
    {
        $this->actingAs(new GenericUser([
            'id' => $userId,
            'User_ID' => $userId,
            'Role_ID' => 'ROLE-TEACHER',
            'Role' => 'TEACHER',
            'Full_Name' => $name,
        ]));
    }

    private function createTransferredStudentFixture(): void
    {
        foreach ([
            ['USR-TEACHER-D', 'teacher.d', 'Guru Kelas D'],
            ['USR-TEACHER-C', 'teacher.c', 'Guru Kelas C'],
        ] as [$userId, $username, $name]) {
            $this->insertPdfRow('MASTER_USER', [
                'User_ID' => $userId,
                'Role_ID' => 'ROLE-TEACHER',
                'Username' => $username,
                'Full_Name' => $name,
            ]);
        }
        $this->insertPdfRow('MASTER_TEACHER', [
            'Teacher_ID' => 'TCH-D', 'Teacher_Code' => 'GURU-D', 'Full_Name' => 'Guru Kelas D',
            'User_ID' => 'USR-TEACHER-D', 'Teaching_Status' => 'Active',
        ]);
        $this->insertPdfRow('MASTER_TEACHER', [
            'Teacher_ID' => 'TCH-C', 'Teacher_Code' => 'GURU-C', 'Full_Name' => 'Guru Kelas C',
            'User_ID' => 'USR-TEACHER-C', 'Teaching_Status' => 'Active',
        ]);
        $this->insertPdfRow('MASTER_CLASS', [
            'Class_ID' => 'CLS-D', 'Class_Code' => 'KLS-D', 'Class_Name' => 'Kelas D Historis',
            'Batch_ID' => 'BAT-AUDIT', 'Program_ID' => 'PRG-AUDIT', 'Homeroom_Teacher_ID' => 'TCH-D',
            'Class_Status' => 'Active', 'Capacity' => 20,
        ]);
        $this->insertPdfRow('MASTER_CLASS', [
            'Class_ID' => 'CLS-C', 'Class_Code' => 'KLS-C', 'Class_Name' => 'Kelas C Sekarang',
            'Batch_ID' => 'BAT-AUDIT', 'Program_ID' => 'PRG-AUDIT', 'Homeroom_Teacher_ID' => 'TCH-C',
            'Class_Status' => 'Active', 'Capacity' => 20,
        ]);
        $this->insertPdfRow('MASTER_STUDENT', [
            'Student_ID' => 'STU-MOVED', 'Student_Number' => 'NIS-MOVED', 'Full_Name' => 'Syahwal Pindahan',
            'Class_ID' => 'CLS-C', 'Batch_ID' => 'BAT-AUDIT', 'Program_ID' => 'PRG-AUDIT',
            'Enrollment_Status' => 'Active', 'Graduation_Status' => 'Not Graduated',
        ]);
        $this->insertPdfRow('MASTER_STUDENT', [
            'Student_ID' => 'STU-STABLE-D', 'Student_Number' => 'NIS-STABLE', 'Full_Name' => 'Siswa Tetap D',
            'Class_ID' => 'CLS-D', 'Batch_ID' => 'BAT-AUDIT', 'Program_ID' => 'PRG-AUDIT',
            'Enrollment_Status' => 'Active', 'Graduation_Status' => 'Not Graduated',
        ]);
        foreach ([
            ['ENR-MOVED-D', 'CLS-D', 'STU-MOVED', 'Historical'],
            ['ENR-MOVED-C', 'CLS-C', 'STU-MOVED', 'Active'],
            ['ENR-STABLE-D', 'CLS-D', 'STU-STABLE-D', 'Active'],
        ] as [$id, $classId, $studentId, $status]) {
            $this->insertPdfRow('MASTER_CLASS_ENROLLMENT', [
                'Enrollment_ID' => $id, 'Class_ID' => $classId, 'Student_ID' => $studentId,
                'Academic_Year_ID' => 'YEAR-AUDIT', 'Status' => $status,
            ]);
        }
        $this->insertPdfRow('MASTER_SCHEDULE', [
            'Schedule_ID' => 'SCH-D-OLD', 'Class_ID' => 'CLS-D', 'Subject_ID' => 'SUB-AUDIT',
            'Teacher_ID' => 'TCH-D', 'Academic_Year_ID' => 'YEAR-AUDIT', 'Day_Of_Week' => 'Monday',
            'Start_Time' => '08:00:00', 'End_Time' => '09:00:00', 'Is_Active' => 0,
        ]);
        $this->insertPdfRow('MASTER_SCHEDULE', [
            'Schedule_ID' => 'SCH-C-NOW', 'Class_ID' => 'CLS-C', 'Subject_ID' => 'SUB-AUDIT',
            'Teacher_ID' => 'TCH-C', 'Academic_Year_ID' => 'YEAR-AUDIT', 'Day_Of_Week' => 'Tuesday',
            'Start_Time' => '08:00:00', 'End_Time' => '09:00:00', 'Is_Active' => 1,
        ]);

        foreach ([
            ['SCR-MOVED-D-SCHEDULE', 'STU-MOVED', 'USR-TEACHER-D', 'SCH-D-OLD', '81.00', '2026-09-04', null, 'NILAI-LAMA-BERJADWAL-D'],
            ['SCR-MOVED-D-DIRECT', 'STU-MOVED', 'USR-TEACHER-D', null, '82.00', '2026-10-01', 'CLS-D', 'NILAI-LAMA-TANPA-JADWAL-D'],
            ['SCR-MOVED-C-DIRECT', 'STU-MOVED', 'USR-TEACHER-C', null, '93.00', '2026-11-04', 'CLS-C', 'NILAI-BARU-KELAS-C'],
            ['SCR-MOVED-OUTSIDE', 'STU-MOVED', 'USR-TEACHER-D', 'SCH-D-OLD', '99.00', '2026-09-03', null, 'DI-LUAR-PERIODE'],
            ['SCR-STABLE-D', 'STU-STABLE-D', 'USR-TEACHER-D', null, '77.00', '2026-10-15', 'CLS-D', 'NILAI-SISWA-TIDAK-PINDAH'],
        ] as [$id, $studentId, $creator, $scheduleId, $score, $date, $classId, $notes]) {
            $details = ['category' => 'ujian_bab', 'assessment_date' => $date, 'notes' => $notes];
            if ($classId !== null) {
                $details['class_id'] = $classId;
            }
            $this->insertPdfRow('MASTER_SCORE', [
                'Score_ID' => $id, 'Student_ID' => $studentId, 'Subject_ID' => 'SUB-AUDIT',
                'Schedule_ID' => $scheduleId, 'Score' => $score, 'Grade' => 'A',
                'Assessment_Category' => 'UJIAN_BAB', 'Created_By' => $creator,
                'Evaluation_Details' => json_encode($details, JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
