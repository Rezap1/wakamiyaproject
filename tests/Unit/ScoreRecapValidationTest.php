<?php

namespace Tests\Unit;

use App\Helpers\CollectionHelper;
use App\Http\Controllers\Academic\ScoreController;
use App\Services\Academic\ScoreService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ScoreRecapValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_recap_rejects_a_start_date_after_the_end_date(): void
    {
        $controller = new ScoreController(Mockery::mock(ScoreService::class));
        $method = new ReflectionMethod($controller, 'validatedDateFilters');
        $request = Request::create('/academic/scores', 'GET', [
            'date_from' => '2026-11-30',
            'date_to' => '2026-11-01',
        ]);

        try {
            $method->invoke($controller, $request);
            $this->fail('Invalid date range was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Tanggal Selesai tidak boleh sebelum Tanggal Mulai.',
                $exception->validator->errors()->first('date_to')
            );
        }
    }

    public function test_recap_accepts_single_bound_filters(): void
    {
        $controller = new ScoreController(Mockery::mock(ScoreService::class));
        $method = new ReflectionMethod($controller, 'validatedDateFilters');

        $from = $method->invoke($controller, Request::create('/academic/scores', 'GET', [
            'date_from' => '2026-11-01',
        ]));
        $to = $method->invoke($controller, Request::create('/academic/scores', 'GET', [
            'date_to' => '2026-11-30',
        ]));

        $this->assertSame('2026-11-01', $from['date_from']);
        $this->assertSame('2026-11-30', $to['date_to']);
    }

    public function test_pagination_links_retain_date_search_category_and_sort_filters(): void
    {
        $request = Request::create('/academic/scores', 'GET', [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'search' => 'Aiko',
            'category' => 'UJIAN_BAB',
            'sort' => 'asc',
        ]);
        $this->app->instance('request', $request);

        $paginator = CollectionHelper::paginate(collect(range(1, 25)), 10)->withQueryString();
        $pageTwoUrl = $paginator->url(2);

        $this->assertStringContainsString('date_from=2026-11-01', $pageTwoUrl);
        $this->assertStringContainsString('date_to=2026-11-30', $pageTwoUrl);
        $this->assertStringContainsString('search=Aiko', $pageTwoUrl);
        $this->assertStringContainsString('category=UJIAN_BAB', $pageTwoUrl);
        $this->assertStringContainsString('sort=asc', $pageTwoUrl);
    }
}
