<?php

$dir = new RecursiveDirectoryIterator('resources/views');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        
        // Skip dashboard views as they are already done
        if (strpos(str_replace('\\', '/', $path), 'resources/views/dashboard/') === 0) continue;
        if (strpos(str_replace('\\', '/', $path), 'resources/views/components/') === 0) continue;
        if (strpos(str_replace('\\', '/', $path), 'resources/views/layouts/') === 0) continue;
        
        $originalContent = file_get_contents($path);
        $content = $originalContent;
        
        $content = preg_replace('/\bbg-white(?!\s+dark:bg-)/', 'bg-white dark:bg-slate-900', $content);
        $content = preg_replace('/\btext-gray-900(?!\s+dark:text-)/', 'text-gray-900 dark:text-white', $content);
        $content = preg_replace('/\btext-gray-800(?!\s+dark:text-)/', 'text-gray-800 dark:text-slate-200', $content);
        $content = preg_replace('/\btext-gray-700(?!\s+dark:text-)/', 'text-gray-700 dark:text-slate-300', $content);
        $content = preg_replace('/\btext-gray-600(?!\s+dark:text-)/', 'text-gray-600 dark:text-slate-400', $content);
        $content = preg_replace('/\btext-gray-500(?!\s+dark:text-)/', 'text-gray-500 dark:text-slate-400', $content);
        $content = preg_replace('/\bborder-gray-100(?!\s+dark:border-)/', 'border-gray-100 dark:border-slate-800', $content);
        $content = preg_replace('/\bborder-gray-200(?!\s+dark:border-)/', 'border-gray-200 dark:border-slate-700', $content);
        $content = preg_replace('/\bborder-gray-300(?!\s+dark:border-)/', 'border-gray-300 dark:border-slate-600', $content);
        $content = preg_replace('/\bbg-gray-50(?!\s+dark:bg-)/', 'bg-gray-50 dark:bg-slate-800/50', $content);
        $content = preg_replace('/\bbg-gray-100(?!\s+dark:bg-)/', 'bg-gray-100 dark:bg-slate-800', $content);
        $content = preg_replace('/\bbg-gray-200(?!\s+dark:bg-)/', 'bg-gray-200 dark:bg-slate-700', $content);
        $content = preg_replace('/\bhover:bg-white(?!\s+dark:hover:bg-)/', 'hover:bg-white dark:hover:bg-slate-700', $content);
        $content = preg_replace('/\bhover:bg-gray-50(?!\s+dark:hover:bg-)/', 'hover:bg-gray-50 dark:hover:bg-slate-800', $content);
        $content = preg_replace('/\bhover:bg-gray-100(?!\s+dark:hover:bg-)/', 'hover:bg-gray-100 dark:hover:bg-slate-700', $content);
        $content = preg_replace('/\bdivide-gray-200(?!\s+dark:divide-)/', 'divide-gray-200 dark:divide-slate-700', $content);
        $content = preg_replace('/\btext-gray-400(?!\s+dark:text-)/', 'text-gray-400 dark:text-slate-500', $content);
        
        if ($content !== $originalContent) {
            file_put_contents($path, $content);
            echo "Updated: $path\n";
        }
    }
}
echo "Done!\n";
