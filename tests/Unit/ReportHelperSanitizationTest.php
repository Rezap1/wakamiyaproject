<?php

namespace Tests\Unit;

use App\Helpers\ReportHelper;
use Tests\TestCase;

class ReportHelperSanitizationTest extends TestCase
{
    public function test_formula_like_cells_are_prefixed_as_text(): void
    {
        $this->assertSame("'=SUM(A1:A2)", ReportHelper::sanitizeCsvCell('=SUM(A1:A2)'));
        $this->assertSame("'+cmd", ReportHelper::sanitizeCsvCell('+cmd'));
        $this->assertSame("'-cmd", ReportHelper::sanitizeCsvCell('-cmd'));
        $this->assertSame("'@payload", ReportHelper::sanitizeCsvCell('@payload'));
    }

    public function test_legitimate_numeric_values_are_not_changed(): void
    {
        $this->assertSame(100, ReportHelper::sanitizeCsvCell(100));
        $this->assertSame(4.5, ReportHelper::sanitizeCsvCell(4.5));
        $this->assertSame(2026, ReportHelper::sanitizeCsvCell(2026));
        $this->assertSame('100', ReportHelper::sanitizeCsvCell('100'));
        $this->assertSame('4.5', ReportHelper::sanitizeCsvCell('4.5'));
        $this->assertSame('2026', ReportHelper::sanitizeCsvCell('2026'));
    }
}
