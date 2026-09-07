<?php

namespace Tests\Unit;

use App\Support\Presentation\IndonesianPresentation;
use PHPUnit\Framework\TestCase;

class IndonesianPresentationTest extends TestCase
{
    public function test_days_roles_and_statuses_are_localized_without_changing_keys(): void
    {
        $this->assertSame('Senin', IndonesianPresentation::day('Monday'));
        $this->assertSame('Rabu', IndonesianPresentation::day('wednesday'));
        $this->assertSame('Siswa', IndonesianPresentation::role('STUDENT'));
        $this->assertSame('Keuangan', IndonesianPresentation::role('FINANCE'));
        $this->assertSame('Dibayar Sebagian', IndonesianPresentation::status('Partial Paid'));
        $this->assertSame('Menunggu Pembayaran', IndonesianPresentation::status('Waiting Payment'));
    }

    public function test_dates_use_indonesian_month_names_and_numeric_day(): void
    {
        $this->assertSame('Sabtu, 5 September 2026', IndonesianPresentation::date('2026-09-05', 'l, j F Y'));
    }
}
