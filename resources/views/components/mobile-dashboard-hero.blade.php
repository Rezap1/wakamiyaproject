@props(['userRole' => 'STUDENT', 'kpiData' => [], 'dashboardContext' => []])

@php
    $user = auth()->user();
    $role = strtoupper(trim((string) $userRole));
    $userName = $dashboardContext['user_name']
        ?? $user->Full_Name
        ?? $user->Name
        ?? $user->Username
        ?? 'Pengguna WMS';
    $greeting = $dashboardContext['greeting'] ?? 'Selamat datang';
    $roleLabels = [
        'MASTER' => 'Master',
        'ADMINISTRATOR' => 'Administrator',
        'ACADEMIC' => 'Tim Akademik',
        'FINANCE' => 'Tim Keuangan',
        'HR' => 'Tim HR',
        'MARKETING' => 'Tim Marketing',
        'DIRECTOR' => 'Direktur',
        'TEACHER' => 'Pengajar',
        'STUDENT' => 'Siswa',
        'EMPLOYEE' => 'Pegawai',
    ];
    $roleLabel = $roleLabels[$role] ?? 'Pengguna WMS';
    $dateLabel = $dashboardContext['date'] ?? now('Asia/Jakarta')->translatedFormat('l, d F Y');
    $formatMoney = static fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');

    $actionsByRole = [
        'MASTER' => [
            ['label' => 'Pengguna', 'route' => 'users.index', 'icon' => 'users'],
            ['label' => 'Persetujuan', 'route' => 'approvals.index', 'icon' => 'inbox'],
            ['label' => 'Audit', 'route' => 'audit.index', 'icon' => 'clipboard-list'],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'camera'],
        ],
        'ADMINISTRATOR' => [
            ['label' => 'Pengguna', 'route' => 'users.index', 'icon' => 'users'],
            ['label' => 'Persetujuan', 'route' => 'approvals.index', 'icon' => 'inbox'],
            ['label' => 'Audit', 'route' => 'audit.index', 'icon' => 'clipboard-list'],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'camera'],
        ],
        'ACADEMIC' => [
            ['label' => 'Siswa', 'route' => 'students.index', 'icon' => 'academic-cap'],
            ['label' => 'Kelas', 'route' => 'classes.index', 'icon' => 'view-boards'],
            ['label' => 'Jadwal', 'route' => 'schedules.index', 'icon' => 'calendar'],
            ['label' => 'QR Siswa', 'route' => 'attendance.qr.index', 'icon' => 'qrcode'],
        ],
        'FINANCE' => [
            ['label' => 'Pembayaran', 'route' => 'payments.index', 'icon' => 'credit-card'],
            ['label' => 'Invoice', 'route' => 'invoices.index', 'icon' => 'document-duplicate'],
            ['label' => 'Transaksi', 'route' => 'transactions.index', 'icon' => 'switch-horizontal'],
            ['label' => 'Laporan', 'route' => 'reports.finance.index', 'icon' => 'chart-bar'],
        ],
        'HR' => [
            ['label' => 'Pegawai', 'route' => 'employees.index', 'icon' => 'identification'],
            ['label' => 'Kehadiran', 'route' => 'hr.attendance.monitoring', 'icon' => 'clock'],
            ['label' => 'Payroll', 'route' => 'payrolls.index', 'icon' => 'cash'],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'camera'],
        ],
        'MARKETING' => [
            ['label' => 'Perusahaan', 'route' => 'companies.index', 'icon' => 'office-building'],
            ['label' => 'Notifikasi', 'route' => 'notifications.index', 'icon' => 'inbox'],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'camera'],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'users'],
        ],
        'DIRECTOR' => [
            ['label' => 'Persetujuan', 'route' => 'approvals.index', 'icon' => 'inbox'],
            ['label' => 'Transaksi', 'route' => 'transactions.index', 'icon' => 'switch-horizontal'],
            ['label' => 'Laporan', 'route' => 'reports.finance.index', 'icon' => 'chart-bar'],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'camera'],
        ],
        'STUDENT' => [
            ['label' => 'Jadwal', 'route' => 'student.schedule', 'icon' => 'calendar'],
            ['label' => 'Tugas', 'route' => 'student.portal.assignments', 'icon' => 'clipboard-list'],
            ['label' => 'Izin / Sakit', 'route' => 'student.attendance.requests.index', 'icon' => 'document-text'],
            ['label' => 'Tagihan', 'route' => 'student.billing.index', 'icon' => 'cash'],
        ],
        'EMPLOYEE' => [
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'camera'],
            ['label' => 'Slip Gaji', 'route' => 'dashboard.personal-payroll', 'icon' => 'cash'],
            ['label' => 'Notifikasi', 'route' => 'notifications.index', 'icon' => 'inbox'],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'users'],
        ],
    ];
    $actions = array_values(array_filter(
        $actionsByRole[$role] ?? [],
        static fn ($action) => Route::has($action['route'])
    ));

    $summaryTitle = 'Ringkasan WMS';
    $summaryLabel = 'Status akun';
    $summaryValue = 'Aktif';
    $summaryTone = 'border-sky-400';
    $metrics = [];

    if (in_array($role, ['MASTER', 'ADMINISTRATOR'], true)) {
        $summaryTitle = 'Ringkasan Sistem';
        $summaryLabel = 'Pengguna terdaftar';
        $summaryValue = number_format((int) ($kpiData['users'] ?? 0), 0, ',', '.').' akun';
        $metrics = [
            ['label' => 'Siswa', 'value' => $kpiData['students'] ?? 0],
            ['label' => 'Pengajar', 'value' => $kpiData['teachers'] ?? 0],
            ['label' => 'Batch', 'value' => $kpiData['batches'] ?? 0],
        ];
    } elseif ($role === 'ACADEMIC') {
        $summaryTitle = 'Ringkasan Akademik';
        $summaryLabel = 'Siswa aktif';
        $summaryValue = number_format((int) ($kpiData['students'] ?? 0), 0, ',', '.').' siswa';
        $summaryTone = 'border-emerald-400';
        $metrics = [
            ['label' => 'Program', 'value' => $kpiData['programs'] ?? 0],
            ['label' => 'Kelas', 'value' => $kpiData['classes'] ?? 0],
            ['label' => 'Kehadiran', 'value' => $kpiData['attendance_rate'] ?? '0%'],
        ];
    } elseif ($role === 'FINANCE') {
        $summaryTitle = 'Ringkasan Keuangan';
        $summaryLabel = 'Saldo kas';
        $summaryValue = $formatMoney($kpiData['cash_balance'] ?? 0);
        $summaryTone = 'border-emerald-400';
        $metrics = [
            ['label' => 'Diterima bulan ini', 'value' => $formatMoney($kpiData['revenue_this_month'] ?? 0)],
            ['label' => 'Pengeluaran', 'value' => $formatMoney($kpiData['expense_this_month'] ?? 0)],
            ['label' => 'Perlu verifikasi', 'value' => $kpiData['pending_verification'] ?? 0],
        ];
    } elseif ($role === 'HR') {
        $summaryTitle = 'Ringkasan Kepegawaian';
        $summaryLabel = 'Pegawai aktif';
        $summaryValue = number_format((int) ($kpiData['active_employees'] ?? 0), 0, ',', '.').' orang';
        $summaryTone = 'border-indigo-400';
        $metrics = [
            ['label' => 'Draft payroll', 'value' => $kpiData['payroll_draft'] ?? 0],
            ['label' => 'Cuti hari ini', 'value' => $kpiData['on_leave'] ?? 0],
            ['label' => 'Departemen', 'value' => $kpiData['total_departments'] ?? 0],
        ];
    } elseif ($role === 'MARKETING') {
        $summaryTitle = 'Ringkasan Kemitraan';
        $summaryLabel = 'Mitra perusahaan';
        $summaryValue = number_format((int) ($kpiData['companies'] ?? 0), 0, ',', '.').' perusahaan';
        $summaryTone = 'border-rose-400';
        $metrics = [
            ['label' => 'Arsip dokumen', 'value' => $kpiData['documents'] ?? 0],
        ];
    } elseif ($role === 'DIRECTOR') {
        $summaryTitle = 'Ringkasan Eksekutif';
        $summaryLabel = 'Menunggu persetujuan';
        $summaryValue = number_format((int) ($kpiData['pending_applications'] ?? 0), 0, ',', '.').' pengajuan';
        $summaryTone = 'border-amber-400';
        $metrics = [
            ['label' => 'Pendapatan bulan ini', 'value' => $formatMoney($kpiData['revenue_this_month'] ?? 0)],
            ['label' => 'Pengeluaran bulan ini', 'value' => $formatMoney($kpiData['expense_this_month'] ?? 0)],
            ['label' => 'Siswa aktif', 'value' => $kpiData['active_students'] ?? 0],
        ];
    } elseif ($role === 'STUDENT') {
        $summaryTitle = 'Ringkasan Siswa';
        $summaryLabel = 'Sisa tagihan';
        $summaryValue = $formatMoney($kpiData['sisa_tagihan'] ?? 0);
        $summaryTone = ($kpiData['sisa_tagihan'] ?? 0) > 0 ? 'border-rose-400' : 'border-emerald-400';
        $metrics = [
            ['label' => 'Kelas hari ini', 'value' => $kpiData['today_class'] ?? 0],
            ['label' => 'Sudah dibayar', 'value' => $formatMoney($kpiData['tagihan_dibayar'] ?? 0)],
            ['label' => 'Pengajuan tertunda', 'value' => $kpiData['request_pending'] ?? 0],
        ];
    } elseif ($role === 'EMPLOYEE') {
        $summaryTitle = 'Ruang Kerja Pegawai';
        $summaryLabel = 'Akses mandiri';
        $summaryValue = 'Presensi dan slip gaji';
        $summaryTone = 'border-indigo-400';
    }
@endphp

<section class="wms-mobile-dashboard lg:hidden" data-mobile-dashboard data-role="{{ $role }}" aria-label="Ringkasan dashboard {{ $roleLabel }}">
    <div class="wms-mobile-greeting" data-mobile-dashboard-greeting>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-500">{{ $greeting }}</p>
            <h2 class="mt-0.5 break-words text-lg font-extrabold leading-tight text-slate-900">{{ $userName }}</h2>
            <p class="mt-1 text-xs font-semibold text-sky-700">{{ $roleLabel }}</p>
        </div>
        <time class="max-w-[9rem] shrink-0 text-right text-xs font-medium leading-relaxed text-slate-500" datetime="{{ now('Asia/Jakarta')->toDateString() }}">
            {{ $dateLabel }}
        </time>
    </div>

    <div class="wms-mobile-summary {{ $summaryTone }}">
        <p class="text-xs font-bold text-sky-200">{{ $summaryTitle }}</p>
        <p class="mt-3 text-xs font-medium text-slate-300">{{ $summaryLabel }}</p>
        <p class="mt-1 break-words text-2xl font-black leading-tight text-white">{{ $summaryValue }}</p>

        @if($metrics !== [])
            <dl class="mt-4 grid grid-cols-1 gap-2 border-t border-white/15 pt-4 min-[360px]:grid-cols-3">
                @foreach($metrics as $metric)
                    <div class="min-w-0 rounded-lg bg-white/10 px-3 py-2.5">
                        <dt class="text-[11px] font-medium leading-snug text-slate-300">{{ $metric['label'] }}</dt>
                        <dd class="mt-1 break-words text-sm font-extrabold leading-snug text-white">{{ $metric['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    @if($actions !== [])
        <div data-mobile-dashboard-actions>
            <h3 class="mb-2 text-sm font-extrabold text-slate-900">Akses cepat</h3>
            <nav class="wms-mobile-action-grid" aria-label="Akses cepat {{ $roleLabel }}">
                @foreach($actions as $action)
                    <a href="{{ route($action['route']) }}" class="wms-mobile-action">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700" aria-hidden="true">
                            <x-sidebar.icon :name="$action['icon']" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 break-words text-xs font-bold leading-snug text-slate-700">{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    @endif
</section>
