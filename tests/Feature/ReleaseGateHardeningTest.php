<?php

namespace Tests\Feature;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReleaseGateHardeningTest extends TestCase
{
    public function test_runtime_route_map_has_no_qa_login_backdoor(): void
    {
        $hasQaLogin = collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($route) => $route->uri() === 'qa-login');

        $this->assertFalse($hasQaLogin);
    }

    public function test_all_attendance_write_endpoints_are_rate_limited(): void
    {
        foreach ([
            'hr.attendance.qr.scan',
            'attendances.student.scan',
            'attendance.scan.verify',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route {$routeName} is missing.");
            $this->assertContains('throttle:20,1', $route->gatherMiddleware());
        }
    }

    public function test_production_exception_message_is_not_exposed(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        $controller = new class extends Controller
        {
            public function present(\Throwable $exception): string
            {
                return $this->safeExceptionMessage($exception, 'Pesan aman.');
            }
        };

        $message = $controller->present(new \RuntimeException('credential path: C:\\secret.json'));

        $this->assertSame('Pesan aman.', $message);
        $this->assertStringNotContainsString('secret.json', $message);
    }

    public function test_controllers_do_not_send_raw_exception_messages_to_browser(): void
    {
        $violations = [];
        $directory = new \RecursiveDirectoryIterator(app_path('Http/Controllers'));
        $files = new \RecursiveIteratorIterator($directory);

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php' || $file->getFilename() === 'Controller.php') {
                continue;
            }

            foreach (file($file->getPathname()) as $lineNumber => $line) {
                if (str_contains($line, 'getMessage()') && !str_contains($line, 'Log::')) {
                    $violations[] = $file->getPathname() . ':' . ($lineNumber + 1);
                }
            }
        }

        $this->assertSame([], $violations, 'Raw exception messages found: ' . implode(', ', $violations));
    }

    public function test_permanent_scanner_uses_ssot_radius_before_rendering_it(): void
    {
        $source = file_get_contents(resource_path('views/attendance/qr/permanent_scanner.blade.php'));

        $assignment = strpos($source, '$qrRadius =');
        $firstRender = strpos($source, '{{ $qrRadiusLabel }}');

        $this->assertNotFalse($assignment);
        $this->assertNotFalse($firstRender);
        $this->assertLessThan($firstRender, $assignment);
        $this->assertStringNotContainsString("get('LPK_ALLOWED_RADIUS_METERS', 20)", $source);
    }

    public function test_qr_hmac_signing_fails_closed_without_app_key(): void
    {
        config(['app.key' => null]);

        foreach ([
            \App\Services\HR\QRAttendanceService::class,
            \App\Services\Academic\StudentQRAttendanceService::class,
        ] as $serviceClass) {
            $reflection = new \ReflectionClass($serviceClass);
            $service = $reflection->newInstanceWithoutConstructor();
            $method = $reflection->getMethod('signingKey');

            try {
                $method->invoke($service);
                $this->fail("{$serviceClass} accepted an empty APP_KEY.");
            } catch (\ReflectionException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                $this->assertInstanceOf(\RuntimeException::class, $exception);
                $this->assertStringContainsString('belum dikonfigurasi', $exception->getMessage());
            }
        }
    }

    public function test_vite_manifest_entries_remain_source_relative_on_windows(): void
    {
        $source = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString(
            "'resources/css/app.css': 'resources/css/app.css'",
            $source
        );
        $this->assertStringContainsString(
            "'resources/js/app.js': 'resources/js/app.js'",
            $source
        );
    }

    public function test_marketing_audit_helper_uses_real_controller_dependencies(): void
    {
        $source = file_get_contents(base_path('audit_dashboards.php'));

        $this->assertStringNotContainsString('MarketingDashboardService', $source);
        $this->assertStringContainsString('CompanyRepositoryInterface', $source);
        $this->assertStringContainsString('DocumentRepositoryInterface', $source);
    }
}
