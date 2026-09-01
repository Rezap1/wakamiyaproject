<?php
$file = 'resources/views/attendance/qr/index.blade.php';
$content = file_get_contents($file);

$header = <<<'EOD'
<div class="max-w-7xl mx-auto space-y-6">
    @php
        $context = app(\App\Services\Dashboard\DashboardContextService::class)->getContext();
        $role = strtoupper($context['role'] ?? '');
        
        if ($role === 'ACADEMIC') {
            $qrCodes = collect($qrCodes)->filter(fn($qr) => ($qr['QR_TYPE'] ?? '') === 'STUDENT')->values();
        } elseif ($role === 'HR') {
            $qrCodes = collect($qrCodes)->filter(fn($qr) => ($qr['QR_TYPE'] ?? '') === 'EMPLOYEE')->values();
        }
    @endphp
EOD;

$content = str_replace('<div class="max-w-7xl mx-auto space-y-6">', $header, $content);

$content = str_replace('<!-- Form Buat QR Siswa -->', "@if(\$role !== 'HR')\n        <!-- Form Buat QR Siswa -->", $content);

$content = str_replace('<!-- Form Buat QR Pegawai -->', "@endif\n\n        @if(\$role !== 'ACADEMIC')\n        <!-- Form Buat QR Pegawai -->", $content);

$content = preg_replace('/(<\/button>\s*<\/form>\s*<\/div>\s*<\/div>)/', "</button>\n            </form>\n        </div>\n        @endif\n    </div>", $content, 1);

file_put_contents($file, $content);
echo 'Updated view!';
