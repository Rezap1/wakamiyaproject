<?php
$file = 'app/Http/Controllers/Finance/StudentBillingController.php';
$content = file_get_contents($file);

// 1. Update index method
$content = str_replace(
    'return ($inv[\'Student_ID\'] ?? \'\') == $studentId;',
    'return ($inv[\'Student_ID\'] ?? \'\') == $studentId && strcasecmp(trim($inv[\'Status\'] ?? \'\'), \'Draft\') !== 0;',
    $content
);

// 2. Update show, downloadInvoicePdf, pay methods
$content = str_replace(
    'if (!$invoice || ($invoice[\'Student_ID\'] ?? \'\') !== $studentId) {',
    'if (!$invoice || ($invoice[\'Student_ID\'] ?? \'\') !== $studentId || strcasecmp(trim($invoice[\'Status\'] ?? \'\'), \'Draft\') === 0) {',
    $content
);
$content = str_replace(
    'abort(403, "Akses Ditolak: Tagihan #{$id} bukan milik akun Anda.");',
    'abort(403, "Akses Ditolak: Tagihan #{$id} bukan milik akun Anda atau belum diterbitkan.");',
    $content
);

file_put_contents($file, $content);
echo "Draft filter applied.";
