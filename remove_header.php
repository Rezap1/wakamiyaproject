<?php
$files = [
    'resources/views/dashboard/academic.blade.php',
    'resources/views/dashboard/director.blade.php',
    'resources/views/dashboard/finance.blade.php',
    'resources/views/dashboard/hr.blade.php',
    'resources/views/dashboard/marketing.blade.php',
    'resources/views/dashboard/student.blade.php',
    'resources/views/dashboard/teacher.blade.php',
];

$pattern = '/<!-- Page Header \(Consistent with other pages\) -->\s*<x-page-header.*?\/>/s';

$count = 0;
foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $original = $content;
        
        $content = preg_replace($pattern, '', $content);
        
        $pattern2 = '/<x-page-header.*?\/>/s';
        
        // Wait, if I use .*? on pattern2 it might match the FIRST x-page-header and then stop, which is what I want.
        // Let's just use pattern.
        
        if ($content !== $original) {
            file_put_contents($file, ltrim($content));
            echo "Cleaned $file\n";
            $count++;
        }
    }
}

echo "Removed x-page-header from $count files.\n";
?>
