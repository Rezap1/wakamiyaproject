# Audit Contextual Sidebar Notification Badge

Tanggal audit: 26 November 2026 (Asia/Jakarta)

Baseline: branch `quiz-multimedia-release`, commit `9a466d16368eda13b8eb9ccfd5cf601d27f40c93`

## Ringkasan keputusan

**GO untuk release candidate/staging.** Implementasi bersifat read-only, mengikuti scope otorisasi menu terkait, tidak menambah polling, dependency, migrasi, atau perubahan kontrak route. Full regression suite lulus 1.462 tes (7.117 assertion), dengan 123 tes conditional yang dilewati oleh konfigurasi suite. Build frontend juga lulus.

Keputusan ini bukan izin deploy production. Sebelum production, tetap lakukan smoke test pada database MySQL staging yang strukturnya setara production, terutama untuk query presensi guru dan kuis siswa.

## Prinsip implementasi

- Badge hanya muncul bila nilainya lebih dari nol dan ditampilkan sebagai `99+` bila lebih dari 99.
- Satu payload terpusat disuntikkan ke shared sidebar oleh view composer.
- Semua hitungan domain memakai query agregat `COUNT`, bukan memuat koleksi penuh.
- Scope pengguna/role mengikuti identitas dan relasi yang sama dengan halaman tujuan; pemetaan identitas ambigu atau schema opsional yang tidak tersedia akan **fail closed** menjadi nol.
- Badge notifikasi menggunakan snapshot unread yang sama dengan notification bell. Membuka sidebar atau halaman tidak menandai item sebagai sudah dibaca.
- Tidak ada polling atau WebSocket baru. Nilai diperbarui pada request/navigasi berikutnya setelah aksi sukses.
- Badge kuis siswa hanya membaca domain kuis reguler (`QUIZ`, `QUIZ_RESULT`, dan bila tersedia `QUIZ_AUDIENCE_CLASS`). Data `PRACTICE_QUIZ` tidak ikut dihitung.

## Matriks menu seluruh role

`[badge]` menandai menu yang sekarang dapat menampilkan badge kontekstual.

| Role | Menu yang tersedia | Badge aktif |
|---|---|---|
| MASTER | Dasbor Utama, SDM, Akademik, Keuangan, Pemasaran; Agenda Management, Kotak Persetujuan, Pegawai, Monitoring Kehadiran, Alumni; Siswa, Pengajar/Guru, Program Studi, Batch, Kelas, Pengumuman, Mata Pelajaran, Jadwal Kelas, Presensi Akademik, Absensi Manual, Laporan Absensi, Tinjau Pengajuan; Pembayaran Pendidikan, Master Akun, Transaksi, Tagihan, Pembayaran, Penggajian & Gaji, Laporan Keuangan; Perusahaan; Pengaturan Sistem/SDM/Akademik/Keuangan, Pengguna, Jabatan, Departemen, Modul, Jejak Audit; Pembuat Dokumen, QR Presensi, Pindai QR Pegawai | Pembayaran `[badge]` |
| ADMINISTRATOR | Kotak Pengumuman, Agenda, Dasbor, Pembayaran Pendidikan, Kotak Persetujuan, Alumni, Jejak Audit, Monitoring Pegawai, Laporan Absensi, Pembuat Dokumen, QR Presensi, Pindai QR Pegawai, Pengaturan Sistem, Library Stiker, Pengguna, Modul, Pengumuman | Kotak Pengumuman `[badge]`, Kotak Persetujuan `[badge]` |
| HR | Kotak Pengumuman, Agenda, Dasbor, Pegawai, Monitoring Kehadiran, QR Presensi, Pindai QR Pegawai, Penggajian & Gaji, Departemen, Jabatan, Pengaturan SDM | Kotak Pengumuman `[badge]` |
| ACADEMIC | Kotak Pengumuman, Agenda, Dasbor, Siswa, Pengajar, Program, Batch, Kelas, Pengumuman, Materi, Jadwal Kelas, Presensi, QR Presensi, Tinjau Pengajuan, Alumni, Pengaturan Akademik | Kotak Pengumuman `[badge]` |
| MARKETING | Kotak Pengumuman, Agenda, Dasbor, Pindai QR Pegawai, Perusahaan, Notifikasi | Kotak Pengumuman `[badge]`, Notifikasi `[badge]` |
| FINANCE | Kotak Pengumuman, Agenda, Dasbor, Pindai QR Pegawai, Master Akun, Transaksi, Tagihan, Pembuat Dokumen, Pembayaran, Laporan Keuangan, Pengaturan Keuangan | Kotak Pengumuman `[badge]`, Pembayaran `[badge]` |
| DIRECTOR | Kotak Pengumuman, Agenda, Dasbor, Pengumuman, Kotak Persetujuan, Pusat Laporan, Kehadiran Pegawai, Transaksi, Laporan Keuangan | Kotak Pengumuman `[badge]` |
| TEACHER | Kotak Pengumuman, Agenda, Dasbor, Pindai QR Pegawai, Jadwal Mengajar, Kelas Saya, Daftar Siswa, Pengumuman, Kehadiran Siswa, Laporan Absensi, Pengajuan Izin/Sakit, Penilaian, Tugas Harian, Kuis, Kalender Akademik, Laporan Pengajaran | Kotak Pengumuman `[badge]`, Pengajuan Izin/Sakit `[badge]` |
| STUDENT | Kotak Pengumuman, Dasbor, Pengumuman, Presensi QR, Riwayat Absensi, Jadwal, Kalender, Materi Saya, Tugas, Kuis, Nilai, Materi, Tagihan Saya, Pengajuan Izin/Sakit, Notifikasi | Kotak Pengumuman `[badge]`, Kuis `[badge]`, Notifikasi `[badge]` |
| EMPLOYEE | Kotak Pengumuman, Agenda, Dasbor, Pindai QR Pegawai, Slip Gaji Saya, Notifikasi, Profil | Kotak Pengumuman `[badge]`, Notifikasi `[badge]` |

## Badge yang diaktifkan

| Badge | Role | Sumber dan scope | Kapan muncul | Kapan berkurang/hilang |
|---|---|---|---|---|
| Aktivitas pengumuman | Semua role non-MASTER | Notifikasi unread yang sudah lolos `NotificationService::isForUser()` dan link-nya menuju `/announcements...` atau `/student/portal/announcements...` | Ada komentar/mention/aktivitas pengumuman unread untuk pengguna tersebut | Mekanisme existing mark-as-read/mark-all-read |
| Total notifikasi | MARKETING, STUDENT, EMPLOYEE | Snapshot unread milik pengguna yang sama dengan notification bell | Ada notifikasi unread | Mekanisme existing mark-as-read/mark-all-read |
| Persetujuan | ADMINISTRATOR | `MASTER_APPROVAL.Status = Waiting Approval`; administrator memang dapat melihat seluruh item waiting | Ada item yang dapat ditindak administrator | Status tidak lagi `Waiting Approval` setelah aksi sukses |
| Verifikasi pembayaran | FINANCE, MASTER | `FINANCE_PAYMENT.Status = Waiting Verification` | Ada pembayaran menunggu verifikasi | Status pembayaran berubah setelah aksi sukses |
| Pengajuan izin/sakit | TEACHER | Request pending milik siswa aktif pada kelas wali/jadwal guru aktif; request terjadwal harus cocok dengan guru dan kelas | Ada request yang dapat ditindak guru tersebut | Status request tidak lagi pending setelah approve/reject |
| Kuis reguler | STUDENT | Kuis `PUBLISHED`, periode aktif, audience kelas siswa, dan belum memiliki `QUIZ_RESULT` untuk siswa | Ada kuis reguler aktif yang belum selesai | Hasil kuis tersimpan atau periode kuis berakhir |

Catatan: badge Kotak Pengumuman mengukur **aktivitas pengumuman unread**, bukan jumlah seluruh pengumuman terbit. Aplikasi belum memiliki read receipt per pengumuman yang cukup untuk menghitung angka tersebut secara akurat.

## Kandidat yang sengaja tidak diberi badge

| Menu/kandidat | Alasan |
|---|---|
| Tugas/Tugas Harian | `MASTER_ASSIGNMENT` tidak memiliki model submission/completion/read yang dapat membedakan tugas belum selesai secara akurat. Menghitung semua tugas published akan menghasilkan false positive dan tidak memiliki lifecycle decrement yang benar. |
| Nilai/Penilaian | Belum ada lifecycle read/unread atau review-pending yang konsisten. |
| Seluruh pengumuman published | Tidak ada receipt per pengguna; badge menggunakan aktivitas notifikasi yang sudah memiliki lifecycle unread. |
| Tinjau Pengajuan untuk ACADEMIC | Halaman dapat dilihat, tetapi service approve/reject saat ini membatasi aksi ke TEACHER. Angka tersebut bukan pending action yang benar-benar dapat ditindak ACADEMIC. |
| Kotak Persetujuan untuk DIRECTOR/MASTER | Schema canonical memiliki `Current_Role`, sedangkan controller/service otorisasi mengacu pada `Current_Approver`. Mengasumsikan keduanya ekuivalen berisiko membocorkan jumlah record yang tidak tampil di halaman. ADMINISTRATOR aman karena halaman memang menampilkan semua status waiting. |
| Review kuis guru | Kuis saat ini dinilai otomatis dan tidak menyediakan state manual-review-pending. |
| Pengajuan izin/sakit milik STUDENT | Bukan pending action bagi siswa; hasil keputusan sudah dikirim melalui notifikasi. |
| Payroll, laporan, riwayat, pengaturan, scanner/QR, profil, master data | Tidak mempunyai pending action terotorisasi atau lifecycle unread yang tepat untuk badge. |

## Otorisasi dan keamanan

- Tidak ada endpoint baru dan tidak ada data detail yang dikirim ke sidebar; hanya integer agregat.
- Lookup guru dan siswa menuntut tepat satu pemetaan identitas aktif. Nol atau lebih dari satu pemetaan menghasilkan badge nol.
- Scope guru memvalidasi relasi siswa, kelas aktif, wali kelas/jadwal aktif, jenis request, dan schedule yang cocok.
- Scope kuis memvalidasi siswa aktif, kelas, audience, periode aktif, status published, serta tidak adanya result milik siswa.
- Query approval role selain ADMINISTRATOR akan nol bila `Current_Approver` tidak tersedia; service saat ini hanya meminta badge approval untuk ADMINISTRATOR.
- Semua query badge menangkap kegagalan schema/runtime dan fail closed agar shared layout tidak menjatuhkan halaman lain.
- Rendering Blade meng-escape label dan nilai secara default; badge tidak menerima HTML mentah.

## Dampak query dan performa

| Role/kondisi | Dampak per request sidebar |
|---|---|
| Semua non-MASTER | Snapshot notifikasi menggunakan memo request milik `NotificationService`, sehingga tidak menduplikasi read bila bell/topbar juga memakainya |
| ADMINISTRATOR | 1 query aggregate count approval |
| FINANCE / MASTER | 1 query aggregate count pembayaran |
| TEACHER | 1 lookup identitas guru + 1 aggregate count request |
| STUDENT | 1 lookup identitas siswa + 1 aggregate count kuis + maksimal 1 schema-presence check untuk tabel audience |
| Role lain | Tidak ada query pending-action tambahan; hanya memakai snapshot notifikasi existing |

Payload sidebar juga dimemoisasi berdasarkan user dan role pada request yang sama. Tidak ada `SELECT *`, hydration seluruh tabel, background polling, atau cache lintas request yang dapat menjadi stale.

## Verifikasi

### Automated tests

- Targeted badge tests: **10 passed, 40 assertions**.
- Targeted regression (notification, redirect safety, approval scope, attendance scope, permission workflow, quiz domain/authorization, mobile navigation, dan badge): **126 passed, 563 assertions**.
- Full suite: **1.462 passed, 7.117 assertions, 123 skipped, 0 failed** dalam 277,46 detik.
- `npm run build`: **passed**, 56 modules transformed.
- PHP lint, Pint, dan `git diff --check`: **passed** (Git hanya memberi peringatan normalisasi CRLF untuk satu Blade file).

Tes baru mencakup:

- batas scope dan query count setiap domain;
- isolasi kuis reguler dari `PRACTICE_QUIZ`;
- fail-closed identity/schema;
- request memoization dan penggunaan snapshot notifikasi;
- aksesibilitas, `99+`, hidden-zero, layout overflow, role/menu mapping, dan mobile drawer z-index.

### Bukti visual

- [Desktop student sidebar](./contextual-sidebar-badges-desktop.png): badge Kotak Pengumuman, Kuis `99+`, dan Notifikasi.
- [Mobile student drawer](./contextual-sidebar-badges-mobile.png): badge tetap terbaca pada drawer sempit dan drawer berada di atas floating QR action.

## File yang berubah

### Implementasi

- `app/Providers/AppServiceProvider.php`
- `app/Repositories/MySql/SidebarBadgeRepository.php`
- `app/Services/Core/NotificationService.php`
- `app/Services/Core/SidebarBadgeService.php`
- `resources/views/components/dashboard/sidebar.blade.php`
- `resources/views/components/sidebar/badge.blade.php`
- `resources/views/components/sidebar/nav-link.blade.php`

### Tes dan dokumentasi

- `tests/Unit/SidebarBadgeRepositoryTest.php`
- `tests/Unit/SidebarBadgeServiceTest.php`
- `tests/Unit/SidebarBadgeViewTest.php`
- `docs/contextual-sidebar-badge-audit.md`
- `docs/contextual-sidebar-badges-desktop.png`
- `docs/contextual-sidebar-badges-mobile.png`

## Exact allowlist kandidat commit

Jika perubahan akan dikomit setelah mendapat persetujuan, stage **hanya** 13 path berikut:

```text
app/Providers/AppServiceProvider.php
app/Repositories/MySql/SidebarBadgeRepository.php
app/Services/Core/NotificationService.php
app/Services/Core/SidebarBadgeService.php
resources/views/components/dashboard/sidebar.blade.php
resources/views/components/sidebar/badge.blade.php
resources/views/components/sidebar/nav-link.blade.php
tests/Unit/SidebarBadgeRepositoryTest.php
tests/Unit/SidebarBadgeServiceTest.php
tests/Unit/SidebarBadgeViewTest.php
docs/contextual-sidebar-badge-audit.md
docs/contextual-sidebar-badges-desktop.png
docs/contextual-sidebar-badges-mobile.png
```

File untracked lain yang sudah ada di working tree bukan bagian dari fitur dan harus tetap dikecualikan. Tidak ada commit, push, deploy, migrasi, atau perubahan data yang dilakukan dalam pekerjaan ini.

## Risiko dan batasan tersisa

- Integration test MySQL yang mensyaratkan koneksi khusus tidak dijalankan oleh konfigurasi lokal; query sudah diuji pada SQLite dan dibangun melalui query grammar Laravel, tetapi smoke test MySQL staging tetap disarankan.
- Badge bersifat request-driven; tanpa polling, tab yang dibiarkan terbuka tidak akan berubah sampai navigasi/refresh berikutnya.
- Schema approval `Current_Role` versus `Current_Approver` perlu direkonsiliasi terpisah sebelum badge scoped untuk DIRECTOR/MASTER dapat dinyalakan dengan aman.
- Badge tugas/nilai membutuhkan model lifecycle baru (submission/read/review), bukan sekadar perubahan tampilan.
