<?php

$webFile = __DIR__ . '/../routes/web.php';
$webContent = file_get_contents($webFile);
$webContent = preg_replace('/->middleware\(\'permission:[A-Z]+,(view|create|edit|delete)\'\)/', '', $webContent);
file_put_contents($webFile, $webContent);

$appFile = __DIR__ . '/../resources/views/layouts/app.blade.php';
$appContent = file_get_contents($appFile);
$appContent = preg_replace('/@can\(\'permission\', \[[^\]]+\]\)\r?\n/m', '', $appContent);
$appContent = preg_replace('/@endcan\r?\n/m', '', $appContent);
file_put_contents($appFile, $appContent);

$views = ['users', 'companies', 'permissions', 'students', 'teachers'];
foreach ($views as $view) {
    $indexFile = __DIR__ . '/../resources/views/' . $view . '/index.blade.php';
    if (file_exists($indexFile)) {
        $content = file_get_contents($indexFile);
        $content = preg_replace('/@can\(\'permission\', \[[^\]]+\]\)\r?\n/m', '', $content);
        $content = preg_replace('/@endcan\r?\n/m', '', $content);
        file_put_contents($indexFile, $content);
    }
}

echo "Reverted.\n";
