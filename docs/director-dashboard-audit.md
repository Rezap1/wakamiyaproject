# Audit Dashboard Direktur

Tanggal audit: 17 Oktober 2026 (Asia/Jakarta)

## Kondisi awal dan akar masalah

- Role runtime yang terverifikasi adalah `DIRECTOR`; route awalnya
  `dashboard.director` dengan middleware `role:DIRECTOR,ADMINISTRATOR`.
- `DirectorDashboardController` mengambil data repository langsung, memanggil
  data siswa dua kali, dan menyimpan hasil pada satu cache global tanpa periode.
- View menampilkan `pending_applications`, `revenue_this_month`, dan
  `expense_this_month`, tetapi controller tidak pernah menghasilkan ketiga
  nilai tersebut. Operator `?? 0` menyamarkan data yang tidak tersedia sebagai
  angka nol yang tampak sah.
- Distribusi program/batch dihitung controller tetapi tidak ditampilkan.
- Sidebar Direktur menampilkan pemindai QR pegawai. Ini bertentangan dengan
  peran Direktur yang hanya memonitor dan tidak membuat presensi pribadi.
- Route transaksi menempatkan endpoint baca dan mutasi dalam satu grup luar,
  tetapi endpoint create/store/edit/update/delete sudah memiliki middleware
  dalam `role:ADMINISTRATOR,FINANCE`; akses mutasi Direktur tetap tertutup.
- Monitoring kehadiran pegawai sudah read-only, terfilter, dan memakai
  `AttendanceStatusHelper`, tetapi middleware belum mengizinkan Direktur.
- Workflow persetujuan sudah membatasi item pada `Current_Approver` dan status
  `Waiting Approval`; action kembali memeriksa approver/status di backend.
- Repository produksi saat ini berbasis MySQL walaupun nama contract lama masih
  memakai namespace `GoogleSheets`. Tidak ada repository/runtime module job
  order, matching, application, interview, COE, atau visa yang terverifikasi,
  sehingga data tersebut tidak boleh diciptakan untuk dashboard ini.

## Rencana perubahan

1. Pindahkan agregasi Direktur ke `DirectorDashboardService`, satu kali fetch
   per sumber, dengan periode tervalidasi dan zona waktu aplikasi.
2. Hitung hanya KPI yang memiliki sumber resmi: siswa, SDM, perusahaan,
   transaksi cash-flow, invoice/piutang, pembayaran, attendance, dan approval.
3. Tampilkan definisi/sumber/periode, empty state, error state, tren yang
   bersumber dari transaksi, alert invoice jatuh tempo, antrean approval, serta
   aktivitas audit yang aman.
4. Tambahkan pusat laporan Direktur read-only dengan filter, pencarian,
   pagination, dan CSV export untuk divisi/sumber yang terverifikasi.
5. Izinkan Direktur membuka monitoring kehadiran tanpa memberi route scan atau
   mutasi attendance.
6. Tambahkan regression test yang membuktikan Direktur hanya mendapat endpoint
   baca transaksi dan tetap ditolak pada create/store/edit/update/delete.
7. Tambahkan unit/feature/regression test untuk hitungan, filter, otorisasi,
   route, view desktop/mobile, serta ketiadaan aksi scan Direktur.

## Matriks sumber awal

| Area | Sumber | Aturan utama | Detail |
| --- | --- | --- | --- |
| Siswa | `MASTER_STUDENT` | `SheetValue::isOperationalStudent`; pendaftar memakai `Registration_Date` | Pusat laporan Direktur |
| SDM | `MASTER_EMPLOYEE`, `MASTER_TEACHER` | `Is_Active != FALSE`; status keluar tidak dihitung aktif | Pusat laporan / monitoring attendance |
| Marketing | `MASTER_COMPANY` | `Is_Active != FALSE` | Pusat laporan Direktur |
| Cash-flow | `FINANCE_TRANSACTION` | aktif, tanggal valid dalam periode, tipe Income/Expense resmi | Laporan keuangan / transaksi |
| Piutang | `FINANCE_INVOICE` | bukan Draft/Cancelled/Paid; sisa positif | Laporan outstanding |
| Pembayaran | `FINANCE_PAYMENT` | status `Waiting Verification` untuk antrean | Pusat laporan Direktur |
| Kehadiran | `MASTER_ATTENDANCE` + employee map | hanya record Employee_ID yang sah; normalisasi helper attendance | Monitoring kehadiran |
| Persetujuan | `MASTER_APPROVAL`, `MASTER_APPROVAL_HISTORY`, `MASTER_USER`, `MASTER_ROLE` | antrean memakai approver DIRECTOR; keputusan selesai ditautkan lewat actor yang role-nya DIRECTOR | Kotak persetujuan / pusat laporan |
| Aktivitas | `AUDIT_LOG` | urut `Created_At`, ringkasan tanpa payload mentah | Dashboard Direktur |

## GAP terverifikasi

Job order, matching, application, interview, COE, dan visa tidak mempunyai
contract repository/runtime module pada repository saat audit. Item tersebut
tidak diimplementasikan dan tidak diberi angka nol palsu.
