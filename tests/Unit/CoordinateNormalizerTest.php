<?php

namespace Tests\Unit;

use App\Support\CoordinateNormalizer;
use PHPUnit\Framework\TestCase;

class CoordinateNormalizerTest extends TestCase
{
    public function test_grouped_spreadsheet_coordinates_are_normalized_without_broad_fallback(): void
    {
        $this->assertSame(-6.812391, CoordinateNormalizer::parse('-6.812.391', -90, 90));
        $this->assertSame(107.194458, CoordinateNormalizer::parse('107.194.458', -180, 180));
        $this->assertSame(-6.812391, CoordinateNormalizer::parse('-6,812391', -90, 90));
    }

    public function test_empty_out_of_range_and_ambiguous_coordinates_are_rejected(): void
    {
        $this->assertNull(CoordinateNormalizer::parse('', -90, 90));
        $this->assertNull(CoordinateNormalizer::parse('91.000000', -90, 90));
        $this->assertNull(CoordinateNormalizer::parse('-6.81.23', -90, 90));
        $this->assertNull(CoordinateNormalizer::parse('not-a-coordinate', -180, 180));
    }
}
