<?php
$viewsDir = 'd:/orderan/wakamiya/resources/views';

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
$indexFiles = [];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getFilename() === 'index.blade.php') {
        $indexFiles[] = $file->getPathname();
    }
}

$report = "Data Mapping Discrepancies in index.blade.php files:\n\n";

foreach ($indexFiles as $filePath) {
    $content = file_get_contents($filePath);
    preg_match_all("/\\\$item\['([^']+)'\]/", $content, $matches);
    if (!empty($matches[1])) {
        $keys = array_unique($matches[1]);
        $relativePath = str_replace($viewsDir . '/', '', str_replace('\\', '/', $filePath));
        $report .= "- $relativePath expects keys: " . implode(', ', $keys) . "\n";
    }
}

file_put_contents('d:/orderan/wakamiya/scratch_mapping_report.txt', $report);
echo "Report generated.\n";
