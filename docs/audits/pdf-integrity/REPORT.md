# WMS — Audit integritas PDF dan perbaikan akar penyebab

Tanggal: 4 November 2026. Workspace: `D:\orderan\wakamiya`. Status akhir: **PARTIAL PASS**.

Perbaikan dan verifikasi lokal telah diselesaikan untuk alur aktif yang tercantum di inventaris. Bukti berasal dari PDF yang benar-benar dihasilkan aplikasi, ekstraksi teks, fixture terhitung, dan render halaman. **Hasil ini tidak membuktikan kebenaran data produksi.** Tidak ada akses diagnostik produksi, perubahan `.env`, migration, seeder, push, atau deploy dalam audit ini. Commit dibuat kemudian atas instruksi eksplisit pengguna.

## A. INVENTORY

- **182 route kandidat** ditemukan dalam pencarian luas `pdf/print/receipt/download/export`, termasuk CSV/Excel dan unduhan lampiran yang bukan generator PDF: [inventaris route luas](all-export-download-routes.json).
- **109 route terkait PDF/cetak**, mencakup **108 endpoint keluaran PDF/cetak** dan satu halaman verifikasi receipt publik: [route, action, middleware](routes.json). Route dinamis dari loop telah diekspansi oleh router Laravel dalam lingkungan testing SQLite.
- **57 alur/varian aktif** diuji. Ini menghitung jenis laporan, konteks siswa/guru, mode presensi, manual versus invoice tersimpan, arsip otomatis, dan lampiran email secara terpisah; bukan klaim 57 template berbeda.
- **31 template/komponen** diinventarisasi: 17 template aktif, 5 layout/komponen bersama, dan 9 template dormant. [Daftar template](templates.md).
- Matriks lengkap **Modul | PDF | Route | Controller | Sumber | Filter | Status**, beserta rekonsiliasi per dokumen: [inventory.md](inventory.md), [matrix.csv](matrix.csv), [matrix.json](matrix.json).

Modul yang tercakup: Core/Administration, Academic, Teacher, Student, HR/Payroll, Finance, Attendance, alumni/placement, notifikasi dan aktivitas. Tidak ditemukan generator PDF mandiri untuk Inventory atau Marketing selain laporan perusahaan/alumni yang sudah tercatat. Ekspor khusus dashboard Director berupa CSV; akses Director ke laporan Finance PDF dan pengumuman ikut diuji. `PayrollDocumentController::showSlip` dan sembilan template lama tidak mempunyai call site aktif yang ditemukan; tidak diberi PASS operasional.

Pencarian mencakup `Pdf::loadView`, `ReportHelper::export`, `Exportable`, `DocumentAutomationService`, `EmailDeliveryService`, route dinamis, preview, print, serta unduhan bukti/lampiran. Unduhan file unggahan tidak diperlakukan sebagai generator laporan; kebenaran isi file unggahan bukan sesuatu yang dapat direkonstruksi dari database.

## B. ROOT CAUSE

| Temuan yang dibuktikan | Lokasi / fungsi | Bukti dan akibat |
|---|---|---|
| Roster untuk PDF guru menggunakan ruang lingkup mengajar, sementara halaman mengizinkan wali kelas tanpa jadwal | `TeacherWorkspaceController::scoreReportResponse` | Fixture guru wali kelas, siswa dan nilai ada, tanpa schedule. Scope roster yang salah menghilangkan identitas/data. PDF student/class kini memuat Siswa Audit dan 88,5. |
| Pilihan periode wajib menghalangi nilai historis; label filter tidak selalu diteruskan ke layout | Controller Teacher, `Exportable`, report/print layout | Nilai bertanggal bisnis 31 Oktober, diimpor 4 November. Tanggal bisnis menghasilkan satu nilai; rentang 4 November menghasilkan kosong; Semua Periode tetap memuat nilai. |
| Index akun dengan numeric-looking code rentan perubahan key, dan akun historis nonaktif dikeluarkan dari lookup | `ReportController`, `FinanceReportService::getCashFlow` | Account_Code `1101` dan akun historis `ACC-OLD` berhasil direkonsiliasi. Perbaikan lokal pengguna di ReportController dipertahankan; audit memperluas lookup historis dan validasi ambiguitas pada service. |
| Controller laporan membentuk dataset/pencarian yang berbeda dari halaman | Score, Invoice, Payment, Payroll, Teacher, Student, Employee, Subject, Assessment controllers | Pencarian nama siswa pada invoice/payment/score, ID guru/siswa, status/type, teaching status, dan tanggal registrasi/pegawai diuji melalui UI response dan PDF. |
| Kolom tanggal ekspor tidak sesuai kontrak sumber | Assessment `Assessment_Date` vs `Date`; Activity `Timestamp` vs `Created_At`; Transaction presentation vs `raw.Transaction_Date`; Schedule tidak punya `Schedule_Date` | Range/today yang sebelumnya membuang data diuji. Jadwal berulang secara eksplisit menggunakan tanggal pembuatan, bukan mengarang tanggal kegiatan. |
| Ekspor halaman aktif memakai ukuran halaman dan urutan yang tidak sama; beberapa halaman tidak dipaginasi | `Exportable::handleExport`, controller config | Halaman akun kedua memuat hanya record ke-16 setelah 15 record pertama; print dan preview sama. Materi yang tidak dipaginasi tetap memuat 13 record. |
| Ringkasan disembunyikan/tidak diteruskan atau tetap memakai dataset sebelum filter; dataset kosong dialihkan | `Exportable`, ReportController, layout | PDF kosong dengan Total Data 0 tetap diterbitkan. Saldo awal/akhir Rp350.000 tetap benar pada bulan tanpa transaksi. Piutang dihitung ulang dari dataset terfilter. |
| Baris invalid/duplikat dihapus diam-diam oleh pipeline umum | `Exportable` | Penghapusan tersembunyi dihilangkan; dataset invalid ditolak. Ledger dengan ID transaksi duplikat ditolak, sumber bisnis berulang diberi temuan eksplisit. |
| Arus kas dapat mengabaikan tipe/date bermasalah dan menampilkan referensi teknis tanpa konteks | `FinanceReportService::getCashFlow` | Tipe tidak dikenal ditolak; tanggal invalid dihitung sekali dan ID-nya dilaporkan; akun hilang ditandai; Payment/PaymentReversal/Payroll memiliki label bermakna dengan referensi tetap tersedia. |
| Audit log dipotong ke 100 sebelum ekspor; aktivitas dipotong ke 1.000 sebelum scope/filter | AuditLogController, ActivityService | Ekspor audit 102 record menghasilkan seluruh 101 AuditRow tambahan, sementara current_page tetap 100 sesuai layar. Nama user dan kolom IP diselesaikan dari sumber yang benar. |
| Validasi jam lembur hanya menerima H:i | `OvertimeRepository::isTime`, validation | Nilai MySQL `TIME(6)` berbentuk `17:00:00.000000` sebelumnya ditolak. Kini diterima dan perbandingan jam menggunakan waktu yang diparse. Durasi 2 jam × Rp25.000 menghasilkan Rp50.000. |
| Hasil update siswa berupa boolean diteruskan sebagai profil dokumen kelulusan | `StudentService::updateStudent` | Generator sekarang memakai profil tersimpan + perubahan yang disahkan, program dan nilai yang benar; kegagalan membaca nilai tidak berubah menjadi transkrip kosong. |
| Metadata generator tidak termasuk kolom fisik MASTER_DOCUMENT | `DocumentRepository::create/fetchAll` | File_Path/Hash/Version/Document_Type disimpan dalam envelope teridentifikasi pada Content. Baca ulang arsip melalui repository dan SHA-256 file cocok pada MySQL. Tidak ada schema migration. |
| Payroll mengirim datetime ke kolom DATE; arsip otomatis tidak memakai payload yang sama dengan unduhan | `PayrollService::updateStatus/buildPayslipDocumentData` | Uji MySQL semula menghasilkan `Expected an unambiguous date`. Approved_Date/Paid_Date mengikuti kontrak DATE; rincian/identitas perusahaan/QR dipakai bersama untuk arsip dan download. |
| Kwitansi invoice memakai nominal tagihan, bukan uang yang sudah diterima | `SmartGeneratorController::prepareStudentInvoiceDocumentData` | Tagihan Rp2.000.000 dengan pembayaran diterima Rp500.000 menghasilkan kwitansi Rp500.000. Payload klien palsu tidak mengganti siswa/nominal invoice. Tanpa pembayaran diterima, kwitansi ditolak. |
| Nilai contoh bank, NPWP, penandatangan, alamat, uang dan tanggal muncul sebagai data dokumen | SystemSettingService defaults, SmartGenerator, smart templates, official payslip | Default identitas/nominal contoh dihapus; konfigurasi tersimpan atau input manual dipakai. Konfigurasi kosong tidak menciptakan nomor rekening/NPWP/penandatangan fiktif. Periode/tanggal bayar slip tidak diganti tanggal sekarang. |
| Flex/min-height A4 menumpuk konten QR dan menghasilkan halaman kedua kosong; SVG inline tidak muncul pada dokumen resmi | QR print template, shared PDF footer, official payslip | QR presensi memakai block layout khusus DomPDF dan menjadi satu halaman. SVG verifikasi disematkan sebagai image data URI agar dirender. |

## C. FIXES

Perbaikan menyasar query, identitas, kontrak tipe MySQL, dan payload dokumen. Nilai siswa/transaksi sumber tidak diubah untuk menyesuaikan tampilan. Perubahan query bersama dibatasi pada controller yang terbukti memiliki perbedaan filter; tidak ada pemindahan arsitektur repository secara besar-besaran.

Nama namespace `GoogleSheets` tidak otomatis berarti sumber lama digunakan. Repository runtime yang dipakai fixture diarahkan ke MySQL dan diuji dengan koneksi `wms_mysql_test`; label diagnostik cash flow diubah menjadi `runtime_repository`. Pemeriksaan runtime read-only repository MySQL yang telah tersedia juga lulus; ini tidak membuktikan konfigurasi koneksi produksi.

Referensi yang rusak tidak diberi nama akun palsu. Laporan arus kas menampilkan daftar temuan integritas; tanggal tidak dapat ditempatkan ke periode tidak disembunyikan tanpa catatan. Duplikasi sumber bisnis tetap terlihat dalam jumlah ledger sebenarnya dan ditandai untuk rekonsiliasi—tidak dikoreksi dengan menghapus transaksi.

UI guru mempunyai pilihan satu siswa/satu kelas, pemilihan entitas, Semua Periode, date picker, validasi tanggal, ringkasan filter dan pesan kosong. Browser fixture diuji pada lebar 390 dan 1440: tidak ada horizontal overflow pada mobile, pergantian kelas memperbarui ringkasan, rentang terbalik tidak valid, dan tombol dapat dijangkau. [Hasil interaksi](../../../tmp/pdf-integrity/teacher-ui-check.json), [mobile](../../../tmp/pdf-integrity/teacher-filter-mobile.png), [desktop](../../../tmp/pdf-integrity/teacher-filter-desktop.png). Semua request browser diintersep; tidak ada server produksi/API eksternal yang dihubungi.

## D. DATA RECONCILIATION

Perhitungan expected berikut ditulis secara independen dari fungsi cash flow yang diuji.

| Skenario fixture | Sumber | PDF |
|---|---|---|
| Saldo awal November | TRX-OPEN 30 Oktober: Rp100.000 | Rp100.000 |
| Income periode | TRX-500 Rp500.000 + TRX-1250 Rp1.250.000 | Rp1.750.000 |
| Expense periode | TRX-1500 Rp1.500.000 | Rp1.500.000 |
| Arus kas bersih | 1.750.000 − 1.500.000 | Rp250.000 |
| Saldo akhir | 100.000 + 250.000 | Rp350.000 |
| Akun 1101, hari ini | Opening 100.000; income 500.000; expense 1.500.000 | Net −1.000.000; closing −900.000 |
| Desember tanpa transaksi | Carry-forward 350.000; nol transaksi | Total Data 0; opening/closing 350.000 |
| Piutang | Tagihan 2.000.000 − verified 500.000; pending 900.000 tidak diterima | Sisa 1.500.000 |
| Kwitansi dari invoice | Uang verified 500.000, tagihan 2.000.000 | Kwitansi 500.000 |
| Payroll | Pokok 3.000.000 + tunjangan 200.000 − potongan 100.000 | Net 3.100.000 |
| Lembur | 2 jam × 25.000 | 50.000 |
| Nilai historis | SCR-0001: 88.50; assessment 31 Oktober; Created_At 4 November | 88,5 pada tanggal bisnis yang benar |
| Duplikasi sumber + reversal | Tambahan income 500.000 + 500.000 − reversal 500.000 | Closing 850.000 serta peringatan sumber berulang |

Ledger kas dasar menggunakan Reference_Type=Other sebagai fixture aritmetika terpisah. Tidak diasumsikan bahwa kesamaan nominal Rp500.000 berarti transaksi itu pasti berasal dari PAY-AUDIT. Receipt fixture sengaja tidak memiliki akun penerima yang dapat diverifikasi; PDF menampilkan peringatan tersebut. **PASS fixture receipt berarti angka/identitas/peringatan sesuai sumber, bukan bahwa akun penerimanya telah sah.**

Usulan rekonsiliasi historis terpisah: jalankan diagnostik berizin, kelompokkan orphan/ambiguous IDs/duplicate source/date invalid/archive metadata hilang, cocokkan dengan bukti transaksi dan backup, lalu buat usulan perubahan per record untuk persetujuan. Tidak ada perbaikan data historis otomatis.

## E. PDF VERIFICATION

[Matriks 57 alur](inventory.md#rekonsiliasi-per-alur) mencantumkan nama, record sumber, record PDF, angka sumber/PDF, status dan tautan bukti. Seluruh 57 alur mempunyai bukti fixture di SQLite dan MySQL.

Artefak utama MySQL: **114 PDF, 122 halaman**. Teks diekstrak dengan pypdf; seluruh halaman dirender dengan PyMuPDF. [Manifest SHA-256, jumlah halaman, gambar dan pemeriksaan batas halaman](../../../tmp/pdf-integrity/mysql/rendered/manifest.json). Tidak ditemukan kata dengan bounding box di luar halaman. Pemeriksaan otomatis ini dilengkapi inspeksi contact sheet dan halaman penting pada ukuran baca; ia tidak sendirian membuktikan bebas overlap. Audit log 102 record terbaca pada 5 halaman dengan header tabel berulang. QR presensi yang semula overlap/berhalaman kosong sudah diperiksa ulang setelah perbaikan.

Kasus tambahan meliputi 26 daftar kosong, filter entitas/tanggal/nama, nilai historis tanpa jadwal, orphan student/account, tanggal kosong, soft-deleted ledger, duplikasi ID/sumber, invoice pending payment, current_page/preview/print, penolakan dokumen siswa lain dan arsip otomatis dengan hash. Dua lampiran email dibuat dan diekstrak dengan `Mail::raw` diintersep; **tidak ada email dikirim**.

Sembilan template dormant berstatus **SKIP operasional**, bukan PASS. Preview/print berbagi pipeline dan diuji pada sampel serta jalur presensi yang sebelumnya berbeda; tidak semua alias × role × filter diuji sebagai kombinasi terpisah. Demikian pula, setiap jenis dokumen individual belum diuji dengan seluruh kombinasi FK null, lifecycle dan data historis yang mungkin.

## F. TEST RESULTS

| Run utama | Passed | Failed / Error | Skipped | Assertions | Bukti |
|---|---:|---:|---:|---:|---|
| GlobalPdfIntegrityTest — SQLite in-memory | 114 | 0 | 0 | 1.019 | [JUnit](../../../tmp/pdf-integrity-sqlite-release.xml) |
| GlobalPdfIntegrityTest — MySQL | 114 | 0 | 0 | 1.133 | [JUnit](../../../tmp/pdf-integrity-mysql-release.xml) |
| Regresi terkait yang sudah ada | 485 | 0 | 0 | 1.546 | [JUnit](../../../tmp/pdf-regression-release.xml) |
| **Total eksekusi run utama** | **713** | **0** | **0** | **3.698** | 599 kasus unik; 114 kasus yang sama dijalankan pada kedua DB |

Ada 5 PHPUnit deprecations pada suite regresi yang sudah ada. Warning PHP `openssl already loaded` berasal dari konfigurasi PHP lokal; hasil dinilai dari PHPUnit/JUnit, bukan exit code redirect PowerShell semata.

Sesudah run utama, perubahan terakhir yang terbatas diperiksa ulang: penangkapan HTML guru (1 tes/22 assertions), default profil/NPWP kosong (SQLite 1/14; MySQL 1/15), dan penyematan QR resmi (lihat [SQLite](../../../tmp/pdf-official-qr-sqlite.xml), [MySQL](../../../tmp/pdf-official-qr-mysql.xml)). Run ulang ini merupakan subset/recheck, tidak ditambahkan sebagai kasus unik baru. Browser interaksi tidak menghasilkan JavaScript error. PHP lint 38 file lulus; `git diff --check` lulus.

Database MySQL test sudah tersedia, tetapi skema permanennya tidak lengkap untuk seluruh kontrak runtime. Fixture membuat **TEMPORARY TABLES yang hanya hidup pada koneksi test**, memakai WmsMigrationManifest + runtime columns dan tipe MySQL asli (DECIMAL, DATE, TIME(6), DATETIME(6), string ID). Nama database aktual diperiksa harus `wms_mysql_test`. Tabel permanen dan row yang sudah ada tidak diubah. SQLite menggunakan `:memory:`. Tidak ada migration/seeder dijalankan. FK/unique constraints produksi tidak disimulasikan penuh; beberapa fixture sengaja mengizinkan data rusak untuk memverifikasi penolakan/pelaporan.

Daftar suite regresi yang dijalankan: [pdf-regression-files.txt](../../../tmp/pdf-regression-files.txt), ditambah H861TeacherScorePdfFilteringTest, TeacherScoreScheduleIndependentTest dan EmailDeliveryOAuthSecurityTest. Ini bukan seluruh suite aplikasi; pengujian yang menjalankan migration tidak dijalankan tanpa persetujuan.

### Reproduksi lokal

Prasyarat: PHP/vendor proyek, Python dengan pypdf 6.21.4 dan PyMuPDF 1.28.2 (audit ini menaruhnya di `tmp/pdf-audit-python`), MySQL test yang telah dikonfigurasi. Script ekstraksi juga menerima Python dari `PDF_AUDIT_PYTHON`. Jangan mengganti koneksi menjadi produksi.

```powershell
C:\xampp\php\php.exe tests/Support/pdf_inventory.php
C:\xampp\php\php.exe vendor/bin/phpunit tests/Feature/GlobalPdfIntegrityTest.php
C:\xampp\php\php.exe vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/GlobalPdfIntegrityTest.php
python tests/Support/pdf_render.py
python tests/Support/pdf_audit_matrix.py
```

File log awal yang gagal tetap dipertahankan di `tmp` sebagai jejak temuan; jangan menafsirkan log awal sebagai status kode akhir. Dependensi ekstraksi PDF perlu disiapkan pada CI sebelum suite baru dijalankan; tidak ada skip diam-diam saat dependency tidak tersedia.

## G. FILES MODIFIED

[Daftar lengkap file yang diubah/ditambahkan](files-modified.md). Perubahan lokal pengguna pada Finance ReportController tetap dipertahankan. Backup, scratch, tmp, riwayat dan skrip lama yang telah ada tidak dibersihkan. Artefak audit berada di `docs/audits/pdf-integrity` dan `tmp/pdf-integrity`; tidak ada commit/push/deploy.

## H. REMAINING RISKS

1. **Produksi belum diverifikasi.** Nominal contoh/screenshot bukan bukti produksi benar. Koneksi, skema aktual, rekening, lifecycle dan relasi historis memerlukan pembacaan produksi yang disetujui.
2. Sembilan template dormant tidak mempunyai jalur operasional yang dapat diuji. Jika diaktifkan lagi, kontrak data dan layout harus diverifikasi terlebih dahulu.
3. Bukti fixture bukan cakupan Cartesian seluruh role/alias/filter/null/FK. Matriks role eksplisit mencakup sembilan role × lima keluarga laporan, ditambah scope Teacher/Student serta regresi yang tersedia. Tidak ada klaim seluruh kombinasi RBAC sudah dibuktikan.
4. Arsip lama yang kehilangan File_Path/Hash/Version sebelum perbaikan tidak dipulihkan otomatis. Default bank/NPWP/penandatangan yang **sudah tersimpan** sebagai data historis juga tidak dihapus atau dianggap sah tanpa pemeriksaan.
5. Cache profil perusahaan yang telah tersimpan sebelumnya bisa mempertahankan default lama. Bila perubahan disetujui untuk rilis, lakukan invalidasi cache profil secara terarah dan verifikasi konfigurasi resmi; audit ini tidak menjalankannya di produksi.
6. Tautan verifikasi resmi adalah signed URL 30 hari. Integritas QR render diuji, tetapi domain publik, jaringan pemindai, sertifikat HTTPS dan validitas dokumen setelah expiry belum diuji end-to-end pada deployment.
7. Skala besar belum dibenchmark. Ekspor audit/aktivitas tidak lagi kehilangan baris karena hard cap; konsumsi memori DomPDF pada data produksi besar perlu diukur sebelum menyatakan kapasitas operasional.
8. Fixture MySQL memverifikasi tipe dan kontrak runtime, bukan kesamaan skema deployment atau semua constraint produksi. Query diagnostik disiapkan sebagai bahan review, belum dieksekusi di produksi.

## I. FINAL STATUS

**PARTIAL PASS — perbaikan kode dan 57 alur fixture terverifikasi; tidak diberikan PASS GLOBAL.**

Tahap berikutnya adalah pemeriksaan produksi read-only menggunakan [query diagnostik yang sudah disiapkan](production-diagnostics-read-only.sql). Sesuai instruksi pengguna, eksekusi produksi menunggu persetujuan eksplisit. Jalankan pemeriksaan kontrak kolom terlebih dahulu, sesuaikan periode yang disetujui, lalu hentikan bila skema berbeda. Temuan data historis harus menghasilkan usulan koreksi terpisah, bukan perubahan otomatis.
