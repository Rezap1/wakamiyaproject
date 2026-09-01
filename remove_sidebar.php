<?php
$file = 'resources/views/components/dashboard/sidebar.blade.php';
$content = file_get_contents($file);
$content = preg_replace('/.*Tugas Harian.*\n/', '', $content);
$content = preg_replace('/.*Pengumpulan Tugas.*\n/', '', $content);
file_put_contents($file, $content);
echo 'Removed Tugas Harian and Pengumpulan Tugas';
