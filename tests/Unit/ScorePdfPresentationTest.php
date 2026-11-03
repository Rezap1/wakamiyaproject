<?php

namespace Tests\Unit;

use App\Support\Academic\ScoreValueFormatter;
use Tests\TestCase;

class ScorePdfPresentationTest extends TestCase
{
    public function test_generic_score_pdf_renders_period_and_compact_score_value(): void
    {
        $html = view('pdf.generic_table', [
            'reportTitle' => 'Laporan Nilai',
            'documentNumber' => 'REP-SCORE-001',
            'generatedBy' => 'Penguji',
            'generatedDate' => '3 November 2026, 10:00 WIB',
            'totalRecords' => 1,
            'scopeLabel' => 'Periode penilaian: 1 November 2026 s.d. 30 November 2026',
            'headers' => ['Nilai'],
            'records' => collect([['Score' => '89.5000']]),
            'mapRow' => fn ($row) => [ScoreValueFormatter::format($row['Score'])],
        ])->render();

        $this->assertStringContainsString('Periode penilaian: 1 November 2026 s.d. 30 November 2026', $html);
        $this->assertStringContainsString('89,5', $html);
        $this->assertStringNotContainsString('89.5000', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
    }
}
