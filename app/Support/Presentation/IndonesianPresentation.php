<?php

namespace App\Support\Presentation;

use Carbon\Carbon;
use DateTimeInterface;

final class IndonesianPresentation
{
    private const DAYS = [
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
        'friday' => 'Jumat',
        'saturday' => 'Sabtu',
        'sunday' => 'Minggu',
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];

    private const ROLES = [
        'student' => 'Siswa',
        'teacher' => 'Guru',
        'employee' => 'Karyawan',
        'academic' => 'Akademik',
        'finance' => 'Keuangan',
        'hr' => 'SDM',
        'marketing' => 'Pemasaran',
        'director' => 'Direktur',
        'administrator' => 'Administrator',
        'master' => 'Administrator Utama',
        'guest' => 'Tamu',
        'user' => 'Pengguna',
    ];

    private const LABELS = [
        'active' => 'Aktif',
        'inactive' => 'Tidak Aktif',
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'canceled' => 'Dibatalkan',
        'draft' => 'Draf',
        'published' => 'Dipublikasikan',
        'expired' => 'Kedaluwarsa',
        'processing' => 'Diproses',
        'completed' => 'Selesai',
        'failed' => 'Gagal',
        'success' => 'Berhasil',
        'verified' => 'Terverifikasi',
        'waiting verification' => 'Menunggu Verifikasi',
        'need revision' => 'Perlu Revisi',
        'reversed' => 'Dikoreksi',
        'paid' => 'Lunas',
        'partial paid' => 'Dibayar Sebagian',
        'partially paid' => 'Dibayar Sebagian',
        'waiting payment' => 'Menunggu Pembayaran',
        'unpaid' => 'Belum Lunas',
        'overdue' => 'Terlambat',
        'waiting approval' => 'Menunggu Persetujuan',
        'closed' => 'Ditutup',
        'calculated' => 'Dihitung',
        'generated' => 'Dibuat',
        'submitted' => 'Diajukan',
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'sick' => 'Sakit',
        'permitted' => 'Izin',
        'permission' => 'Izin',
        'leave' => 'Cuti',
        'absent' => 'Alpa',
        'scheduled' => 'Terjadwal',
        'ongoing' => 'Berlangsung',
        'open' => 'Dibuka',
        'archived' => 'Diarsipkan',
        'progress' => 'Berlangsung',
        'high' => 'Tinggi',
        'medium' => 'Sedang',
        'low' => 'Rendah',
        'urgent' => 'Mendesak',
        'all' => 'Semua',
        'all users' => 'Semua Pengguna',
        'true' => 'Aktif',
        'false' => 'Tidak Aktif',
        'yes' => 'Ya',
        'no' => 'Tidak',
        'male' => 'Laki-laki',
        'female' => 'Perempuan',
        'invoice' => 'Tagihan',
        'payment' => 'Pembayaran',
        'transaction' => 'Transaksi',
        'payroll' => 'Penggajian',
        'assignment' => 'Tugas',
        'assessment' => 'Penilaian',
        'attendance' => 'Kehadiran',
        'schedule' => 'Jadwal',
        'subject' => 'Mata Pelajaran',
        'class' => 'Kelas',
        'document' => 'Dokumen',
        'signed' => 'Ditandatangani',
        'unsigned' => 'Belum Ditandatangani',
        'permanent' => 'Tetap',
        'contract' => 'Kontrak',
        'probation' => 'Masa Percobaan',
        'resigned' => 'Mengundurkan Diri',
        'terminated' => 'Diberhentikan',
        'active employee' => 'Karyawan Aktif',
        'active teaching' => 'Aktif Mengajar',
        'inactive teaching' => 'Tidak Aktif Mengajar',
        'enrolled' => 'Terdaftar',
        'graduated' => 'Lulus',
        'drop out' => 'Putus Studi',
        'not graduated' => 'Belum Lulus',
    ];

    public static function day(mixed $value, string $fallback = '-'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::DAYS[self::key($raw)] ?? $raw;
    }

    public static function paymentMethod(mixed $value): string
    {
        return match (self::key((string) $value)) {
            'cash', 'tunai' => 'Tunai',
            'transfer', 'bank transfer' => 'Transfer Bank',
            'qris' => 'QRIS',
            default => 'Metode pembayaran belum teridentifikasi',
        };
    }

    public static function paymentType(mixed $value): string
    {
        return match (self::key((string) $value)) {
            'student self service' => 'Pembayaran Mandiri Siswa',
            'student' => 'Pembayaran Siswa',
            'company' => 'Pembayaran Perusahaan',
            default => 'Pembayaran',
        };
    }

    public static function paymentStatus(mixed $value): string
    {
        return match (self::key((string) $value)) {
            'pending', 'waiting verification' => 'Menunggu Verifikasi',
            'verified' => 'Terverifikasi',
            'need revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'reversed' => 'Dikoreksi',
            default => 'Status pembayaran perlu diperiksa',
        };
    }

    public static function role(mixed $value, string $fallback = 'Pengguna'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::ROLES[self::key($raw)] ?? $raw;
    }

    public static function status(mixed $value, string $fallback = '-'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::knownLabel($raw) ?? $raw;
    }

    public static function boolean(mixed $value, string $fallback = '-'): string
    {
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return match (self::key($raw)) {
            'true', 'yes', 'y', '1', 'active' => 'Ya',
            'false', 'no', 'n', '0', 'inactive' => 'Tidak',
            default => $raw,
        };
    }

    public static function enum(mixed $value, string $fallback = '-'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::knownLabel($raw) ?? $raw;
    }

    public static function knownLabel(mixed $value): ?string
    {
        $key = self::key((string) $value);

        return self::DAYS[$key]
            ?? self::ROLES[$key]
            ?? self::LABELS[$key]
            ?? null;
    }

    public static function date(mixed $value, string $format = 'j F Y', string $fallback = '-'): string
    {
        if ($value === null || (!$value instanceof DateTimeInterface && trim((string) $value) === '')) {
            return $fallback;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse(
                    str_replace('/', '-', trim((string) $value)),
                    'Asia/Jakarta'
                );

            $format = str_replace(['d F', 'd M'], ['j F', 'j M'], $format);

            try {
                return $date->locale('id')->translatedFormat($format);
            } catch (\Throwable) {
                // Keep presentation deterministic even when PHP intl/locale
                // data is unavailable on the host.
                $english = $date->format($format);
                return strtr($english, [
                    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
                    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April',
                    'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus',
                    'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember',
                ]);
            }
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private static function key(string $value): string
    {
        $value = preg_replace('/[_-]+/', ' ', trim($value)) ?? trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return strtolower($value);
    }
}
