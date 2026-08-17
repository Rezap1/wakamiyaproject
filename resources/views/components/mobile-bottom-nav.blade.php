@props(['userRole' => 'STUDENT'])

@php
    $role = strtoupper(trim($userRole));
    
    // Resolve items per role according to design spec
    if ($role === 'STUDENT') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.student', 'icon' => 'home', 'active' => request()->routeIs('dashboard.student')],
            ['label' => 'Presensi', 'route' => 'attendances.student.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('attendances.student.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'TEACHER') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.teacher', 'icon' => 'home', 'active' => request()->routeIs('dashboard.teacher')],
            ['label' => 'Presensi', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Nilai', 'route' => 'scores.index', 'icon' => 'academic-cap', 'active' => request()->routeIs('scores.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($role === 'HR') {
        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard.hr', 'icon' => 'home', 'active' => request()->routeIs('dashboard.hr')],
            ['label' => 'Presensi', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Karyawan', 'route' => 'employees.index', 'icon' => 'users', 'active' => request()->routeIs('employees.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    } else {
        // ADMINISTRATOR, FINANCE, DIRECTOR, MARKETING
        $dashRoute = Route::has('dashboard.' . strtolower($role)) ? 'dashboard.' . strtolower($role) : 'dashboard.administrator';
        $items = [
            ['label' => 'Dashboard', 'route' => $dashRoute, 'icon' => 'home', 'active' => request()->routeIs('dashboard.*')],
            ['label' => 'Presensi', 'route' => 'hr.attendance.qr.scanner', 'icon' => 'barcode-scan', 'active' => request()->routeIs('hr.attendance.qr.*')],
            ['label' => 'Profil', 'route' => 'profile.index', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
        ];
    }
@endphp

<!-- MOBILE BOTTOM NAVIGATION (WAKAMIYA BRAND SKY BLUE ACTIVE HIGHLIGHT) -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-xl border-t border-slate-200/80 shadow-[0_-4px_20px_rgba(0,0,0,0.05)] px-4 pt-2 flex items-center justify-around select-none" style="padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));">
    @foreach($items as $item)
        @php
            $url = Route::has($item['route']) ? route($item['route']) : '#';
            $isActive = $item['active'];
            $iconType = $item['icon'];
        @endphp

        <a href="{{ $url }}" 
           class="flex flex-col items-center justify-center min-w-[70px] min-h-[44px] py-1 transition-all duration-200 relative {{ $isActive ? 'text-sky-500 font-bold' : 'text-slate-400 hover:text-slate-600' }}">
            
            <div class="relative flex items-center justify-center">
                @if($iconType === 'home')
                    <!-- Home Icon -->
                    <svg class="w-6 h-6" fill="{{ $isActive ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="{{ $isActive ? '0' : '2' }}" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                @elseif($iconType === 'barcode-scan')
                    <!-- Barcode Scan Reticle Icon (Wakamiya Brand Spec) -->
                    <svg class="w-6 h-6 {{ $isActive ? 'text-sky-500' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2M16 4h2a2 2 0 012 2v2M16 20h2a2 2 0 002-2v-2" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8" stroke-width="2" />
                        <rect x="9" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                    </svg>
                @elseif($iconType === 'academic-cap')
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>
                @elseif($iconType === 'users')
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                @else
                    <!-- Profile User Icon -->
                    <svg class="w-6 h-6" fill="{{ $isActive ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="{{ $isActive ? '0' : '2' }}" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                @endif
            </div>

            <span class="text-[11px] font-medium mt-1 tracking-tight">{{ $item['label'] }}</span>

            @if($isActive)
                <span class="absolute bottom-0 w-8 h-1 bg-sky-500 rounded-t-full"></span>
            @endif
        </a>
    @endforeach
</nav>
