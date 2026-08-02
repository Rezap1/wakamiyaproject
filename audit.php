<?php
$controllers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/Http/Controllers'));
foreach ($controllers as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $modelName = str_replace('Controller.php', '', $file->getFilename());
        $changed = false;
        if (preg_match('/public function store\(Request \$request\)/', $content)) {
            $content = preg_replace('/public function store\(Request \$request\)/', 'public function store(\App\Http\Requests\Store'.$modelName.'Request $request)', $content);
            exec('php artisan make:request Store'.$modelName.'Request');
            $changed = true;
        }
        if (preg_match('/public function update\(Request \$request,/', $content)) {
            $content = preg_replace('/public function update\(Request \$request,/', 'public function update(\App\Http\Requests\Update'.$modelName.'Request $request,', $content);
            exec('php artisan make:request Update'.$modelName.'Request');
            $changed = true;
        }
        if ($changed) {
            file_put_contents($file->getPathname(), $content);
            echo 'Updated ' . $file->getFilename() . PHP_EOL;
        }
    }
}
