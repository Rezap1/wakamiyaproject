<?php

namespace Tests\Unit;

use Tests\TestCase;

class StudentPdfPresentationTest extends TestCase
{
    public function test_score_pdf_is_semantic_and_human_readable(): void
    {
        $html = view('pdf.student_score_report', [
            'reportTitle' => 'Riwayat Nilai Siswa',
            'documentNumber' => 'REP-TEST-001',
            'generatedBy' => 'Penguji',
            'generatedDate' => '11 September 2026, 10:00 WIB',
            'totalRecords' => 1,
            'student' => ['Full_Name' => 'Aiko Tanaka', 'Student_Number' => 'NIS-001'],
            'scopeLabel' => 'Siswa terautentikasi',
            'averageScore' => 90,
            'records' => collect([['2026-09-10', 'Ujian Bab', 'Bab 1', '90; Catatan: Baik']]),
        ])->render();

        $this->assertStringContainsString('Ujian Bab', $html);
        $this->assertStringContainsString('Nilai / Hasil', $html);
        $this->assertStringContainsString('Siswa terautentikasi', $html);
        $this->assertStringNotContainsString('Evaluation_Details', $html);
        $this->assertStringContainsString('display: table-header-group', $html);
    }

    public function test_attendance_pdf_has_scope_summary_and_empty_state(): void
    {
        $html = view('pdf.student_attendance_report', [
            'reportTitle' => 'Riwayat Kehadiran Siswa',
            'documentNumber' => 'REP-TEST-002',
            'generatedBy' => 'Penguji',
            'generatedDate' => '11 September 2026, 10:00 WIB',
            'totalRecords' => 0,
            'student' => ['Full_Name' => 'Aiko Tanaka', 'Student_Number' => 'NIS-001'],
            'scopeLabel' => 'Siswa terautentikasi',
            'attendanceSummary' => ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0],
            'records' => collect(),
        ])->render();

        $this->assertStringContainsString('Periode & Ruang Lingkup', $html);
        $this->assertStringContainsString('Belum ada riwayat kehadiran.', $html);
        $this->assertStringContainsString('Hadir', $html);
        $this->assertStringContainsString('Alpa', $html);
        $this->assertStringNotContainsString('undefined', strtolower($html));
    }
}
