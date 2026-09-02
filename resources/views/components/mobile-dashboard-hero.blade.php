@props(['userRole' => 'STUDENT', 'kpiData' => [], 'quickActionsData' => []])

@php
    $user = auth()->user();
    $userName = $user->Username ?? $user->Name ?? $user->Full_Name ?? 'User';
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
        'ACADEMIC' => 'Kurikulum & Akademik',
    ];
    $roleDisplay = $roleLabelMap[$role] ?? $role;

    // Scanner URL per role
    $scannerUrl = (str_contains($role, 'STUDENT'))
        ? route('attendances.student.scanner')
        : route('hr.attendance.qr.scanner');

    // Default KPIs
    $cashBalance = 'Rp 0';
    $incomeMonth = 'Rp 0';
    $expenseMonth = 'Rp 0';
    $totalInvoices = 0;
    
    $totalUsers = 0;
    $totalStudents = 0;
    $totalTeachers = 0;
    $totalHrEmployees = 0;
    $totalBatches = 0;
    $totalCompanies = 0;
    
    try {
        if (in_array($role, ['HR', 'ACADEMIC', 'MARKETING', 'ADMINISTRATOR'], true)) {
            $heroCounts = \Illuminate\Support\Facades\Cache::remember('mobile_dashboard_hero_counts', (int) config('cache.wms.dashboard', 60), function () {
                $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
                $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
                $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
                $batchRepo = app(\App\Interfaces\GoogleSheets\BatchRepositoryInterface::class);

                $employees = collect($employeeRepo->fetchAll());

                return [
                    'totalUsers' => collect($userRepo->fetchAll())->count(),
                    'totalStudents' => collect($studentRepo->fetchAll())->count(),
                    'totalHrEmployees' => $employees->count(),
                    'totalTeachers' => $employees->filter(fn ($e) => str_contains(strtoupper($e['Position_ID'] ?? $e['Department_ID'] ?? ''), 'TEACHER') || str_contains(strtoupper($e['Job_Title'] ?? ''), 'GURU'))->count(),
                    'totalBatches' => collect($batchRepo->fetchAll())->count(),
                ];
            });

            $totalUsers = $heroCounts['totalUsers'] ?? 0;
            $totalStudents = $heroCounts['totalStudents'] ?? 0;
            $totalHrEmployees = $heroCounts['totalHrEmployees'] ?? 0;
            $totalTeachers = $heroCounts['totalTeachers'] ?? 0;
            $totalBatches = $heroCounts['totalBatches'] ?? 0;
        }
    } catch (\Throwable $e) {}

    if ($role === 'FINANCE' || isset($kpiData['cash_balance'])) {
        $cashBalance = 'Rp ' . number_format($kpiData['cash_balance'] ?? 0, 0, ',', '.');
        $incomeMonth = 'Rp ' . number_format($kpiData['revenue_this_month'] ?? 0, 0, ',', '.');
        $expenseMonth = 'Rp ' . number_format($kpiData['expense_this_month'] ?? 0, 0, ',', '.');
        $totalInvoices = $kpiData['pending_verification'] ?? 0;
    }
@endphp

<!-- WMS UNIFIED MOBILE DESIGN SYSTEM - WAKAMIYA BRAND PALETTE -->
<div class="lg:hidden block space-y-5 pb-28 max-w-md mx-auto select-none" x-data="wmsMobileDashboardWidget()">

    <!-- MOBILE HEADER -->
    <div class="flex items-center justify-between pt-1 pb-2">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="p-2.5 bg-white text-slate-800 border border-slate-200/80 shadow-xs rounded-2xl flex items-center justify-center shrink-0 active:scale-95 transition-transform">
                <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 bg-[#111827] rounded-full flex items-center justify-center border-2 border-sky-400 p-0.5 shadow-sm">
                    <img src="{{ $companyProfile['company']['logo_url'] ?? asset('img/logo.png.jpeg') }}" alt="Logo" class="w-full h-full object-cover rounded-full bg-white" onerror="this.src='{{ asset('img/logo.png.jpeg') }}'">
                </div>
                <h1 class="text-base font-black text-[#111827] tracking-tight">WMS</h1>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            @php
                $notificationUnavailable = false;
                try {
                    $notifService = app(\App\Services\Core\NotificationService::class);
                    $unreadCount = $notifService->summarizeForUser(null, $role, 1)['unreadCount'] ?? 0;
                } catch (\Throwable $e) {
                    $notificationUnavailable = true;
                    $unreadCount = 0;
                }
            @endphp
            <a href="{{ route('notifications.index') }}" class="relative p-2 rounded-2xl bg-white border border-slate-200/80 shadow-xs text-slate-700 hover:text-sky-600 transition-colors shrink-0">
                <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                @if($unreadCount > 0)
                    <span class="absolute top-0.5 right-0.5 min-w-4.5 h-4.5 px-1 bg-rose-500 text-white text-[9px] font-black rounded-full border-2 border-white flex items-center justify-center shadow-xs">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
                @if($notificationUnavailable)
                    <span class="absolute -bottom-1 -right-1 text-[8px] font-bold text-slate-400">!</span>
                @endif
            </a>
            <a href="{{ route('profile.index') }}" class="block">
                <x-user-avatar class="w-9 h-9" text-size="text-[10px]" />
            </a>
        </div>
    </div>

    <!-- GREETING SECTION -->
    @php
        $context = $dashboardContext ?? [
            'greeting' => 'Selamat datang',
            'greeting_icon' => '👋',
            'user_name' => $userName,
            'role' => $roleDisplay,
            'time' => date('H:i:s'),
            'date' => $todayDateStr,
            'timezone' => 'Asia/Jakarta',
            'timestamp' => time()
        ];
    @endphp
    <div class="bg-white rounded-3xl p-4 border border-slate-200/80 shadow-xs flex flex-col gap-3" x-data="realtimeClockMobile({{ $context['timestamp'] }})">
        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <span class="text-xs font-semibold text-slate-400 flex items-center gap-1">
                    <span>{{ $context['greeting'] }}</span>
                    <span>{{ $context['greeting_icon'] }}</span>
                </span>
                <h2 class="text-base font-black text-slate-900 tracking-tight leading-tight">{{ $context['user_name'] }}</h2>
                <p class="text-[11px] font-bold text-sky-600">{{ $context['role'] }}</p>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider">WAKTU ({{ $context['timezone'] }})</span>
                <span class="text-[14px] font-black text-slate-800 leading-tight block mt-0.5 font-mono" x-text="timeString">{{ $context['time'] }}</span>
                <span class="text-[10px] font-bold text-slate-500 leading-tight block mt-0.5">{{ $context['date'] }}</span>
            </div>
        </div>
    </div>

    <!-- ROLE-SPECIFIC HERO SUMMARY CARD -->
    @if($role === 'FINANCE')
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
    @elseif($role === 'HR')
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-sky-400/20 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-300">👥 DATA KEPEGAWAIAN</span>
                <span class="px-2.5 py-0.5 rounded-full bg-sky-500/20 text-sky-300 font-extrabold text-[10px] border border-sky-400/30">HRD Area</span>
            </div>
            <div class="mt-3">
                <p class="text-xs text-sky-200 font-medium">Total Pegawai Aktif</p>
                <h3 class="text-3xl font-black tracking-tight text-white mt-1">{{ $totalHrEmployees }} <span class="text-lg font-bold">Orang</span></h3>
            </div>
            <div class="mt-4 pt-3 border-t border-white/15 grid grid-cols-2 gap-2 text-xs">
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10">
                    <span class="text-[10px] text-emerald-300 font-bold block">🕒 Jadwal Presensi</span>
                    <span class="font-extrabold text-white text-xs mt-0.5 block">08:00 - 17:00</span>
                </div>
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10">
                    <span class="text-[10px] text-amber-300 font-bold block">🌴 Pengajuan Cuti</span>
                    <span class="font-extrabold text-white text-xs mt-0.5 block">{{ $kpiData['pending_leave'] ?? 0 }} Pending</span>
                </div>
            </div>
        </div>
    @elseif($role === 'ACADEMIC')
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-emerald-950 text-white rounded-3xl p-6 shadow-xl border border-emerald-400/20 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-emerald-300">🎓 PUSAT DATA AKADEMIK</span>
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-extrabold text-[10px] border border-emerald-400/30">Kurikulum</span>
            </div>
            <div class="mt-3">
                <p class="text-xs text-emerald-200 font-medium">Total Siswa Aktif</p>
                <h3 class="text-3xl font-black tracking-tight text-white mt-1">{{ $totalStudents }} <span class="text-lg font-bold">Siswa</span></h3>
            </div>
            <div class="mt-4 pt-3 border-t border-white/15 grid grid-cols-2 gap-2 text-xs">
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10">
                    <span class="text-[10px] text-emerald-300 font-bold block">📚 Total Kelas Berjalan</span>
                    <span class="font-extrabold text-white text-xs mt-0.5 block">{{ $totalBatches }} Kelas</span>
                </div>
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10">
                    <span class="text-[10px] text-sky-300 font-bold block">👨‍🏫 Total Guru</span>
                    <span class="font-extrabold text-white text-xs mt-0.5 block">{{ $totalTeachers }} Guru</span>
                </div>
            </div>
        </div>
    @elseif($role === 'MARKETING')
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-rose-950 text-white rounded-3xl p-6 shadow-xl border border-rose-400/20 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-rose-300">📈 MARKETING & KERJASAMA</span>
                <span class="px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 font-extrabold text-[10px] border border-rose-400/30">Humas</span>
            </div>
            <div class="mt-3">
                <p class="text-xs text-rose-200 font-medium">Partner Perusahaan Aktif</p>
                <h3 class="text-3xl font-black tracking-tight text-white mt-1">{{ $kpiData['total_companies'] ?? 0 }} <span class="text-lg font-bold">Perusahaan</span></h3>
            </div>
        </div>
    @elseif($role === 'DIRECTOR')
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-amber-950 text-white rounded-3xl p-6 shadow-xl border border-amber-400/20 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-amber-300">👑 EXECUTIVE SUMMARY</span>
                <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-extrabold text-[10px] border border-amber-400/30">Pimpinan</span>
            </div>
            <div class="mt-3">
                <p class="text-xs text-amber-200 font-medium">Menunggu Persetujuan Anda</p>
                <h3 class="text-3xl font-black tracking-tight text-white mt-1">{{ $kpiData['pending_approvals'] ?? 0 }} <span class="text-lg font-bold">Dokumen</span></h3>
            </div>
        </div>
    @elseif($role === 'ADMINISTRATOR')
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-cyan-950 text-white rounded-3xl p-6 shadow-xl border border-cyan-400/20 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-cyan-300">⚙️ SYSTEM STATUS</span>
                <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 font-extrabold text-[10px] border border-cyan-400/30">All Systems Normal</span>
            </div>
            <div class="mt-3">
                <p class="text-xs text-cyan-200 font-medium">Total Pengguna Terdaftar</p>
                <h3 class="text-3xl font-black tracking-tight text-white mt-1">{{ $totalUsers }} <span class="text-lg font-bold">Akun</span></h3>
            </div>
        </div>
    @elseif($role === 'STUDENT')
        <!-- STUDENT ACADEMIC HERO CARD IN WAKAMIYA BRAND NAVY & SKY BLUE -->
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-sky-400/20 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-300">🎓 INFORMASI AKADEMIK SISWA</span>
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-extrabold text-[10px] border border-emerald-400/30">{{ $context['enrollment_status'] ?? 'Aktif Belajar' }}</span>
            </div>
            <div class="mt-3">
                <p class="text-xs text-sky-200 font-medium">Program & Kelas Belajar</p>
                <h3 class="text-xl font-black tracking-tight text-white mt-0.5">{{ $context['class'] ?? $kpiData['student_class'] ?? 'Kelas LPK Wakamiya' }}</h3>
                <p class="text-[11px] text-sky-300 font-bold mt-0.5">{{ $context['batch'] ?? $kpiData['student_batch'] ?? 'Menunggu Penempatan' }}</p>
            </div>
            <div class="mt-4 pt-3 border-t border-white/15 grid grid-cols-2 gap-2 text-xs">
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10">
                    <span class="text-[10px] text-sky-300 font-bold block">📊 Kehadiran Siswa</span>
                    <span class="font-extrabold text-emerald-400 text-sm mt-0.5 block">{{ $kpiData['attendance_percentage'] ?? '100%' }}</span>
                </div>
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10">
                    <span class="text-[10px] text-amber-300 font-bold block">📝 Tugas Pending</span>
                    <span class="font-extrabold text-white text-sm mt-0.5 block">{{ $kpiData['pending_assignments'] ?? 0 }} Tugas</span>
                </div>
            </div>
            
            <!-- BILLING SUMMARY SEPARATOR -->
            <div class="mt-4 pt-3 border-t border-white/15">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-300 mb-2 block">💳 RINGKASAN TAGIHAN</span>
                <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                    <div>
                        <span class="text-[10px] text-sky-200 block">Total Tagihan</span>
                        <span class="font-extrabold text-white text-sm mt-0.5 block">Rp {{ number_format($kpiData['total_tagihan'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-emerald-300 block">Sudah Dibayar</span>
                        <span class="font-extrabold text-white text-sm mt-0.5 block">Rp {{ number_format($kpiData['tagihan_dibayar'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="bg-white/10 p-2.5 rounded-2xl border border-white/10 flex justify-between items-center">
                    <div>
                        <span class="text-[10px] text-rose-300 font-bold block">Sisa Tagihan</span>
                        <span class="font-extrabold text-white text-sm mt-0.5 block">Rp {{ number_format($kpiData['sisa_tagihan'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <span class="px-2.5 py-1 rounded-full {{ ($kpiData['status_pembayaran'] ?? 'BELUM LUNAS') === 'LUNAS' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-400/30' : 'bg-rose-500/20 text-rose-300 border-rose-400/30' }} font-extrabold text-[10px] border">
                        {{ $kpiData['status_pembayaran'] ?? 'BELUM LUNAS' }}
                    </span>
                </div>
            </div>
        </div>
    @else
        <!-- DEFAULT TEACHER CARD -->
        <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-sky-400/20 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-300">👨‍🏫 PUSAT DATA PENGAJAR</span>
                <span class="px-2.5 py-0.5 rounded-full bg-sky-500/20 text-sky-300 font-extrabold text-[10px] border border-sky-400/30">Teacher</span>
            </div>
            <div class="mt-3">
                <p class="text-xs text-sky-200 font-medium">Kelas Hari Ini</p>
                <h3 class="text-3xl font-black tracking-tight text-white mt-1">{{ $kpiData['today_class'] ?? 0 }} <span class="text-lg font-bold">Jadwal</span></h3>
            </div>
        </div>
    @endif

    <!-- AKSI CEPAT (QUICK ACTIONS GRID) -->
    <div class="space-y-2">
        <h4 class="text-xs font-black text-[#111827] uppercase tracking-wider px-1">Aksi Cepat</h4>
        
        <div class="grid grid-cols-4 gap-2.5 text-center">
            @if($role === 'FINANCE')
                <a href="{{ route('hr.attendance.qr.scanner') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-lg">📷</div>
                    <span class="text-[11px] font-bold text-slate-700">Scan QR</span>
                </a>
                <a href="{{ Route::has('invoices.create') ? route('invoices.create') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">🧾</div>
                    <span class="text-[11px] font-bold text-slate-700">Invoice</span>
                </a>
                <a href="{{ Route::has('transactions.create') ? route('transactions.create') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg">💰</div>
                    <span class="text-[11px] font-bold text-slate-700">Transaksi</span>
                </a>
                <a href="{{ Route::has('payments.index') ? route('payments.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">💳</div>
                    <span class="text-[11px] font-bold text-slate-700">Bayar</span>
                </a>

            @elseif($role === 'HR')
                <a href="{{ route('hr.attendance.qr.scanner') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">📷</div>
                    <span class="text-[11px] font-bold text-slate-700">Scan QR</span>
                </a>
                <a href="{{ Route::has('employees.create') ? route('employees.create') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg">👤</div>
                    <span class="text-[11px] font-bold text-slate-700">+ Pegawai</span>
                </a>
                <a href="{{ Route::has('attendances.index') ? route('attendances.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">📋</div>
                    <span class="text-[11px] font-bold text-slate-700">Kehadiran</span>
                </a>
                <a href="{{ Route::has('payrolls.index') ? route('payrolls.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center text-lg">💵</div>
                    <span class="text-[11px] font-bold text-slate-700">Payroll</span>
                </a>

            @elseif($role === 'ACADEMIC')
                <a href="{{ route('attendances.student.scanner') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-lg">📷</div>
                    <span class="text-[11px] font-bold text-slate-700">Scan QR</span>
                </a>
                <a href="{{ Route::has('students.index') ? route('students.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">👨‍🎓</div>
                    <span class="text-[11px] font-bold text-slate-700">Siswa</span>
                </a>
                <a href="{{ Route::has('classes.index') ? route('classes.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg">🏫</div>
                    <span class="text-[11px] font-bold text-slate-700">Kelas</span>
                </a>
                <a href="{{ Route::has('schedules.index') ? route('schedules.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">📅</div>
                    <span class="text-[11px] font-bold text-slate-700">Jadwal</span>
                </a>

            @elseif($role === 'MARKETING')
                <a href="{{ route('hr.attendance.qr.scanner') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-lg">📷</div>
                    <span class="text-[11px] font-bold text-slate-700">Scan QR</span>
                </a>
                <a href="{{ Route::has('companies.index') ? route('companies.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">🏢</div>
                    <span class="text-[11px] font-bold text-slate-700">Perusahaan</span>
                </a>
                <a href="{{ Route::has('documents.index') ? route('documents.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg">📁</div>
                    <span class="text-[11px] font-bold text-slate-700">Dokumen</span>
                </a>
                <a href="#" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">📝</div>
                    <span class="text-[11px] font-bold text-slate-700">MoU Baru</span>
                </a>

            @elseif($role === 'DIRECTOR')
                <a href="{{ route('hr.attendance.qr.scanner') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-lg">📷</div>
                    <span class="text-[11px] font-bold text-slate-700">Scan QR</span>
                </a>
                <a href="{{ Route::has('approvals.index') ? route('approvals.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">✅</div>
                    <span class="text-[11px] font-bold text-slate-700">Approval</span>
                </a>
                <a href="{{ Route::has('finance.smart_generator.index') ? route('finance.smart_generator.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">✨</div>
                    <span class="text-[11px] font-bold text-slate-700">AI Smart</span>
                </a>
                <a href="{{ Route::has('reports.finance.index') ? route('reports.finance.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg">📊</div>
                    <span class="text-[11px] font-bold text-slate-700">Laporan</span>
                </a>

            @elseif($role === 'ADMINISTRATOR')
                <a href="{{ route('hr.attendance.qr.scanner') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-lg">📷</div>
                    <span class="text-[11px] font-bold text-slate-700">Scan QR</span>
                </a>
                <a href="{{ Route::has('users.index') ? route('users.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">👤</div>
                    <span class="text-[11px] font-bold text-slate-700">Pengguna</span>
                </a>
                <a href="{{ Route::has('audit.index') ? route('audit.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">📋</div>
                    <span class="text-[11px] font-bold text-slate-700">Audit Log</span>
                </a>
                <a href="{{ Route::has('modules.index') ? route('modules.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg">🧩</div>
                    <span class="text-[11px] font-bold text-slate-700">Modul</span>
                </a>

            @elseif($role === 'STUDENT')
                <a href="{{ route('student.progress') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">🕒</div>
                    <span class="text-[11px] font-bold text-slate-700">Riwayat</span>
                </a>
                <a href="{{ route('student.schedule') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">📅</div>
                    <span class="text-[11px] font-bold text-slate-700">Jadwal</span>
                </a>
                <a href="{{ route('student.portal.assignments') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg">📝</div>
                    <span class="text-[11px] font-bold text-slate-700">Tugas</span>
                </a>
                <a href="{{ route('student.attendance.requests.index') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center text-lg">🤒</div>
                    <span class="text-[11px] font-bold text-slate-700">Sakit/Izin</span>
                </a>

            @else
                <!-- TEACHER -->
                <a href="{{ route('hr.attendance.qr.scanner') }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-100 flex items-center justify-center text-lg">📷</div>
                    <span class="text-[11px] font-bold text-slate-700">Scan QR</span>
                </a>
                <a href="{{ Route::has('scores.index') ? route('scores.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-lg">🎓</div>
                    <span class="text-[11px] font-bold text-slate-700">Input Nilai</span>
                </a>
                <a href="{{ Route::has('assignments.index') ? route('assignments.index') : '#' }}" class="p-2.5 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center gap-1 active:scale-95 transition-transform">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-lg">📝</div>
                    <span class="text-[11px] font-bold text-slate-700">Tugas</span>
                </a>
            @endif
        </div>
    </div>

    <!-- KPI CARDS (CONSISTENT ROUNDED WHITE CARDS) -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black text-[#111827] uppercase tracking-wider">Ringkasan Statistik</h3>
            <span class="text-[11px] font-bold text-sky-600">Update Realtime</span>
        </div>

        @if($role === 'FINANCE')
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
        @elseif($role === 'STUDENT')
            <div class="space-y-3">
                <!-- Academic Stats Row -->
                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('student.schedule') }}" class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3.5 space-y-1 block hover:bg-slate-100 transition-colors">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-bold">📅</div>
                        <p class="text-[10px] font-semibold text-slate-500">Kelas Hari Ini</p>
                        <p class="text-sm font-black text-slate-900">{{ $kpiData['today_class'] ?? 0 }} Kelas</p>
                    </a>
                    <a href="{{ route('student.progress') }}" class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3.5 space-y-1 block hover:bg-slate-100 transition-colors">
                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-bold">🎓</div>
                        <p class="text-[10px] font-semibold text-slate-500">Nilai Terakhir</p>
                        <p class="text-sm font-black text-slate-900">{{ $kpiData['latest_score'] ?? '-' }}</p>
                    </a>
                </div>
                
                <!-- Billing Summary Section -->
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-3">Ringkasan Tagihan Siswa</h4>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="bg-sky-50/50 border border-sky-100 rounded-2xl p-3">
                            <p class="text-[10px] font-semibold text-sky-600 mb-0.5">Total Tagihan</p>
                            <p class="text-[13px] font-black text-slate-900">Rp {{ number_format($kpiData['total_tagihan'] ?? 0, 0, ',', '.') }}</p>
                        </div>
                        <div class="bg-emerald-50/50 border border-emerald-100 rounded-2xl p-3">
                            <p class="text-[10px] font-semibold text-emerald-600 mb-0.5">Sudah Dibayar</p>
                            <p class="text-[13px] font-black text-slate-900">Rp {{ number_format($kpiData['tagihan_dibayar'] ?? 0, 0, ',', '.') }}</p>
                        </div>
                    </div>
                    <div class="bg-{{ ($kpiData['sisa_tagihan'] ?? 0) > 0 ? 'rose' : 'emerald' }}-50 border border-{{ ($kpiData['sisa_tagihan'] ?? 0) > 0 ? 'rose' : 'emerald' }}-200 rounded-2xl p-3.5 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-semibold text-{{ ($kpiData['sisa_tagihan'] ?? 0) > 0 ? 'rose' : 'emerald' }}-600 mb-0.5">Sisa Tagihan</p>
                            <p class="text-base font-black text-slate-900">Rp {{ number_format($kpiData['sisa_tagihan'] ?? 0, 0, ',', '.') }}</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-{{ ($kpiData['sisa_tagihan'] ?? 0) > 0 ? 'rose' : 'emerald' }}-500 text-white font-extrabold text-[10px] shadow-xs uppercase tracking-wider">
                            {{ $kpiData['status_pembayaran'] ?? 'BELUM LUNAS' }}
                        </span>
                    </div>
                    <a href="{{ route('student.billing.index') }}" class="block w-full text-center mt-3 py-2 text-[11px] font-bold text-sky-600 hover:text-sky-800 transition-colors">
                        Lihat Rincian Pembayaran &rarr;
                    </a>
                </div>
            </div>
        @else
            <!-- SYSTEM KPI OVERVIEW -->
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
                    <p class="text-[10px] font-semibold text-slate-500">Pegawai HR</p>
                    <p class="text-base font-black text-slate-900">{{ $totalHrEmployees }}</p>
                </div>
                <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-3 text-left space-y-1">
                    <div class="w-7 h-7 rounded-xl bg-cyan-100 text-cyan-600 flex items-center justify-center text-xs shrink-0 font-bold">🏢</div>
                    <p class="text-[10px] font-semibold text-slate-500">Perusahaan</p>
                    <p class="text-base font-black text-slate-900">{{ $totalCompanies }}</p>
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
            lpkLat: -6.81234,
            lpkLon: 107.19451,

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

    document.addEventListener('alpine:init', () => {
        Alpine.data('realtimeClockMobile', (serverTimestamp) => ({
            timestamp: serverTimestamp,
            timeString: '',
            
            init() {
                this.updateTimeString();
                setInterval(() => {
                    this.timestamp++;
                    this.updateTimeString();
                }, 1000);
            },
            
            updateTimeString() {
                const date = new Date(this.timestamp * 1000);
                const formatter = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
                this.timeString = formatter.format(date).replace(/\./g, ':');
            }
        }));
    });
</script>
