<?php
$directory = 'resources/views';

$dictionary = [
    // Main Navigations
    "/>Dashboard</" => ">Beranda<",
    "/>Academic</" => ">Akademik<",
    "/>Finance</" => ">Keuangan<",
    "/>Document Management</" => ">Manajemen Dokumen<",
    "/>System Settings</" => ">Pengaturan Sistem<",
    "/>Audit Trail</" => ">Rekam Jejak<",
    "/>Approvals</" => ">Persetujuan<",
    "/>Workflow</" => ">Alur Kerja<",
    "/>Notifications</" => ">Notifikasi<",
    
    // Auth & Generic
    "/>Logout</" => ">Keluar<",
    "/Search.../" => "Cari...",
    "/>Search</" => ">Cari<",
    "/>Save Changes</" => ">Simpan Perubahan<",
    "/>Save</" => ">Simpan<",
    "/>Cancel</" => ">Batal<",
    "/>Edit</" => ">Ubah<",
    "/>Update</" => ">Perbarui<",
    "/>Delete</" => ">Hapus<",
    "/>Create</" => ">Buat<",
    "/>Add New</" => ">Tambah Baru<",
    "/>View Details</" => ">Lihat Detail<",
    "/>View</" => ">Lihat<",
    "/>Back</" => ">Kembali<",
    "/>Actions</" => ">Aksi<",
    "/>Status</" => ">Status<",
    
    // Statuses
    "/>Active</" => ">Aktif<",
    "/>Inactive</" => ">Nonaktif<",
    "/>Pending</" => ">Menunggu<",
    "/>Approved</" => ">Disetujui<",
    "/>Rejected</" => ">Ditolak<",
    "/>Verified</" => ">Diverifikasi<",
    "/>All Status</" => ">Semua Status<",

    // Common Text
    "/>No records found</" => ">Data tidak ditemukan<",
    "/>Date</" => ">Tanggal<",
    "/>Description</" => ">Deskripsi<",
    "/>Name</" => ">Nama<",
    "/>Role</" => ">Peran<",
    "/>Module</" => ">Modul<",
];

function translateFiles($dir, $dictionary) {
    $files = scandir($dir);
    $count = 0;
    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            // Exclude HR directory completely
            if (strpos($path, DIRECTORY_SEPARATOR . 'hr' . DIRECTORY_SEPARATOR) !== false || 
                strpos($path, DIRECTORY_SEPARATOR . 'payroll' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }

            if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $content = file_get_contents($path);
                $original = $content;
                foreach ($dictionary as $pattern => $replacement) {
                    $content = preg_replace($pattern, $replacement, $content);
                }
                if ($content !== $original) {
                    file_put_contents($path, $content);
                    $count++;
                }
            }
        } else if ($value != "." && $value != "..") {
            $count += translateFiles($path, $dictionary);
        }
    }
    return $count;
}

$translatedCount = translateFiles($directory, $dictionary);
echo "Translated $translatedCount files successfully (HR excluded).\n";
?>
