<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SearchRouteTest extends TestCase
{
    public function test_global_search_routes_are_registered_to_real_controller(): void
    {
        $this->assertTrue(Route::has('search.index'));
        $this->assertTrue(Route::has('search.overlay'));
        $this->assertTrue(Route::has('search.clearHistory'));

        $this->assertSame('search', Route::getRoutes()->getByName('search.index')->uri());
        $this->assertSame('search/overlay', Route::getRoutes()->getByName('search.overlay')->uri());
        $this->assertSame('search/clear-history', Route::getRoutes()->getByName('search.clearHistory')->uri());
    }
}
