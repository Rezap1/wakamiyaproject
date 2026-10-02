<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\ValueNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueNormalizerTest extends TestCase
{
    private ValueNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ValueNormalizer('Asia/Jakarta');
    }

    #[DataProvider('booleanValues')]
    public function test_boolean_conversion_preserves_false_and_zero(mixed $source, int $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($source, 'boolean')['value']);
    }

    public static function booleanValues(): array
    {
        return [[true, 1], [false, 0], ['TRUE', 1], ['FALSE', 0], ['1', 1], ['0', 0]];
    }

    public function test_money_is_normalized_without_float_arithmetic(): void
    {
        $this->assertSame('00123', $this->normalizer->normalize('00123', 'identifier')['value']);
        $this->assertSame('1234567890123456.1200', $this->normalizer->normalize('1234567890123456.12', 'decimal')['value']);
        $this->assertSame('0.0000', $this->normalizer->normalize('-0', 'decimal')['value']);
    }

    public function test_date_datetime_and_time_use_deterministic_formats(): void
    {
        $this->assertSame('2026-10-02', $this->normalizer->normalize('02/10/2026', 'date')['value']);
        $this->assertSame('2026-10-02 08:30:00.000000', $this->normalizer->normalize('2026-10-02 08:30:00', 'datetime')['value']);
        $this->assertSame('08:30:00.000000', $this->normalizer->normalize('08:30', 'time')['value']);
    }

    public function test_invalid_json_is_preserved_as_text_and_reported(): void
    {
        $result = $this->normalizer->normalize('{legacy-invalid', 'json_text');

        $this->assertSame('{legacy-invalid', $result['value']);
        $this->assertSame(['invalid_json_preserved_as_text'], $result['warnings']);
    }

    public function test_null_empty_zero_and_false_are_not_conflated(): void
    {
        $this->assertNull($this->normalizer->normalize(null, 'integer')['value']);
        $this->assertNull($this->normalizer->normalize('', 'integer')['value']);
        $this->assertSame(0, $this->normalizer->normalize('0', 'integer')['value']);
        $this->assertSame(0, $this->normalizer->normalize(false, 'boolean')['value']);
    }

    public function test_invalid_conversion_fails_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize('12.99999', 'decimal');
    }

    public function test_google_float_with_excess_money_scale_fails_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(12.99999, 'decimal');
    }

    public function test_bounded_columns_cannot_be_silently_truncated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(str_repeat('x', 192), 'identifier');
    }
}
