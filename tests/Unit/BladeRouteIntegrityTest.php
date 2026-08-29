<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class BladeRouteIntegrityTest extends TestCase
{
    public function test_all_literal_blade_route_references_exist(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);
            preg_match_all('/\broute\(\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as [$routeName, $offset]) {
                if (!Route::has($routeName)) {
                    $missing[] = $this->location($file, $source, $offset) . " => {$routeName}";
                }
            }
        }

        $this->assertSame([], $missing, implode(PHP_EOL, $missing));
    }

    public function test_every_post_form_in_blade_has_csrf_directive(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);
            preg_match_all('/<form\b[^>]*\bmethod\s*=\s*[\'\"]post[\'\"][^>]*>.*?<\/form>/is', $source, $forms, PREG_OFFSET_CAPTURE);

            foreach ($forms[0] as [$form, $offset]) {
                if (!str_contains($form, '@csrf')) {
                    $missing[] = $this->location($file, $source, $offset);
                }
            }
        }

        $this->assertSame([], $missing, implode(PHP_EOL, $missing));
    }

    public function test_literal_form_routes_accept_the_effective_form_method(): void
    {
        $mismatches = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);
            preg_match_all('/<form\b[^>]*>.*?<\/form>/is', $source, $forms, PREG_OFFSET_CAPTURE);

            foreach ($forms[0] as [$form, $offset]) {
                if (!preg_match('/\baction\s*=\s*[\'\"][^\'\"]*route\(\s*[\'\"]([^\'\"]+)[\'\"]/', $form, $routeMatch)) {
                    continue;
                }

                $route = Route::getRoutes()->getByName($routeMatch[1]);
                if (!$route) {
                    continue;
                }

                $method = preg_match('/@method\(\s*[\'\"](PUT|PATCH|DELETE)[\'\"]\s*\)/i', $form, $spoofed)
                    ? strtoupper($spoofed[1])
                    : (preg_match('/\bmethod\s*=\s*[\'\"](GET|POST)[\'\"]/i', $form, $htmlMethod)
                        ? strtoupper($htmlMethod[1])
                        : 'GET');

                $acceptedMethods = collect(Route::getRoutes())
                    ->filter(fn ($candidate) => $candidate->uri() === $route->uri())
                    ->flatMap(fn ($candidate) => $candidate->methods())
                    ->unique()
                    ->values()
                    ->all();

                if (!in_array($method, $acceptedMethods, true)) {
                    $mismatches[] = $this->location($file, $source, $offset)
                        . " => {$routeMatch[1]} expects " . implode('|', $acceptedMethods) . ", form uses {$method}";
                }
            }
        }

        $this->assertSame([], $mismatches, implode(PHP_EOL, $mismatches));
    }

    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function location(string $file, string $source, int $offset): string
    {
        $line = substr_count(substr($source, 0, $offset), "\n") + 1;

        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file) . ':' . $line;
    }
}
