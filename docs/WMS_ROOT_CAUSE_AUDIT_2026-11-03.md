# WMS — audit akar masalah dan perbaikan lokal

Audit dimulai 2 November dan ditutup 3 November 2026 (WIB). Workspace: `D:\orderan\wakamiya`.

## 1. Executive Summary

**Status keseluruhan: BLOCKED.** Sejumlah cacat telah direproduksi dan diperbaiki, dengan bukti unit/feature, transaksi MySQL terisolasi, proses paralel, dan HTTP/Playwright lokal. Bukti ini tidak menetapkan akar masalah insiden produksi: stack trace dan correlation ID produksi belum tersedia.

Perbaikan meliputi penolakan pengajuan ganda, penguncian saat pengajuan paralel, retry keputusan yang sebelumnya mengubah histori, kewenangan guru wali kelas tanpa jadwal, notifikasi QR sebelum commit, validasi absensi manual yang terhalang middleware, serta konsistensi waktu dan label TERLAMBAT. Error approval dan QR permanen sekarang dapat dilacak melalui correlation ID pada jalur kegagalan sistem yang diuji.

Persetujuan pengguna yang diterapkan: sesi siswa berlangsung satu jam **sejak jam mulai absensi**, menit ke-30 sampai tepat menit ke-60 berstatus TERLAMBAT; approval tetap TEACHER sesuai kelas. MASTER/ACADEMIC tidak memperoleh kewenangan approval baru. Token dinamis tetap berganti setiap 25 detik; masa sesi dan usia token adalah dua pemeriksaan berbeda.

Ada insiden tooling yang harus dicatat: formatter sempat menyentuh file pengguna. Sebanyak 692 file histori dipulihkan byte-for-byte dari salinan asli. Tiga skrip `h865_*` belum dapat dipastikan kembali ke byte semula karena baseline tidak tersedia. Ini merupakan blocker tambahan, bukan hasil PASS.

## 2. Root Cause dan bukti

Nomor baris merujuk working tree saat laporan dibuat. Semua log pada tabel berada di `tmp/root-cause-audit/`.

| Temuan | Lokasi/fungsi dan pemicu | Hasil sebelum perbaikan | Hasil sesudah |
|---|---|---|---|
| Riwayat REJECTED menutupi pengajuan aktif | `app/Services/Attendance/AttendanceRequestService.php:126`, `createRequest`; pencarian mengambil kecocokan pertama sebelum memeriksa status aktif | `reproduction.log`: pengajuan ketiga diterima walau ada PENDING yang lebih baru | Filter status aktif menjadi bagian predicate; regresi lulus |
| Dua worker dapat membuat dua PENDING | `AttendanceRequestService.php:159`, callback create; cache lokal tiap proses bukan pengunci lintas worker | `concurrency-before.log`: dua keberhasilan dan dua request | Transaksi dan lock baris siswa sebelum snapshot duplikasi; satu berhasil, satu ditolak, satu request |
| Retry penolakan menimpa koreksi resmi | `AttendanceRequestService.php:446`, `rejectRequest`; REJECTED dipanggil ulang setelah koreksi PRESENT | `reproduction.log`: PRESENT berubah ALPA | Retry tidak menyinkronkan ulang; status koreksi dipertahankan |
| Retry approval membuat ulang histori hilang | `AttendanceRequestService.php:377`, `approveRequest`; request APPROVED tanpa baris attendance | `reproduction.log`: satu attendance baru dibuat | Retry tidak dijadikan mekanisme perbaikan histori; tidak ada insert |
| Guru wali kelas tanpa jadwal ditolak | `AttendanceRequestService.php:736`, `teacherScopeForUser`; fallback hanya memakai jadwal | `homeroom-before.log`: AuthorizationException pada guru pemilik kelas | Resolver penugasan kelas digabung dengan jadwal dan dibatasi kelas aktif; regresi dan klik ACC lokal lulus |
| Event QR dikirim sebelum commit berhasil | `app/Services/Academic/StudentQRAttendanceService.php:401`, jalur transaksi scan | `qr-reproduction.log`: event terhitung 1 saat commit dibuat gagal | Dispatch dijalankan setelah transaksi kembali sukses; kegagalan event tidak membatalkan hasil simpan |
| MASTER manual selalu kehilangan Status valid | `app/Http/Middleware/ProtectMassAssignment.php:66`, `handle`; middleware membuang Status sebelum FormRequest mewajibkan PRESENT | `manual-before.log` dan `manual-error.html`: “Status Publikasi wajib diisi” | Hanya kolom Status untuk route manual diizinkan; field audit tetap dilindungi. 8 tes manual dan HTTP simpan lulus |
| Waktu dan label siswa belum mengikuti kebijakan yang disetujui | `app/Services/Attendance/AttendanceWindowService.php:48,124`; controller scan dan `qr_scanner.blade.php:499` | Kode lama memakai jam tutup tersimpan dan mengklasifikasikan scan yang diterima sebagai PRESENT/HADIR; `qr-policy.log` merekam ketidakcocokan terhadap aturan baru | Akhir efektif = mulai + 60 menit; LATE mulai menit 30; cutoff ALPA dan UI mengikuti akhir efektif. Ini penyesuaian kebijakan yang disetujui, bukan kesimpulan penyebab insiden produksi |
| Error approval tidak ditangani lengkap | `app/Http/Controllers/Academic/AttendanceRequestController.php:142,165`, `approve/reject` | `approval-response-before.log`: PHP Error lolos dan authorization tertangkap sebagai error umum | Throwable dilaporkan melalui helper correlation; AuthorizationException tetap 403, validasi tetap 422 |
| QR permanen menyembunyikan ID laporan | `app/Http/Controllers/Core/PermanentQrController.php:363`, `scanVerify`; fallback kustom tidak menampilkan ID dan catch hanya Exception | `permanent-errors-before.log`: dua tes gagal karena report_id kosong | JSON dan header X-Request-ID berisi ID log; kegagalan sistem siswa 500, penolakan domain mempertahankan statusnya |
| Skema DB tes tertinggal | Migration existing `database/migrations/wms_mysql/2026_10_19_000001_add_attendance_request_routing_columns.php`; Class_ID/Attendance_Type hilang di MASTER_ATTENDANCE_REQUEST pada DB tes | `mysql-approval.log`: guard skema gagal sebelum menulis | Migration additive yang sudah ada hanya dijalankan pada wms_mysql_test; jumlah request tetap 0; tes transaksi MySQL lulus. Keadaan skema produksi belum diketahui |
| Ekspektasi tes tanggal salah bahasa | `tests/Feature/TeacherScoreScheduleIndependentTest.php`, tes tampilan nilai | `baseline.log`: mengharapkan “31 Oct”, UI berbahasa Indonesia menampilkan “31 Okt” | Ekspektasi diperbaiki; tidak mengubah alur nilai atau data |

### Peta alur yang diperiksa

| Alur | UI, route dan validasi | Service, transaksi dan sumber resmi | Respons |
|---|---|---|---|
| QR siswa | `academic/attendances/qr_scanner.blade.php` → POST `/attendance/student/scan` → auth/role STUDENT/CSRF → `StudentQRAttendanceController::scan` | `StudentQRAttendanceService` → window WIB/token/geofence/identitas → transaction/unique guard → AttendanceRepository | JSON status resmi/reason_code; JS menampilkan HADIR atau TERLAMBAT |
| QR permanen | POST `/attendance/scan/{type}/{identifier}/verify` → auth/CSRF/throttle → `PermanentQrController::scanVerify` | Memanggil service scan siswa yang sama | Label resmi; error sistem memiliki report_id dan X-Request-ID |
| Manual MASTER | `academic/attendances/manual.blade.php` → POST `/academic/attendances/manual` → role MASTER/mass-assignment guard → `StoreMasterManualAttendanceRequest` → `AttendanceController::manualStore` | `MasterManualAttendanceService` memeriksa roster kembali → `AttendanceService::recordManualBatch` → transaksi/unique guard | Redirect success atau validation/error; sumber MASTER_MANUAL dan aktor server dicatat |
| Pengajuan siswa | `student/attendance/request-create.blade.php` → POST `/student/attendance/requests` → STUDENT/CSRF → Student AttendanceRequestController::store/validasi bukti dan tanggal | `AttendanceRequestService::createRequest` → lock siswa, cek identitas/kelas dan duplicate → request PENDING | Redirect sukses; notifikasi setelah transaksi |
| Keputusan guru | `academic/attendance/request-show.blade.php` → POST `/teacher/workspace/attendance-requests/{id}/approve` atau `/reject` → TEACHER/CSRF → Academic AttendanceRequestController/validasi | `lockedReviewableRequest` membaca ulang request di dalam transaksi dan lockById; cek scope; update request + syncOfficialAttendance dalam transaksi yang sama | APPROVED→IZIN/SAKIT; REJECTED→ALPA; side effect setelah transaksi; retry tidak mengoreksi histori |
| ALPA | `routes/console.php:21`, jadwal setiap 10 menit WIB → `attendance:mark-absent` | `AutomaticStudentAbsenceService` → cutoff efektif → tidak menimpa status resmi → AttendanceRepository | Ringkasan run_id, created/skipped, hasil command |

Nama namespace repository `GoogleSheets` adalah kompatibilitas lama. `BaseSheetRepository` mewarisi `BaseMySqlRepository`; sumber aktif tetap tabel `MASTER_ATTENDANCE` dan `MASTER_ATTENDANCE_REQUEST`, bukan sistem attendance tambahan. Tampilan Indonesia mengkanonisasi variasi status legacy (misalnya IZIN/PERMITTED dan ALPA/ABSENT).

## 3. Perbaikan

- Pengajuan baru memakai transaksi dan lock siswa lintas worker; keputusan approval tetap memakai row lock request dan pembacaan status terbaru di dalam transaksi.
- Pengulangan keputusan tidak lagi memperbaiki atau menimpa histori secara diam-diam. Keputusan berlawanan tetap ditolak.
- Notifikasi/event QR menunggu keberhasilan transaksi. Uji kegagalan simpan, commit, dan notifikasi membedakan hasil persistensi dari side effect.
- Window siswa memakai WIB, menerima tepat awal dan tepat akhir, serta menolak setelah akhir. Nilai jam tutup lama tidak dimigrasikan; UI menampilkan akhir efektif satu jam. Sesi lintas tengah malam ditolak sesuai batas konfigurasi hari yang sama.
- Middleware manual mengizinkan Status hanya pada endpoint manual, sementara FormRequest tetap membatasi PRESENT dan MASTER tetap satu-satunya aktor.
- Konfigurasi PHPUnit menetralkan DB_URL agar URL lingkungan tidak mengalihkan pengujian ke database lain.
- Tidak ada perubahan layanan Finance atau aturan nilai. Perubahan ReportController milik pengguna dipertahankan; koreksi tes nilai hanya teks bulan.

## 4. File Modified

File aplikasi yang diubah oleh audit:

```text
app/Http/Controllers/Academic/AttendanceRequestController.php
app/Http/Controllers/Academic/StudentQRAttendanceController.php
app/Http/Controllers/Core/PermanentQrController.php
app/Http/Middleware/ProtectMassAssignment.php
app/Services/Academic/StudentQRAttendanceService.php
app/Services/Attendance/AttendanceRequestService.php
app/Services/Attendance/AttendanceWindowService.php
app/Services/Attendance/AutomaticStudentAbsenceService.php
app/Services/Core/PermanentQrService.php
resources/views/academic/attendances/qr_scanner.blade.php
resources/views/attendance/qr/index.blade.php
phpunit.xml
phpunit.mysql.xml
```

File tes yang diubah/ditambahkan:

```text
tests/Feature/AttendanceDecisionResponseTest.php (baru)
tests/Feature/AttendanceDecisionTransactionTest.php (baru)
tests/Feature/MasterManualAttendanceTest.php
tests/Feature/StudentQrAttendanceResponseTest.php
tests/Feature/TeacherScoreScheduleIndependentTest.php
tests/Unit/AttendanceOperationalIntegrityTest.php
tests/Unit/AttendanceQrFlowTest.php
tests/Unit/CriticalAttendanceIntegrityTest.php
tests/Unit/H875PermanentQrSettingsSyncTest.php
tests/Unit/StudentPermissionWorkflowIntegrityTest.php
```

Laporan ini dan `tmp/root-cause-audit/` merupakan file baru. Direktori tmp berisi log, XML, fixture SQLite, script diagnosis/concurrency/browser lokal, screenshot, dan bukti pemulihan formatter. Server fixture hanya mengikat loopback; tidak boleh dipublikasikan. Hasil Vite berada di lokasi build proyek.

File pengguna yang perlu dibedakan: `app/Http/Controllers/Finance/ReportController.php` sudah berubah sebelum audit (diff akhir tetap 4 baris tambah/6 hapus). Tiga skrip `h865_forensic_audit.php`, `h865_phase2_continue.php`, `h865_phase2_reset.php` tersentuh formatter dan pemulihan byte asal belum terbukti. Skrip tersebut tidak dieksekusi. File untracked, backup, dan histori tidak dihapus.

## 5. Test Results

Full suite final: **1.181 kasus, 1.169 lulus, 12 SKIP, 0 failure/error, 5.219 assertions**. Unit: 792 lulus; feature: 377 lulus dan 12 SKIP. Ada 5 deprecation PHPUnit existing. Angka dan referensi bukti mesin dicatat dalam `tmp/root-cause-audit/verification-summary.json`.

| Gate | Status | Bukti dan batas cakupan |
|---|---|---|
| Unit/feature/regression/full suite | PASS untuk tes dieksekusi; SKIP eksplisit | `full-complete.xml` / `.log`; 12 kasus MySQL tidak berjalan dalam konfigurasi default SQLite; 5 deprecation PHPUnit existing tetap ada |
| MySQL integration terisolasi | PASS, 14 tes / 210 assertions | `mysql-final.xml`; real repository, rollback keputusan, unique guards, auth, finance rollback. DB aktual diperiksa sebagai wms_mysql_test |
| RBAC dan route contracts | PASS, 37 tes / 125 assertions | `rbac-routes.xml`; juga tercakup ulang full suite |
| Concurrency pengajuan/keputusan | PASS untuk skenario diuji | `concurrency-audit_e6b0bb6daa33_.json`: dua proses pengajuan menghasilkan satu request; approve vs reject menghasilkan satu keputusan sah dan satu attendance. Cache array terpisah per proses |
| Concurrency harness lama | SKIP | `test_database_guarantees_hold_under_real_parallel_processes` dikecualikan dari run MySQL karena cleanup menghapus fixture; harness khusus audit mempertahankan tabel/fixture |
| PHP syntax | PASS, 569 file | `php-syntax-complete.json`, scope app/bootstrap/config/database/routes/tests; dua file QR terakhir juga diperiksa ulang |
| Laravel route audit | PASS lokal | `routes-complete.json` dan tes route/RBAC; file output mengandung warning PHP sebelum JSON |
| Blade compilation | PASS | `blade-complete.log` |
| Vite production build | PASS | `vite.log`: 56 modul, build selesai; tidak ada perubahan dependency |
| Playwright approval | PASS lokal | `browser-results.json`: klik tombol approve/reject, endpoint POST benar, CSRF hadir; tanpa CSRF→419 |
| HTTP QR/manual/ALPA melalui Playwright | PASS lokal | `browser-attendance.json`; request nyata ke Laravel fixture dan database SQLite terisolasi |
| Scanner menampilkan TERLAMBAT | PASS UI | `browser-scanner.json`; respons scan dimock khusus pemeriksaan rendering JavaScript, bukan bukti transaksi backend |
| Error correlation | PASS untuk jalur diuji | `AttendanceDecisionResponseTest` serta `StudentQrAttendanceResponseTest`, termasuk Error dan RuntimeException dengan report ID/log sesuai |
| Penyebab insiden dan operasi produksi | BLOCKED | Tidak tersedia stack trace, correlation ID, snapshot skema, dan bukti scheduler produksi |
| Perlindungan semua file pengguna | BLOCKED | Tiga skrip h865 belum dapat dipulihkan persis; 692 histori sudah dipulihkan |

### Hasil aktual skenario wajib

| Skenario | Hasil aktual |
|---|---|
| Siswa mengajukan izin → guru ACC | Request APPROVED, attendance IZIN, tepat satu baris; endpoint `/teacher/workspace/attendance-requests/REQ0000003/approve` |
| Siswa mengajukan izin → guru menolak | Request REJECTED, attendance ALPA, tepat satu baris; endpoint `/teacher/workspace/attendance-requests/REQ0000004/reject` |
| SAKIT | Regression service approval SAKIT dan notifikasi lulus; browser khusus izin, bukan bukti browser sakit |
| QR 06:15, mulai 06:00 | HTTP 200, PRESENT |
| QR kedua hari yang sama 06:16 | HTTP 409, ALREADY_ATTENDED |
| QR tepat 06:30 / 07:00 | HTTP 200, LATE |
| QR 07:00:01 | HTTP 422, TOO_LATE |
| Token dinamis kedaluwarsa/signature salah | Regression menolak dengan zero write dalam window aktif |
| Manual MASTER | PRESENT, Verification_Method MASTER_MANUAL, satu baris |
| ALPA setelah manual | created=0, skipped=1; manual tetap sah |
| ALPA pertama / retry | created=1 lalu created=0, skipped=1; satu ABSENT |
| Nilai tanpa jadwal | Enam feature cases mencakup pilih siswa sesuai kelas, simpan/tampil, edit nilai lama tanpa mengganti pemilik, penolakan kelas asing dan nilai di luar rentang |
| Invoice/Finance | Full regression mencakup plafon pendidikan, idempotency/retry, rekonsiliasi, kegagalan ledger; MySQL memverifikasi rollback status ketika verifikasi ledger gagal |

MySQL concurrency memakai tabel request/attendance yang diklon dengan indeksnya dalam prefix `audit_*`; master pendukung adalah fixture sintetis. Hasil ini membuktikan race yang diuji, bukan semua kombinasi deadlock/QR/manual/ALPA secara bersamaan. Playwright memakai aktor sintetis dan jam server terkontrol; tidak memakai akun atau data produksi.

## 6. Database Safety

Tidak menjalankan migration produksi, mengubah .env produksi, atau menghapus/mengubah data produksi. Tidak menjalankan migrate:fresh, db:wipe, truncate, reset, clean, atau stash.

Mutasi pengujian dibatasi ke SQLite fixture/in-memory dan database **existing** `wms_mysql_test`. Tidak membuat database atau mengubah kredensial. Migration additive existing untuk routing request dijalankan hanya setelah SELECT DATABASE() memastikan DB tes; jumlah request sebelum/sesudah tetap nol. Test MySQL transaksi melakukan rollback fixture. Tes CRUD existing dapat menghapus fixture yang dibuatnya sendiri di dalam transaksi DB tes; tidak menyasar histori pengguna.

Tabel prefiks concurrency beserta baris sintetis sengaja dipertahankan, tanpa DROP/DELETE cleanup. Artefak ini menambah isi DB tes dan perlu dikelola terpisah bila kelak ingin dibersihkan; audit tidak melakukan pembersihan destruktif.

## 7. Git Status

Setelah laporan awal, pengguna secara eksplisit meminta “commit saja”. Atas instruksi terbaru tersebut, perbaikan aplikasi, tes, dan laporan ini dimasukkan ke commit lokal meskipun status audit tetap BLOCKED. Perubahan Finance milik pengguna, tiga skrip h865, backup, histori, dan artefak tmp tidak dimasukkan. Snapshot status sebelum commit ada di `tmp/root-cause-audit/git-status-final.txt`.

Insiden formatter: `php vendor/bin/pint --dirty` menjangkau file untracked/history. Proses dihentikan. `recovery-results.json` mencatat 692 pemulihan, nol histori yang tidak cocok, dari sumber VS Code History asli setelah kecocokan diverifikasi. Untuk tiga h865, ketiadaan baseline berarti tidak boleh menyatakan file pengguna seluruhnya tidak berubah. Bukti dan salinan pemulihan disimpan; tidak ada reset/stash/clean.

## 8. Deployment

**Belum push dan belum deploy.** Tidak ada migration produksi. Server HTTP fixture lokal dihentikan setelah pengujian. Jangan memasukkan tmp, fixture browser, histori, backup, atau skrip h865 ke paket deployment.

## 9. Remaining Risks dan diagnostik produksi read-only

1. Akar masalah insiden produksi belum dapat dihubungkan dengan perbaikan lokal tanpa bukti insiden. Dibutuhkan satu kejadian gagal: waktu WIB, URL + method, role, Request_ID tersamarkan, HTTP status, correlation ID, serta log exception/stack trace terkait. Jangan kirim cookie, token QR, password, kredensial, atau bukti sakit pribadi.
2. Belum diverifikasi kesesuaian migration/index produksi, runtime PHP/DB, cache driver/worker bersama, queue, atau scheduler sistem. Scheduler Laravel terdaftar setiap 10 menit; ini bukan bukti cron produksi benar-benar menjalankannya.
3. Tiga file h865 tersentuh formatter dan membutuhkan salinan pra-format untuk pemulihan persis.
4. Tidak diuji semua interleaving QR/manual/ALPA/approval, kegagalan koneksi pada semua titik commit, atau deadlock skala produksi. Tidak mengklaim exactly-once notifikasi; belum ada durable outbox baru.
5. Penolakan pertama atas PENDING tetap memakai kebijakan existing sinkronisasi ALPA. Race dengan kehadiran sah yang baru ditulis oleh alur lain perlu bukti dan keputusan kebijakan koreksi; audit tidak memperluas kebijakan historis secara spekulatif.
6. Correlation untuk kegagalan sistem yang diuji lulus; tidak semua penolakan bisnis/validasi memiliki ID unik (contoh scan duplikat, token expired, validasi input). Persyaratan literal “setiap kegagalan” belum sepenuhnya terbukti untuk seluruh aplikasi.
7. Warning PHP openssl dimuat dua kali dan 5 deprecation PHPUnit masih ada; tidak mengubah konfigurasi PHP host atau dependency di luar scope.

Langkah memperoleh bukti produksi berikut hanya membaca. Jalankan pada checkout produksi yang benar dan catat hasil tanpa membocorkan rahasia:

```sh
php artisan route:list --path=attendance -v
php artisan schedule:list
php artisan migrate:status
```

Cari correlation ID yang benar-benar tampil pada insiden, bukan pencarian massal data siswa:

```sh
rg -n -C 12 --fixed-strings 'WMS-ID_DARI_INSIDENT' storage/logs
```

Gunakan akses SQL read-only, dengan database aktif diverifikasi. Query berikut tidak mengubah data:

```sql
SELECT DATABASE() AS active_database, VERSION() AS server_version,
       @@session.time_zone AS session_timezone;
SHOW VARIABLES LIKE '%isolation%';
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('MASTER_ATTENDANCE_REQUEST', 'MASTER_ATTENDANCE')
  AND COLUMN_NAME IN ('Request_ID','Attendance_ID','Student_ID','Class_ID',
                     'Attendance_Type','Attendance_Date','Status',
                     'Reviewed_By','Reviewed_At','_runtime_attendance_key');
SHOW INDEX FROM MASTER_ATTENDANCE;
SHOW INDEX FROM MASTER_ATTENDANCE_REQUEST;
```

Setelah kolom terverifikasi, ambil hanya request insiden dengan parameter terikat:

```sql
SELECT Request_ID, Student_ID, Class_ID, Attendance_ID, Attendance_Type,
       Attendance_Date, Status, Reviewed_By, Reviewed_At
FROM MASTER_ATTENDANCE_REQUEST WHERE Request_ID = ?;
SELECT Attendance_ID, Student_ID, Class_ID, Attendance_Type,
       Attendance_Date, Status, Updated_At
FROM MASTER_ATTENDANCE
WHERE Student_ID = ? AND Attendance_Date = ?;
```

Bandingkan Class_ID dengan master siswa dan kewenangan guru melalui hasil yang dibatasi pada ID insiden. Periksa catatan cron/scheduler dan log command ALP pada interval terkait. Jangan menjalankan ulang approval, scan, mark-absent, atau migration di produksi untuk sekadar diagnosis.

## 10. Final Status

**BLOCKED.** Perbaikan lokal dan hasil pengujian tersedia untuk review. Validasi produksi serta pemulihan persis tiga file pengguna masih wajib diselesaikan sebelum keseluruhan tugas dapat dinyatakan PASS. Commit lokal diizinkan melalui instruksi pengguna setelah laporan awal; hal itu tidak mengubah status validasi produksi.
