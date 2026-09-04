@props(['userRole' => 'STUDENT'])

@php
    $role = strtoupper(trim($userRole));
    
    // Resolve items per role according to design spec
    if ($role === 'STUDENT') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.student', 'icon' => 'home', 'active' => request()->routeIs('dashboard.student')],
            ['label' => 'Jadwal', 'route' => 'student.schedule', 'icon' => 'calendar', 'active' => request()->routeIs('student.schedule')],
            ['label' => 'Scan QR Siswa', 'route' => 'attendances.student.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('attendances.student.*')],
            ['label' => 'Nilai', 'route' => 'student.progress', 'icon' => 'academic-cap', 'active' => request()->routeIs('student.progress')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'TEACHER') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.teacher', 'icon' => 'home', 'active' => request()->routeIs('dashboard.teacher')],
            ['label' => 'Tugas', 'route' => 'teacher.workspace.assignments', 'icon' => 'document-duplicate', 'active' => request()->routeIs('teacher.workspace.assignments*')],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Nilai', 'route' => 'teacher.workspace.scores', 'icon' => 'academic-cap', 'active' => request()->routeIs('teacher.workspace.scores*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'HR') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.hr', 'icon' => 'home', 'active' => request()->routeIs('dashboard.hr')],
            ['label' => 'Kehadiran', 'route' => 'hr.attendance.monitoring', 'icon' => 'clock', 'active' => request()->routeIs('hr.attendance.*')],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Payroll', 'route' => 'payrolls.index', 'icon' => 'cash', 'active' => request()->routeIs('payrolls.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'EMPLOYEE') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Gaji', 'route' => 'dashboard.personal-payroll', 'icon' => 'cash', 'active' => request()->routeIs('dashboard.personal-payroll*')],
            ['label' => 'Notifikasi', 'route' => 'notifications.index', 'icon' => 'inbox', 'active' => request()->routeIs('notifications.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'FINANCE') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.finance', 'icon' => 'home', 'active' => request()->routeIs('dashboard.finance')],
            ['label' => 'Transaksi', 'route' => 'transactions.index', 'icon' => 'switch-horizontal', 'active' => request()->routeIs('transactions.*')],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Invoice', 'route' => 'invoices.index', 'icon' => 'document-duplicate', 'active' => request()->routeIs('invoices.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'ACADEMIC') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.academic', 'icon' => 'home', 'active' => request()->routeIs('dashboard.academic')],
            ['label' => 'Kelas', 'route' => 'classes.index', 'icon' => 'view-boards', 'active' => request()->routeIs('classes.*')],
            ['label' => 'Manajemen QR Siswa', 'route' => 'attendance.qr.index', 'icon' => 'barcode-scan', 'active' => request()->routeIs('attendance.qr.*')],
            ['label' => 'Jadwal', 'route' => 'schedules.index', 'icon' => 'calendar', 'active' => request()->routeIs('schedules.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'MARKETING') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.marketing', 'icon' => 'home', 'active' => request()->routeIs('dashboard.marketing')],
            ['label' => 'Perusahaan', 'route' => 'companies.index', 'icon' => 'office-building', 'active' => request()->routeIs('companies.*')],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Notifikasi', 'route' => 'notifications.index', 'icon' => 'inbox', 'active' => request()->routeIs('notifications.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'DIRECTOR') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.director', 'icon' => 'home', 'active' => request()->routeIs('dashboard.director')],
            ['label' => 'Persetujuan', 'route' => 'approvals.index', 'icon' => 'clipboard-list', 'active' => request()->routeIs('approvals.*')],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Laporan', 'route' => 'reports.finance.index', 'icon' => 'chart-bar', 'active' => request()->routeIs('reports.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'MASTER') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator', 'icon' => 'home', 'active' => request()->routeIs('dashboard.administrator')],
            ['label' => 'Persetujuan', 'route' => 'approvals.index', 'icon' => 'clipboard-list', 'active' => request()->routeIs('approvals.*')],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Pengguna', 'route' => 'users.index', 'icon' => 'users', 'active' => request()->routeIs('users.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } else {
        // ADMINISTRATOR
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator', 'icon' => 'home', 'active' => request()->routeIs('dashboard.administrator')],
            ['label' => 'Audit', 'route' => 'audit.index', 'icon' => 'clipboard-list', 'active' => request()->routeIs('audit.*')],
            ['label' => 'Scan QR', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Pengguna', 'route' => 'users.index', 'icon' => 'users', 'active' => request()->routeIs('users.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    }
@endphp

<!-- WMS MOBILE BOTTOM NAV BAR (Glassmorphism & Fixed Bottom) -->
<nav aria-label="Navigasi utama mobile" class="mobile-bottom-nav md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-xl border-t border-slate-200/80 shadow-[0_-4px_20px_rgba(0,0,0,0.05)] px-2 pt-2 flex items-center justify-around select-none" style="padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));">
    @foreach($items as $item)
        @php
            $url = Route::has($item['route']) ? route($item['route']) : '#';
            $isActive = $item['active'];
            $iconType = $item['icon'];
        @endphp

        @if($iconType === 'barcode-scan')
            <!-- FLOATING ACTION BUTTON FOR SCAN QR -->
            <div class="relative -top-5 shrink-0 flex flex-col items-center justify-center">
                <a href="{{ $url }}" 
                   class="flex items-center justify-center w-[56px] h-[56px] bg-gradient-to-br from-sky-400 to-sky-600 text-white rounded-full shadow-[0_8px_20px_rgba(14,165,233,0.35)] border-4 border-white active:scale-95 transition-transform focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                   title="Scan QR Presensi">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2M16 4h2a2 2 0 012 2v2M16 20h2a2 2 0 002-2v-2" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8" stroke-width="2" />
                        <rect x="9" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                    </svg>
                </a>
            </div>
        @else
            <!-- REGULAR NAV ITEM -->
            <a href="{{ $url }}" 
               class="flex min-w-0 flex-1 flex-col items-center justify-center min-h-[44px] px-1 py-1 transition-all duration-200 relative {{ $isActive ? 'text-sky-500 font-bold' : 'text-slate-400 hover:text-slate-600' }}"
               aria-label="{{ $item['label'] }}"
               aria-current="{{ $isActive ? 'page' : 'false' }}">
                
                <div class="relative flex items-center justify-center">
                    @if($iconType === 'user')
                        <svg class="w-6 h-6" fill="{{ $isActive ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="{{ $isActive ? '0' : '2' }}" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    @elseif($iconType === 'home')
                        <svg class="w-6 h-6" fill="{{ $isActive ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="{{ $isActive ? '0' : '2' }}" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    @else
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @switch($iconType)
                                @case('inbox') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path> @break
                                @case('clipboard-list') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path> @break
                                @case('users') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path> @break
                                @case('clock') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path> @break
                                @case('cash') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path> @break
                                @case('office-building') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path> @break
                                @case('academic-cap') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path> @break
                                @case('view-boards') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path> @break
                                @case('calendar') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path> @break
                                @case('chart-bar') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path> @break
                                @case('folder-open') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"></path> @break
                                @case('document-duplicate') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path> @break
                                @case('switch-horizontal') <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path> @break
                                @default <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            @endswitch
                        </svg>
                    @endif
                </div>

                <span class="w-full truncate text-center text-[10px] font-medium mt-1 tracking-tight leading-tight">{{ $item['label'] }}</span>

                @if($isActive)
                    <span class="absolute bottom-0 w-8 h-1 bg-sky-500 rounded-t-full"></span>
                @endif
            </a>
        @endif
    @endforeach
</nav>
