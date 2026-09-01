<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
foreach ($files as $file) {
    if (str_ends_with($file->getFilename(), '.blade.php')) {
        $content = file_get_contents($file->getPathname());
        if (stripos($content, '<form') !== false && stripos($content, 'pdf') !== false) {
            // Check if pdf link is inside form
            preg_match_all('/<form[^>]*>(.*?)<\/form>/is', $content, $matches);
            foreach ($matches[1] as $innerForm) {
                if (stripos($innerForm, 'pdf') !== false) {
                    echo "Found PDF inside form in: " . $file->getPathname() . "\n";
                }
            }
        }
    }
}
