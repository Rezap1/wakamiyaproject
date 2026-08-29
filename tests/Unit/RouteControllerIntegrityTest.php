<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteControllerIntegrityTest extends TestCase
{
    public function test_every_controller_route_points_to_an_existing_method(): void
    {
        $invalid = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (!str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action, 2);
            if (!class_exists($controller) || !method_exists($controller, $method)) {
                $invalid[] = $route->uri() . ' => ' . $action;
            }
        }

        $this->assertSame([], $invalid, implode(PHP_EOL, $invalid));
    }
}
