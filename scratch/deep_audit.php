<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;

function scanDirectory($dir, $pattern, $callback) {
    if (!is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
            $content = file_get_contents($file->getPathname());
            $callback($file->getPathname(), $content);
        }
    }
}

echo "=== WMS H8.17 ADVANCED DEEP AUDIT ===\n\n";

$routes = Route::getRoutes();

$unprotectedRoutes = [];
$missingRoleRoutes = [];

// Excluded public routes
$publicRouteNames = [
    'login', 'login.perform', 'logout',
    'verify.receipt', 'verify.invoice', 'verify.payslip', 'verify.leave', 'verify.overtime',
    'sanctum.csrf-cookie', 'ignition.healthCheck', 'ignition.executeSolution', 'ignition.updateConfig'
];

foreach ($routes as $route) {
    $uri = $route->uri();
    $name = $route->getName();
    $methods = implode('|', $route->methods());
    $middleware = (array) ($route->getAction()['middleware'] ?? []);
    $middlewareStr = implode(', ', $middleware);

    if (in_array($name, $publicRouteNames, true) || str_starts_with($uri, '_') || str_starts_with($uri, 'sanctum/')) {
        continue;
    }

    // Check if auth middleware is present
    $hasAuth = false;
    foreach ($middleware as $m) {
        if (is_string($m) && (str_contains($m, 'auth') || str_contains($m, 'SignedPublicOrAuth'))) {
            $hasAuth = true;
            break;
        }
    }

    if (!$hasAuth) {
        $unprotectedRoutes[] = "{$methods} /{$uri} [name: {$name}] - Middleware: ({$middlewareStr})";
    }

    // Check if role check or permission check is present for protected non-profile/dashboard routes
    $hasRole = false;
    foreach ($middleware as $m) {
        if (is_string($m) && (str_contains($m, 'role') || str_contains($m, 'permission') || str_contains($m, 'SignedPublicOrAuth'))) {
            $hasRole = true;
            break;
        }
    }

    if (!$hasRole && !in_array($name, [
        'dashboard', 'profile.index', 'profile.update',
        'dashboard.personal-payroll', 'dashboard.personal-payroll.proof',
        'notifications.index', 'notifications.show', 'notifications.mark-read', 'notifications.markAllRead', 'notifications.mark-all-read', 'notifications.read', 'notifications.archive', 'notifications.destroy',
        'notifications.preview-pdf', 'notifications.export-pdf', 'notifications.export-excel', 'notifications.export-csv', 'notifications.print',
        'search.index', 'search.overlay', 'search.clearHistory',
        'activity.index', 'activity.export', 'activity.preview-pdf', 'activity.export-pdf', 'activity.export-excel', 'activity.export-csv', 'activity.print',
        'hr.attendance.qr.scanner', 'hr.attendance.qr.scan', 'profile.password.update',
        'attendance.qr.index', 'attendance.qr.store', 'attendance.qr.preview', 'attendance.qr.print', 'attendance.qr.pdf', 'attendance.qr.availability', 'attendance.qr.deactivate', 'attendance.qr.destroy',
        'attendance.scan.entry', 'attendance.scan.verify'
    ], true)) {
        $missingRoleRoutes[] = "{$methods} /{$uri} [name: {$name}] - Middleware: ({$middlewareStr})";
    }
}

echo "[1] Unprotected Non-Public Routes (missing auth): " . count($unprotectedRoutes) . "\n";
foreach ($unprotectedRoutes as $ur) {
    echo "  - $ur\n";
}

echo "\n[2] Routes Missing Role/Permission Middleware: " . count($missingRoleRoutes) . "\n";
foreach ($missingRoleRoutes as $mr) {
    echo "  - $mr\n";
}

// 3. FILE UPLOAD SECURITY AUDIT
$uploadFindings = [];
scanDirectory(__DIR__ . '/../app', '*.php', function($path, $content) use (&$uploadFindings) {
    $relPath = str_replace(dirname(__DIR__) . '/', '', $path);
    if (str_contains($content, 'storeAs') || str_contains($content, 'store(')) {
        if (str_contains($content, 'file(') || str_contains($content, '$request->file')) {
            if (!str_contains($content, 'mimes') && !str_contains($content, 'validate') && !str_contains($content, 'denied') && !str_contains($content, 'allowed')) {
                $uploadFindings[] = "Upload processing in $relPath may lack extension validation";
            }
        }
    }
});

echo "\n[3] File Upload Security Audit: " . count($uploadFindings) . "\n";
foreach ($uploadFindings as $uf) {
    echo "  - $uf\n";
}

// 4. CHECK ALL CONTROLLER ACTIONS FOR STUDENT MAPPING / OWNERSHIP
$studentPortalFindings = [];
scanDirectory(__DIR__ . '/../app/Http/Controllers/Student', '*.php', function($path, $content) use (&$studentPortalFindings) {
    $relPath = str_replace(dirname(__DIR__) . '/', '', $path);
    if (preg_match('/public function \w+\s*\(/', $content)) {
        if (str_contains($content, 'STU-001')) {
            $studentPortalFindings[] = "STU-001 hardcoded in $relPath";
        }
    }
});

echo "\n[4] Student Portal Hardcoded Identity Check: " . count($studentPortalFindings) . "\n";
foreach ($studentPortalFindings as $spf) {
    echo "  - $spf\n";
}

echo "\n=== ADVANCED AUDIT COMPLETE ===\n";
