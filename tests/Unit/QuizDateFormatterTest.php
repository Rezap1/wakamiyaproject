<?php

namespace Tests\Unit;

use App\Support\Quiz\QuizDateFormatter;
use Tests\TestCase;

class QuizDateFormatterTest extends TestCase
{
    public function test_same_day_range_uses_indonesian_month_without_microseconds(): void
    {
        $value = (new QuizDateFormatter)->range('2026-11-23 03:59:00.000000', '2026-11-23 04:58:00.000000');

        $this->assertSame('23 November 2026, 03:59 – 04:58 WIB', $value);
        $this->assertStringNotContainsString('.000000', $value);
    }

    public function test_cross_day_range_prints_both_complete_dates(): void
    {
        $value = (new QuizDateFormatter)->range('2026-11-23 23:59:00', '2026-11-24 00:15:00');

        $this->assertSame('23 November 2026, 23:59 – 24 November 2026, 00:15 WIB', $value);
    }
}
