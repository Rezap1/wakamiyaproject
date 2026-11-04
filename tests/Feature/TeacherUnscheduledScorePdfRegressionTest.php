<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithPdf;
use Tests\Support\PdfDatabaseFixture;
use Tests\TestCase;

class TeacherUnscheduledScorePdfRegressionTest extends TestCase
{
    use InteractsWithPdf, PdfDatabaseFixture;

    /** @var list<string> */
    private array $validUnscheduledScoreIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 16:34:00');
        $this->createPdfFixture();
        $this->createProductionShapedTeacherScores();
        $this->pdfActor('TEACHER');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge();
        parent::tearDown();
    }

    public function test_student_pdf_includes_owned_unscheduled_and_scheduled_scores_in_inclusive_period(): void
    {
        $this->assertCount(56, $this->validUnscheduledScoreIds);

        $text = $this->pdfText($this->get(route('teacher.workspace.reports.scores-pdf', [
            'mode' => 'student',
            'student_id' => 'STU-0001',
            'date_from' => '2026-10-26',
            'date_to' => '2026-11-04',
        ])), 'teacher-unscheduled-student-regression');

        $this->assertMatchesRegularExpression('/Total Data(?: Nilai)?\s*:\s*9\b/', $text);
        $this->assertStringContainsString('NILAI-JADWAL-TETAP-MUNCUL', $text);
        $this->assertStringContainsString('NILAI-HISTORIS-CREATED-AT', $text);
        $this->assertStringNotContainsString('DI-LUAR-PERIODE', $text);
        $this->assertStringNotContainsString('CREATOR-GURU-LAIN', $text);
        $this->assertMatchesRegularExpression('/\b90\b/', $text);
        $this->assertStringNotContainsString('90,0', $text);
    }

    public function test_class_pdf_contains_all_56_owned_unscheduled_scores_for_seven_students(): void
    {
        $text = $this->pdfText($this->get(route('teacher.workspace.reports.scores-pdf', [
            'mode' => 'class',
            'class_id' => 'CLS-AUDIT',
            'date_from' => '2026-10-26',
            'date_to' => '2026-11-04',
        ])), 'teacher-unscheduled-class-regression');

        $this->assertMatchesRegularExpression('/Total Siswa\s*:\s*7\b/', $text);
        $this->assertMatchesRegularExpression('/Total Data(?: Nilai)?\s*:\s*57\b/', $text);
        foreach (range(2, 7) as $studentNumber) {
            $this->assertStringContainsString('SISWA REGRESI '.$studentNumber, strtoupper($text));
        }
        $this->assertStringContainsString('TANGGAL-PENILAIAN-BEDA-DARI-CREATED-AT', $text);
        $this->assertStringContainsString('NILAI-HISTORIS-CREATED-AT', $text);
        $this->assertStringContainsString('NILAI-JADWAL-TETAP-MUNCUL', $text);
        $this->assertStringNotContainsString('DI-LUAR-PERIODE', $text);
        $this->assertStringNotContainsString('CREATOR-GURU-LAIN', $text);
    }

    public function test_other_teacher_cannot_export_the_homeroom_class(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-TEACHER-OTHER',
            'User_ID' => 'USR-TEACHER-OTHER',
            'Role_ID' => 'ROLE-TEACHER',
            'Role' => 'TEACHER',
            'Full_Name' => 'Guru Lain',
        ]));

        $this->get(route('teacher.workspace.reports.scores-pdf', [
            'mode' => 'class',
            'class_id' => 'CLS-AUDIT',
            'date_from' => '2026-10-26',
            'date_to' => '2026-11-04',
        ]))->assertForbidden();
    }

    private function createProductionShapedTeacherScores(): void
    {
        $this->insertPdfRow('MASTER_USER', [
            'User_ID' => 'USR-TEACHER-OTHER',
            'Role_ID' => 'ROLE-TEACHER',
            'Username' => 'teacher.other',
            'Full_Name' => 'Guru Lain',
        ]);
        $this->insertPdfRow('MASTER_TEACHER', [
            'Teacher_ID' => 'TCH-OTHER',
            'Teacher_Code' => 'GURU-OTHER',
            'Full_Name' => 'Guru Lain',
            'User_ID' => 'USR-TEACHER-OTHER',
            'Teaching_Status' => 'Active',
        ]);

        foreach (range(2, 7) as $number) {
            $studentId = sprintf('STU-%04d', $number);
            $this->insertPdfRow('MASTER_STUDENT', [
                'Student_ID' => $studentId,
                'Student_Number' => sprintf('NIS-%04d', $number),
                'Full_Name' => 'Siswa Regresi '.$number,
                'Class_ID' => 'CLS-AUDIT',
                'Batch_ID' => 'BAT-AUDIT',
                'Program_ID' => 'PRG-AUDIT',
                'Enrollment_Status' => 'Active',
                'Graduation_Status' => 'Not Graduated',
            ]);
            $this->insertPdfRow('MASTER_CLASS_ENROLLMENT', [
                'Enrollment_ID' => sprintf('ENR-%04d', $number),
                'Class_ID' => 'CLS-AUDIT',
                'Student_ID' => $studentId,
                'Status' => 'Active',
                'Academic_Year_ID' => 'YEAR-AUDIT',
            ]);
        }

        DB::table('MASTER_SCORE')->where('Score_ID', 'SCR-0001')->update([
            'Assignment_ID' => 'ASG-AUDIT',
        ]);
        $this->validUnscheduledScoreIds[] = 'SCR-0001';

        foreach (range(2, 56) as $number) {
            $studentNumber = (($number - 1) % 7) + 1;
            $usesAssessmentDate = $number % 2 === 1;
            $marker = $number === 3
                ? 'TANGGAL-PENILAIAN-BEDA-DARI-CREATED-AT'
                : ($number === 8 ? 'NILAI-HISTORIS-CREATED-AT' : 'REGRESI-NILAI-'.$number);
            $details = [
                'category' => 'ujian_bab',
                'notes' => $marker,
            ];
            if ($usesAssessmentDate) {
                $details['assessment_date'] = '2026-10-26';
            }

            $scoreId = sprintf('SCR-REG-%02d', $number);
            $this->insertPdfRow('MASTER_SCORE', [
                'Score_ID' => $scoreId,
                'Student_ID' => sprintf('STU-%04d', $studentNumber),
                'Subject_ID' => 'SUB-AUDIT',
                'Score' => (string) (80 + ($number % 10)).'.00',
                'Grade' => 'A',
                'Assessment_Category' => 'UJIAN_BAB',
                'Assignment_ID' => 'ASG-AUDIT',
                'Schedule_ID' => null,
                'Created_By' => 'USR-TEACHER',
                'Created_At' => $usesAssessmentDate ? '2026-12-01 09:00:00' : '2026-11-04 09:00:00',
                'Evaluation_Details' => json_encode($details, JSON_THROW_ON_ERROR),
            ]);
            $this->validUnscheduledScoreIds[] = $scoreId;
        }

        $this->insertPdfRow('MASTER_SCORE', [
            'Score_ID' => 'SCR-OUTSIDE',
            'Student_ID' => 'STU-0001',
            'Subject_ID' => 'SUB-AUDIT',
            'Score' => '99.00',
            'Assignment_ID' => 'ASG-AUDIT',
            'Schedule_ID' => null,
            'Created_By' => 'USR-TEACHER',
            'Created_At' => '2026-11-04 09:00:00',
            'Evaluation_Details' => '{"assessment_date":"2026-10-25","notes":"DI-LUAR-PERIODE"}',
        ]);
        $this->insertPdfRow('MASTER_SCORE', [
            'Score_ID' => 'SCR-OTHER-CREATOR',
            'Student_ID' => 'STU-0001',
            'Subject_ID' => 'SUB-AUDIT',
            'Score' => '100.00',
            'Assignment_ID' => 'ASG-AUDIT',
            'Schedule_ID' => null,
            'Created_By' => 'USR-TEACHER-OTHER',
            'Evaluation_Details' => '{"assessment_date":"2026-11-04","notes":"CREATOR-GURU-LAIN"}',
        ]);

        $this->insertPdfRow('MASTER_SCHEDULE', [
            'Schedule_ID' => 'SCH-AUDIT',
            'Class_ID' => 'CLS-AUDIT',
            'Subject_ID' => 'SUB-AUDIT',
            'Teacher_ID' => 'TCH-AUDIT',
            'Academic_Year_ID' => 'YEAR-AUDIT',
            'Day_Of_Week' => 'Wednesday',
            'Start_Time' => '08:00:00',
            'End_Time' => '09:00:00',
        ]);
        $this->insertPdfRow('MASTER_SCORE', [
            'Score_ID' => 'SCR-SCHEDULED',
            'Student_ID' => 'STU-0001',
            'Subject_ID' => 'SUB-AUDIT',
            'Score' => '90.00',
            'Grade' => 'A',
            'Assessment_Category' => 'UJIAN_BAB',
            'Schedule_ID' => 'SCH-AUDIT',
            'Created_By' => 'USR-TEACHER',
            'Evaluation_Details' => '{"assessment_date":"2026-11-04","notes":"NILAI-JADWAL-TETAP-MUNCUL"}',
        ]);
    }
}
