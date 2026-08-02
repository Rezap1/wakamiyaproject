<?php
$file = __DIR__ . '/resources/views/components/dashboard/sidebar.blade.php';
$content = file_get_contents($file);

$content = str_replace(
    "'bg-primary-500 text-white shadow-md shadow-primary-500/30'",
    "'bg-gradient-to-r from-blue-800 to-blue-600 text-white shadow-lg shadow-blue-900/50'",
    $content
);

$content = str_replace(
    "'text-slate-400 hover:bg-slate-800 hover:text-white'",
    "'text-slate-400 hover:bg-white/5 hover:text-white'",
    $content
);

$content = str_replace(
    "bg-slate-900",
    "bg-[#0F172A]",
    $content
);

file_put_contents($file, $content);
echo "Sidebar styling updated.\\n";
