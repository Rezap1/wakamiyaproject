<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/resources/views'));
$undefinedRoutes = [];
foreach($files as $f) {
    if($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $content = file_get_contents($f->getPathname());
        preg_match_all('/route\(\'([^\']+)\'/', $content, $matches);
        if(!empty($matches[1])) {
            foreach($matches[1] as $r) {
                if(!Route::has($r)) {
                    $undefinedRoutes[$r][] = $f->getPathname();
                }
            }
        }
    }
}

foreach($undefinedRoutes as $route => $paths) {
    echo "UNDEFINED ROUTE: " . $route . "\n";
    foreach(array_unique($paths) as $path) {
        echo "  - " . str_replace(__DIR__, '', $path) . "\n";
    }
}
echo "DONE\n";
