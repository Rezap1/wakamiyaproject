<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/Http/Requests'));
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (strpos($content, 'return false;') !== false) {
            $content = str_replace('return false;', 'return true;', $content);
            file_put_contents($file->getPathname(), $content);
            echo 'Fixed ' . $file->getFilename() . PHP_EOL;
        }
    }
}
