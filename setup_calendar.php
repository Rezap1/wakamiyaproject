<?php
$dashboards = glob('resources/views/dashboard/*.blade.php');
foreach ($dashboards as $d) {
    $content = file_get_contents($d);
    $content = str_replace('<a href="#" class="text-[11px] text-blue-600 font-bold hover:underline">Lihat Kalender</a>', '<a href="{{ Route::has(\'schedules.index\') ? route(\'schedules.index\') : \'#\' }}" class="text-[11px] text-blue-600 font-bold hover:underline">Lihat Kalender</a>', $content);
    file_put_contents($d, $content);
}
echo "Fixed calendar view all link.";
