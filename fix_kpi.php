<?php
$dir = 'resources/views/dashboard';
$files = scandir($dir);
$count = 0;
foreach ($files as $file) {
    $path = $dir . '/' . $file;
    if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($path);
        $original = $content;
        
        // Find :value="$kpi['something']" and replace with :value="$kpi['something'] ?? 0"
        $content = preg_replace('/:value="\$kpi\[\'(.*?)\'\]"/', ':value="$kpi[\'$1\'] ?? 0"', $content);
        
        // Also check {{ $kpi['something'] }}
        $content = preg_replace('/{{\s*\$kpi\[\'(.*?)\'\]\s*}}/', '{{ $kpi[\'$1\'] ?? 0 }}', $content);
        
        if ($content !== $original) {
            file_put_contents($path, $content);
            echo "Fixed $file\n";
            $count++;
        }
    }
}
echo "Total fixed: $count\n";
?>
