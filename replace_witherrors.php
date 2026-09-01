<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/Http/Controllers/Finance'));
foreach ($files as $file) {
    if (str_ends_with($file->getFilename(), '.php')) {
        $content = file_get_contents($file->getPathname());
        $modified = str_replace(
            "withErrors(['error' => \$e->getMessage()])",
            "with('error', \$e->getMessage())",
            $content
        );
        if ($content !== $modified) {
            file_put_contents($file->getPathname(), $modified);
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}
