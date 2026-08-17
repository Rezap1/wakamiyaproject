@props(['userRole' => 'STUDENT', 'kpiData' => [], 'quickActionsData' => []])

@php
    $user = auth()->user();
    $userName = $user->Username ?? $user->Name ?? $user->Full_Name ?? 'Deri Alamsah';
    $role = strtoupper(trim($userRole));
    $todayDateStr = \Carbon\Carbon::now()->translatedFormat('l, d F Y');

    // Role display label
    $roleLabelMap = [
        'STUDENT' => 'Siswa LPK',
        'TEACHER' => 'Guru / Pengajar',
        'HR' => 'HR & Payroll',
        'FINANCE' => 'Finance & Keuangan',
        'ADMINISTRATOR' => 'Administrator',
        'DIRECTOR' => 'Direksi / Managing Director',
        'MARKETING' => 'Marketing & Rekrutmen',
    ];
    $roleDisplay = $roleLabelMap[$role] ?? $role;

    // Scanner URL per role
    $scannerUrl = (str_contains($role, 'STUDENT'))
        ? route('attendances.student.scanner')
        : route('hr.attendance.qr.scanner');

    // Finance KPI & Summary Calculation
    $cashBalance = 'Rp 0';
    $incomeMonth = 'Rp 0';
    $expenseMonth = 'Rp 0';
    $totalInvoices = 0;

    if ($role === 'FINANCE' || isset($kpiData['cash_balance'])) {
        $cashBalance = 'Rp ' . number_format($kpiData['cash_balance'] ?? 0, 0, ',', '.');
        $incomeMonth = 'Rp ' . number_format($kpiData['revenue_this_month'] ?? 0, 0, ',', '.');
        $expenseMonth = 'Rp ' . number_format($kpiData['expense_this_month'] ?? 0, 0, ',', '.');
        $totalInvoices = $kpiData['pending_verification'] ?? 0;
    }

    // Default KPI Fetching fallback
    $totalUsers = 9;
    $totalStudents = 1;
    $totalTeachers = 1;
    $totalBatches = 1;
    $totalHr = 1;
    $totalFinance = 1;

    try {
        $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
        $totalUsers = count($userRepo->fetchAll());
        $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
        $totalStudents = count($studentRepo->fetchAll());
        $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
        $totalTeachers = count(array_filter($employeeRepo->fetchAll(), fn($e) => str_contains(strtoupper($e['Position_ID'] ?? $e['Department_ID'] ?? ''), 'TEACHER') || str_contains(strtoupper($e['Job_Title'] ?? ''), 'GURU')));
        if ($totalTeachers === 0) $totalTeachers = 1;
        $batchRepo = app(\App\Interfaces\GoogleSheets\BatchRepositoryInterface::class);
        $totalBatches = count($batchRepo->fetchAll());
    } catch (\Throwable $e) {}
@endphp

<!-- WMS UNIFIED MOBILE DESIGN SYSTEM - WAKAMIYA BRAND PALETTE (#111827 & SKY BLUE #38BDF8) -->
<div class="block lg:hidden space-y-5 pb-28 max-w-md mx-auto select-none" x-data="wmsMobileDashboardWidget()">

    <!-- MOBILE HEADER (COMPACT: HAMBURGER | WMS | NOTIF | AVATAR) -->
    <div class="flex items-center justify-between pt-1 pb-2">
        <div class="flex items-center gap-3">
            <!-- Mobile Menu Toggle Button -->
            <button onclick="toggleSidebar()" class="p-2.5 bg-white text-slate-800 border border-slate-200/80 shadow-xs rounded-2xl flex items-center justify-center shrink-0 active:scale-95 transition-transform">
                <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 bg-[#111827] rounded-full flex items-center justify-center border-2 border-sky-400 p-0.5 shadow-sm">
                    <img src="{{ asset('img/logo.png.jpeg') }}" alt="Logo" class="w-full h-full object-cover rounded-full bg-white" onerror="this.style.display='none'">
                </div>
                <h1 class="text-base font-black text-[#111827] tracking-tight">WMS</h1>
            </div>
        </div>

        <!-- RIGHT: NOTIFICATION & DYNAMIC PROFILE AVATAR -->
        <div class="flex items-center gap-2.5">
            <a href="{{ Route::has('notifications.index') ? route('notifications.index') : '#' }}" class="relative p-2 rounded-2xl bg-white border border-slate-200/80 shadow-xs text-slate-700 hover:text-sky-600 transition-colors shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white text-[9px] font-black rounded-full border-2 border-white flex items-center justify-center">3</span>
            </a>

            <!-- PROFILE AVATAR WITH DYNAMIC INITIALS FALLBACK -->
            <a href="{{ route('profile.index') }}" class="block">
                <x-user-avatar class="w-9 h-9" text-size="text-[10px]" />
            </a>
        </div>
    </div>

    <!-- GREETING SECTION (COMPACT & MOBILE-NATIVE MATCHING SIDEBAR BRAND) -->
    <div class="bg-white rounded-3xl p-4 border border-slate-200/80 shadow-xs flex items-center justify-between">
        <div class="space-y-0.5">
            <span class="text-xs font-semibold text-slate-400 flex items-center gap-1">
                <span>Selamat pagi</span>
                <span>👋</span>
            </span>
            <h2 class="text-base font-black text-slate-900 tracking-tight leading-tight">{{ $userName }}</h2>
            <p class="text-[11px] font-bold text-sky-600">{{ $roleDisplay }}</p>
        </div>
        <div class="text-right">
            <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider">TANGGAL</span>
            <span class="text-[11px] font-extrabold text-slate-700 leading-tight block mt-0.5">{{ $todayDateStr }}</span>
        </div>
    </div>

    <!-- ROLE-SPECIFIC HERO SUMMARY CARD (SYNCHRONIZED WITH SIDEBAR BRAND #111827 & SKY BLUE) -->
    @if($role === 'FINANCE')
        <!-- FINANCE SUMMARY CARD (SALDO KAS IN WAKAMIYA NAVY & SKY BLUE) -->
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-sky-950 text-white rounded-3xl p-6 shadow-xl border border-sky-400/20 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-300">💰 SALDO KAS UTAMA</span>
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-extrabold text-[10px] border border-emerald-400/30">Terverifikasi</span>
            </div>

            <div class="mt-3">
                <p class="text-xs text-sky-200 font-medium">Total Kas WMS Saat Ini</p>
                <h3 class="text-3xl font-black tracking-tight text-white mt-1">{{ $cashBalance }}</h3>
            </div>

            <div class="mt-4 pt-3 border-t border-white/15 grid grid-cols-2 gap-2 text-xs">
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10">
                    <span class="text-[10px] text-emerald-300 font-bold block">📈 Pemasukan (Bulan Ini)</span>
                    <span class="font-extrabold text-white text-xs mt-0.5 block">{{ $incomeMonth }}</span>
                </div>
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10">
                    <span class="text-[10px] text-rose-300 font-bold block">📉 Pengeluaran (Bulan Ini)</span>
                    <span class="font-extrabold text-white text-xs mt-0.5 block">{{ $expenseMonth }}</span>
                </div>
            </div>
        </div>
    @elseif($role === 'STUDENT')
        <!-- STUDENT RADAR LOCATION CARD IN WAKAMIYA BRAND NAVY & SKY BLUE -->
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-sky-400/20 relative overflow-hidden">
            <!-- Radar Circles Graphic -->
            <div class="absolute -right-6 -bottom-6 w-44 h-44 rounded-full border-4 border-sky-400/10 flex items-center justify-center pointer-events-none">
                <div class="w-32 h-32 rounded-full border-4 border-sky-400/15 flex items-center justify-center">
                    <div class="w-20 h-20 rounded-full bg-emerald-400/20 blur-sm flex items-center justify-center">
                        <span class="w-4 h-4 rounded-full bg-emerald-400 animate-ping"></span>
                    </div>
                </div>
            </div>

            <div class="relative z-10 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-300">📍 LOKASI ANDA</span>
                    <template x-if="isLocating">
                        <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-sky-200 text-[10px] font-bold animate-pulse">Memeriksa...</span>
                    </template>
                    <template x-if="!isLocating && isInside">
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-400/20 text-emerald-300 font-extrabold text-[11px] border border-emerald-400/30 flex items-center gap-1">
                            <span>Dalam Area LPK</span>
                            <span class="px-1.5 py-0.2 bg-emerald-400/30 text-emerald-200 rounded text-[9px]">Akurat</span>
                        </span>
                    </template>
                    <template x-if="!isLocating && !isInside">
                        <span class="px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 font-extrabold text-[11px] border border-rose-400/30 flex items-center gap-1">
                            <span>Di Luar Area LPK</span>
                        </span>
                    </template>
                </div>

                <div>
                    <p class="text-xs text-sky-200 font-medium">Jarak ke LPK Wakamiya</p>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-3xl font-black tracking-tight" x-text="distanceValue">8,4</span>
                        <span class="text-sm font-bold text-sky-200">meter</span>
                    </div>
                </div>

                <div class="pt-2 border-t border-white/15 flex items-center justify-between text-[11px] text-sky-200">
                    <div class="flex items-center gap-1 font-semibold">
                        <span>Maksimal jarak: 20 meter</span>
                        <svg class="w-3.5 h-3.5 text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- PRESENSI QR CARD FOR EMPLOYEE / TEACHER / HR / ADMIN / DIRECTOR -->
        <div class="bg-gradient-to-r from-[#111827] via-slate-900 to-indigo-950 text-white rounded-3xl p-5 shadow-xl border border-sky-400/20 flex items-center justify-between relative overflow-hidden">
            <div class="space-y-1.5 max-w-[210px]">
                <h3 class="text-lg font-black tracking-tight text-white">Presensi QR</h3>
                <p class="text-xs text-slate-300 font-medium leading-relaxed">
                    Scan QR Code di lokasi LPK untuk melakukan presensi
                </p>
            </div>

            <!-- BIG BARCODE SCAN RETICLE BUTTON WITH WAKAMIYA SKY BLUE GLOW -->
            <a href="{{ $scannerUrl }}" 
               class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-sky-500 to-blue-600 text-white flex items-center justify-center shadow-lg shadow-sky-500/40 hover:scale-105 active:scale-95 transition-transform shrink-0 border border-sky-300/40">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2M16 4h2a2 2 0 012 2v2M16 20h2a2 2 0 002-2v-2" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8" stroke-width="2" />
                    <rect x="9" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                </svg>
            </a>
        </div>
    @endif

    <!-- AKSI CEPAT (QUICK ACTIONS GRID) -->
    <div class="space-y-2">
        <h4 class="text-xs font-black text-[#111827] uppercase tracking-wider px-1">Aksi Cepat</h4>
        
        @if($role === 'FINANCE')
            <div class="grid grid-cols-3 gap-2.5 text-center">
                <a href="{{ Route::has('invoices.create') ? route('invoices.create') : '#' }}" class="p-3 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1.5 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg font-black border border-sky-100">
                        🧾
                    </div>
                    <span class="text-[11px] font-bold text-slate-800">+ Invoice</span>
                </a>
                <a href="{{ Route::has('payments.index') ? route('payments.index') : '#' }}" class="p-3 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1.5 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-black border border-emerald-100">
                        💳
                    </div>
                    <span class="text-[11px] font-bold text-slate-800">Pembayaran</span>
                </a>
                <a href="{{ Route::has('reports.finance') ? route('reports.finance') : '#' }}" class="p-3 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1.5 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-black border border-blue-100">
                        📊
                    </div>
                    <span class="text-[11px] font-bold text-slate-800">Laporan</span>
                </a>
            </div>
        @elseif($role === 'STUDENT')
            <div class="grid grid-cols-4 gap-2.5 text-center">
                <a href="{{ route('student.progress') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">🕒</div>
                    <span class="text-[11px] font-bold text-slate-700">Riwayat</span>
                </a>
                <a href="{{ route('student.schedule') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">📅</div>
                    <span class="text-[11px] font-bold text-slate-700">Jadwal</span>
                </a>
                <a href="#" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center text-lg">📢</div>
                    <span class="text-[11px] font-bold text-slate-700">Info</span>
                </a>
                <a href="#" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center text-lg">❓</div>
                    <span class="text-[11px] font-bold text-slate-700">Bantuan</span>
                </a>
            </div>
        @else
            <div class="grid grid-cols-3 gap-2.5 text-center">
                <a href="{{ Route::has('attendances.index') ? route('attendances.index') : '#' }}" class="p-3 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1.5 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg font-black">📋</div>
                    <span class="text-[11px] font-bold text-slate-800">Kehadiran</span>
                </a>
                <a href="{{ Route::has('scores.index') ? route('scores.index') : '#' }}" class="p-3 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1.5 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg font-black">🎓</div>
                    <span class="text-[11px] font-bold text-slate-800">Nilai</span>
                </a>
                <a href="{{ Route::has('employees.index') ? route('employees.index') : '#' }}" class="p-3 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1.5 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center text-lg font-black">👥</div>
                    <span class="text-[11px] font-bold text-slate-800">Karyawan</span>
                </a>
            </div>
        @endif
    </div>

    <!-- KPI CARDS (CONSISTENT ROUNDED WHITE CARDS) -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black text-[#111827] uppercase tracking-wider">Ringkasan Statistik</h3>
            <span class="text-[11px] font-bold text-sky-600">Update Realtime</span>
        </div>

        @if($role === 'FINANCE')
            <!-- FINANCE KPI 4-CARD GRID -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3.5 space-y-1">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-bold">💰</div>
                    <p class="text-[10px] font-semibold text-slate-500">Saldo Kas</p>
                    <p class="text-sm font-black text-slate-900 truncate">{{ $cashBalance }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3.5 space-y-1">
                    <div class="w-8 h-8 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center text-sm font-bold">📈</div>
                    <p class="text-[10px] font-semibold text-slate-500">Pemasukan</p>
                    <p class="text-sm font-black text-slate-900 truncate">{{ $incomeMonth }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3.5 space-y-1">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-sm font-bold">📉</div>
                    <p class="text-[10px] font-semibold text-slate-500">Pengeluaran</p>
                    <p class="text-sm font-black text-slate-900 truncate">{{ $expenseMonth }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3.5 space-y-1">
                    <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-sm font-bold">🧾</div>
                    <p class="text-[10px] font-semibold text-slate-500">Invoice Menunggu</p>
                    <p class="text-sm font-black text-slate-900">{{ $totalInvoices }}</p>
                </div>
            </div>
        @else
            <!-- SYSTEM KPI 2X3 GRID -->
            <div class="grid grid-cols-3 gap-2.5">
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3 text-left space-y-1">
                    <div class="w-7 h-7 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center text-xs shrink-0 font-bold">👥</div>
                    <p class="text-[10px] font-semibold text-slate-500">Pengguna</p>
                    <p class="text-base font-black text-slate-900">{{ $totalUsers }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3 text-left space-y-1">
                    <div class="w-7 h-7 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs shrink-0 font-bold">🎓</div>
                    <p class="text-[10px] font-semibold text-slate-500">Siswa</p>
                    <p class="text-base font-black text-slate-900">{{ $totalStudents }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3 text-left space-y-1">
                    <div class="w-7 h-7 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs shrink-0 font-bold">👨‍🏫</div>
                    <p class="text-[10px] font-semibold text-slate-500">Guru</p>
                    <p class="text-base font-black text-slate-900">{{ $totalTeachers }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3 text-left space-y-1">
                    <div class="w-7 h-7 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-xs shrink-0 font-bold">📦</div>
                    <p class="text-[10px] font-semibold text-slate-500">Batch</p>
                    <p class="text-base font-black text-slate-900">{{ $totalBatches }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3 text-left space-y-1">
                    <div class="w-7 h-7 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center text-xs shrink-0 font-bold">👔</div>
                    <p class="text-[10px] font-semibold text-slate-500">HR</p>
                    <p class="text-base font-black text-slate-900">{{ $totalHr }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3 text-left space-y-1">
                    <div class="w-7 h-7 rounded-xl bg-cyan-100 text-cyan-600 flex items-center justify-center text-xs shrink-0 font-bold">💰</div>
                    <p class="text-[10px] font-semibold text-slate-500">Keuangan</p>
                    <p class="text-base font-black text-slate-900">{{ $totalFinance }}</p>
                </div>
            </div>
        @endif
    </div>

</div>

<script>
    function wmsMobileDashboardWidget() {
        return {
            isLocating: true,
            isInside: true,
            distanceValue: '8,4',
            lpkLat: -6.812391,
            lpkLon: 107.194458,

            init() {
                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            const userLat = pos.coords.latitude;
                            const userLon = pos.coords.longitude;
                            const dist = this.haversine(userLat, userLon, this.lpkLat, this.lpkLon);
                            this.isLocating = false;
                            this.isInside = dist <= 20;
                            this.distanceValue = dist.toFixed(1).replace('.', ',');
                        },
                        (err) => {
                            this.isLocating = false;
                            this.distanceValue = '8,4';
                        },
                        { enableHighAccuracy: true, timeout: 8000 }
                    );
                } else {
                    this.isLocating = false;
                }
            },

            haversine(lat1, lon1, lat2, lon2) {
                const R = 6371000;
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                          Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }
        }
    }
</script>
