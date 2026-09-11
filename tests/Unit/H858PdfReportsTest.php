<?php

namespace Tests\Unit;

use Tests\TestCase;
use Barryvdh\DomPDF\Facade\Pdf;

class H858PdfReportsTest extends TestCase
{
    public function test_teacher_score_report_is_scoped_and_human_readable(): void
    {
        $html = view('pdf.teacher_score_report', [
            'reportTitle' => 'Laporan Nilai Guru',
            'teacherName' => 'Budi Pengajar',
            'scopeLabel' => 'Guru: Budi Pengajar | Kelas: Kelas A | Mata pelajaran: Bahasa Jepang',
            'records' => collect([[
                'date' => '2026-09-10', 'student_name' => 'Aiko Tanaka', 'class_name' => 'Kelas A',
                'subject_name' => 'Bahasa Jepang', 'category' => 'Ujian Bab', 'title' => 'Bab 1',
                'score' => 90, 'grade' => 'A', 'status' => 'Published', 'details' => 'Pronunciation: Baik',
            ]]),
        ])->render();

        $this->assertStringContainsString('Ujian Bab', $html);
        $this->assertStringContainsString('Aiko Tanaka', $html);
        $this->assertStringContainsString('Kelas A', $html);
        $this->assertStringNotContainsString('Evaluation_Details', $html);
        $this->assertStringContainsString('display: table-header-group', $html);
    }

    public function test_teacher_score_empty_state_is_explicit(): void
    {
        $html = view('pdf.teacher_score_report', ['records' => collect()])->render();
        $this->assertStringContainsString('Belum ada data nilai.', $html);
    }

    public function test_academic_attendance_report_contains_filters_localized_status_and_empty_roster_state(): void
    {
        $html = view('pdf.academic_attendance_report', [
            'scopeLabel' => 'Kelas: Kelas A | Periode: 2026-09-10',
            'filterLabel' => 'Pencarian: Semua | Status: Semua',
            'records' => collect([[
                'date' => '2026-09-10', 'day' => 'Kamis', 'student_name' => 'Aiko Tanaka',
                'student_number' => 'NIS-1', 'class_name' => 'Kelas A', 'schedule' => 'Bahasa Jepang | Kelas A',
                'check_in' => '08:00', 'check_out' => '12:00', 'status' => 'Hadir', 'notes' => '-',
            ]]),
        ])->render();

        $this->assertStringContainsString('Kamis', $html);
        $this->assertStringContainsString('Hadir', $html);
        $this->assertStringContainsString('Periode: 2026-09-10', $html);
        $this->assertStringContainsString('display: table-header-group', $html);

        $empty = view('pdf.academic_attendance_report', ['records' => collect()])->render();
        $this->assertStringContainsString('Belum ada data presensi sesuai filter.', $empty);
    }

    public function test_both_report_views_generate_pdf_bytes(): void
    {
        $teacherPdf = Pdf::loadView('pdf.teacher_score_report', ['records' => collect(), 'reportTitle' => 'Laporan Nilai Guru']);
        $attendancePdf = Pdf::loadView('pdf.academic_attendance_report', ['records' => collect(), 'reportTitle' => 'Laporan Presensi Akademik']);

        $this->assertStringStartsWith('%PDF-', $teacherPdf->output());
        $this->assertStringStartsWith('%PDF-', $attendancePdf->output());
    }

    public function test_report_routes_keep_existing_role_boundaries(): void
    {
        $teacherRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('teacher.workspace.reports.scores-pdf');
        $attendanceRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('attendances.export-pdf');

        $this->assertNotNull($teacherRoute);
        $this->assertNotNull($attendanceRoute);
        $this->assertTrue(collect($teacherRoute->gatherMiddleware())->contains(fn ($m) => str_contains($m, 'role:TEACHER')));
        $this->assertTrue(collect($attendanceRoute->gatherMiddleware())->contains(fn ($m) => str_contains($m, 'role:ACADEMIC,ADMINISTRATOR')));
    }
}
