<?php
$file = 'd:\orderan\wakamiya\resources\views\components\dashboard\action-center.blade.php';
$content = file_get_contents($file);

$phpBlock = <<<'EOD'
    @if(count($kpi) > 0)
    @php
        $showGaji = !in_array('Gaji Bulan Ini', array_column($kpi, 'title')) && !request()->routeIs('dashboard.student');
        $totalCards = count($kpi) + ($showGaji ? 1 : 0);
        $gridClass = match($totalCards) {
            1 => 'lg:grid-cols-1',
            2 => 'lg:grid-cols-2',
            3 => 'lg:grid-cols-3',
            4 => 'lg:grid-cols-4',
            6 => 'lg:grid-cols-6',
            default => 'lg:grid-cols-5'
        };
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 {{ $gridClass }} gap-4">
EOD;

$content = str_replace(
    "@if(count(\$kpi) > 0)\n    <div class=\"grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4\">",
    $phpBlock,
    $content
);

$content = str_replace(
    "@if(!in_array('Gaji Bulan Ini', array_column(\$kpi, 'title')) && !request()->routeIs('dashboard.student'))",
    "@if(\$showGaji)",
    $content
);

file_put_contents($file, $content);
echo "Dashboard layout fixed.";
