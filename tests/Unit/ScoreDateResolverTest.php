<?php

namespace Tests\Unit;

use App\Support\Academic\ScoreDateResolver;
use Tests\TestCase;

class ScoreDateResolverTest extends TestCase
{
    public function test_it_prioritizes_the_assessment_date_and_supports_legacy_metadata(): void
    {
        $this->assertSame('2026-11-03', ScoreDateResolver::value([
            'Assessment_Date' => '2026-11-03',
            'Created_At' => '2026-10-01 10:00:00',
        ]));

        $this->assertSame('2026-11-04', ScoreDateResolver::value([
            'Evaluation_Details' => '{"assessment_date":"2026-11-04"}',
            'Created_At' => '2026-10-01 10:00:00',
        ]));
    }

    public function test_it_uses_created_at_when_a_dedicated_date_is_absent_or_invalid(): void
    {
        $this->assertSame('2026-11-05', ScoreDateResolver::value([
            'Created_At' => '2026-11-05 08:00:00',
        ]));

        $this->assertSame('2026-11-05', ScoreDateResolver::value([
            'Assessment_Date' => 'not-a-date',
            'Created_At' => '2026-11-05 08:00:00',
        ]));
    }

    public function test_inclusive_single_bound_and_range_filters(): void
    {
        $scores = collect([
            ['Score_ID' => 'BEFORE', 'Assessment_Date' => '2026-10-31'],
            ['Score_ID' => 'START', 'Assessment_Date' => '2026-11-01'],
            ['Score_ID' => 'MIDDLE', 'Assessment_Date' => '2026-11-15'],
            ['Score_ID' => 'END', 'Assessment_Date' => '2026-11-30'],
            ['Score_ID' => 'AFTER', 'Assessment_Date' => '2026-12-01'],
        ]);

        $this->assertSame(
            ['START', 'MIDDLE', 'END'],
            ScoreDateResolver::filter($scores, '2026-11-01', '2026-11-30')->pluck('Score_ID')->values()->all()
        );
        $this->assertSame(
            ['END', 'AFTER'],
            ScoreDateResolver::filter($scores, '2026-11-30', null)->pluck('Score_ID')->values()->all()
        );
        $this->assertSame(
            ['BEFORE', 'START'],
            ScoreDateResolver::filter($scores, null, '2026-11-01')->pluck('Score_ID')->values()->all()
        );
        $this->assertTrue(ScoreDateResolver::filter($scores, '2027-01-01', '2027-01-31')->isEmpty());
    }
}
