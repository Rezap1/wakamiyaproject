<?php

function countFiles($dir, $pattern) {
    if (!is_dir($dir)) return 0;
    $count = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
            $count++;
        }
    }
    return $count;
}

$routesRaw = shell_exec('php artisan route:list --json');
$jsonPos = strpos($routesRaw, '[');
if ($jsonPos !== false) {
    $routesRaw = substr($routesRaw, $jsonPos);
}
$routes = count(json_decode($routesRaw, true) ?? []);

$controllers = countFiles(__DIR__ . '/../app/Http/Controllers', '*.php');
$services = countFiles(__DIR__ . '/../app/Services', '*.php');
$repositories = countFiles(__DIR__ . '/../app/Repositories', '*.php');
$requests = countFiles(__DIR__ . '/../app/Http/Requests', '*.php');
$middleware = countFiles(__DIR__ . '/../app/Http/Middleware', '*.php');
$models = countFiles(__DIR__ . '/../app/Models', '*.php');
$helpers = countFiles(__DIR__ . '/../app/Helpers', '*.php');
$views = countFiles(__DIR__ . '/../resources/views', '*.blade.php');
$tests = countFiles(__DIR__ . '/../tests', '*.php');
$js = countFiles(__DIR__ . '/../resources', '*.js');
$config = countFiles(__DIR__ . '/../config', '*.php');

echo "=== INVENTORY SUMMARY ===\n";
echo "Routes: $routes\n";
echo "Controllers: $controllers\n";
echo "Services: $services\n";
echo "Repositories: $repositories\n";
echo "Requests: $requests\n";
echo "Middleware: $middleware\n";
echo "Models: $models\n";
echo "Helpers: $helpers\n";
echo "Views: $views\n";
echo "Tests: $tests\n";
echo "JS Files: $js\n";
echo "Config Files: $config\n";
