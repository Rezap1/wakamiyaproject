<?php
$directory = 'resources/views';

$fixes = [
    // Topbar
    '<!-- Center Section: Global Cari...>' => '<!-- Center Section: Global Search -->',
    'x-data="globalCari...>' => 'x-data="globalSearch()"',
    "Alpine.data('globalCari...() => ({" => "Alpine.data('globalSearch', () => ({",
    'placeholder="Cari...ything..."' => 'placeholder="Search anything..."',
    
    // JS filters
    'const matchesCari...searchString' => 'const matchesSearch = searchString',
    'const matchCari...textContent' => 'const matchSearch = textContent',
    'if (matchCari... matchCompany' => 'if (matchSearch && matchCompany',
    'if (matchesCari... matchesStatus' => 'if (matchesSearch && matchesStatus',
    'if (matchCari... matchStatus' => 'if (matchSearch && matchStatus',
    'if (matchesCari... matchesTarget' => 'if (matchesSearch && matchesTarget',
    'if (matchesCari... matchesDay' => 'if (matchesSearch && matchesDay',
    'if (matchCari... matchType' => 'if (matchSearch && matchType',

    // Search page
    "@section('header', 'Enterprise Cari..." => "@section('header', 'Enterprise Search')",
    'placeholder="Cari...ything in WMS..."' => 'placeholder="Cari sesuatu di WMS..."',
    'Cari...utton>' => 'Cari</button>',
    'Cari...sults for "' => 'Hasil pencarian untuk "',
    'Enterprise Cari...3>' => 'Enterprise Search</h3>',
    'Clear Cari...story</button>' => 'Bersihkan Riwayat</button>',
    
    // Assessment & Attendance placeholders
    'placeholder="Cari...sessments..."' => 'placeholder="Cari ujian/tugas..."',
    'placeholder="Cari... name, ID..."' => 'placeholder="Cari nama, ID..."',
    'placeholder="Cari...tivities..."' => 'placeholder="Cari aktivitas..."'
];

function fixFiles($dir, $fixes) {
    $files = scandir($dir);
    $count = 0;
    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $content = file_get_contents($path);
                $original = $content;
                foreach ($fixes as $search => $replace) {
                    $content = str_replace($search, $replace, $content);
                }
                if ($content !== $original) {
                    file_put_contents($path, $content);
                    $count++;
                }
            }
        } else if ($value != "." && $value != "..") {
            $count += fixFiles($path, $fixes);
        }
    }
    return $count;
}

$fixedCount = fixFiles($directory, $fixes);
echo "Fixed $fixedCount files.\n";
?>
