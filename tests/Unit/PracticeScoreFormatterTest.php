<?php

namespace Tests\Unit;

use App\Support\PracticeQuiz\PracticeScoreFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PracticeScoreFormatterTest extends TestCase
{
    #[DataProvider('scoreProvider')]
    public function test_it_removes_only_unnecessary_trailing_decimal_zeroes(mixed $value, string $expected): void
    {
        $this->assertSame($expected, PracticeScoreFormatter::format($value));
    }

    public static function scoreProvider(): array
    {
        return [
            'integer one hundred' => ['100.00', '100'],
            'integer eighty five' => ['85.00', '85'],
            'single decimal' => ['87.50', '87.5'],
            'two decimals' => ['83.33', '83.33'],
            'zero' => ['0.00', '0'],
        ];
    }

    public function test_it_formats_signed_differences_without_trailing_zeroes(): void
    {
        $this->assertSame('+12.5', PracticeScoreFormatter::signed('12.50'));
        $this->assertSame('-12.5', PracticeScoreFormatter::signed('-12.50'));
        $this->assertSame('0', PracticeScoreFormatter::signed('0.00'));
    }
}
