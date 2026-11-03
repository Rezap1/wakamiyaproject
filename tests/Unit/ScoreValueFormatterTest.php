<?php

namespace Tests\Unit;

use App\Support\Academic\ScoreValueFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScoreValueFormatterTest extends TestCase
{
    #[DataProvider('scoreValues')]
    public function test_it_removes_only_unnecessary_decimal_zeroes(mixed $value, string $expected): void
    {
        $this->assertSame($expected, ScoreValueFormatter::format($value));
    }

    public static function scoreValues(): array
    {
        return [
            ['89.0000', '89'],
            ['55.0000', '55'],
            ['100.0000', '100'],
            ['89.5000', '89,5'],
            ['89.7500', '89,75'],
            ['0.0000', '0'],
            ['000.2500', '0,25'],
        ];
    }

    public function test_it_keeps_number_input_compatible_and_does_not_turn_zero_into_empty_text(): void
    {
        $this->assertSame('89.5', ScoreValueFormatter::forInput('89.5000'));
        $this->assertSame('0', ScoreValueFormatter::forInput('0.0000'));
        $this->assertSame('-', ScoreValueFormatter::format(null));
    }
}
