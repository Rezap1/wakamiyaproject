<?php

// Read-only route/source inventory. Never boots against the production database.
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'WMS_RUNTIME_DB_CONNECTION' => 'sqlite', 'WMS_RUNTIME_DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes = [];
$downloads = [];
foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
    $action = $route->getActionName();
    if (preg_match('/pdf|print|receipt|download|export/i', $action.' '.$route->uri())) {
        $downloads[] = ['methods' => $route->methods(), 'uri' => $route->uri(), 'name' => $route->getName(), 'action' => $action, 'middleware' => $route->gatherMiddleware()];
    }
    if (preg_match('/pdf|print|receipt/i', $action.' '.$route->uri())) {
        $routes[] = ['methods' => $route->methods(), 'uri' => $route->uri(), 'name' => $route->getName(), 'action' => $action, 'middleware' => $route->gatherMiddleware()];
    }
}
$directory = __DIR__.'/../../docs/audits/pdf-integrity';
if (!is_dir($directory)) { mkdir($directory, 0777, true); }
file_put_contents($directory.'/routes.json', json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($directory.'/all-export-download-routes.json', json_encode($downloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo count($routes)." PDF/print routes inventoried.\n";
