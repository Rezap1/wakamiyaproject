<?php

$dir = new RecursiveDirectoryIterator('resources/views');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        
        // Skip dashboard framework
        if (strpos(str_replace('\\', '/', $path), 'resources/views/dashboard/') === 0) continue;
        if (strpos(str_replace('\\', '/', $path), 'resources/views/components/') === 0) continue;
        if (strpos(str_replace('\\', '/', $path), 'resources/views/layouts/') === 0) continue;
        
        $originalContent = file_get_contents($path);
        $content = $originalContent;
        
        // Clean up malformed classes
        $content = str_replace('dark:bg-slate-800/50/50/50', 'dark:bg-slate-800', $content);
        $content = str_replace('dark:bg-slate-800/50/50', 'dark:bg-slate-800', $content);
        $content = str_replace('dark:bg-slate-800/50', 'dark:bg-slate-800', $content); // Let's just use solid slate-800 for headers and rows
        
        // Clean up duplicates
        $content = preg_replace('/\bdark:bg-slate-900\s+dark:bg-slate-900\b/', 'dark:bg-slate-900', $content);
        $content = preg_replace('/\bdark:bg-slate-800\s+dark:bg-slate-800\b/', 'dark:bg-slate-800', $content);
        
        // Fix missing divide-gray-100
        $content = preg_replace('/\bdivide-gray-100(?!\s+dark:divide-)/', 'divide-gray-100 dark:divide-slate-700/50', $content);
        
        // Ensure table headers have proper dark mode (they often use bg-gray-50)
        $content = preg_replace('/\bbg-gray-50(?!\s+dark:bg-)/', 'bg-gray-50 dark:bg-slate-800', $content);

        // Fix table body background if it was missed
        $content = preg_replace('/<tbody class="bg-white(?!.*?dark:bg-slate-900)/', '<tbody class="bg-white dark:bg-slate-900', $content);
        
        // Fix table row hover
        $content = preg_replace('/hover:bg-gray-50(?!\s+dark:hover:bg-)/', 'hover:bg-gray-50 dark:hover:bg-slate-800/80', $content);

        if ($content !== $originalContent) {
            file_put_contents($path, $content);
            echo "Cleaned: $path\n";
        }
    }
}
echo "Done!\n";
