@php
    $user = auth()->user();
    $roleName = strtoupper(trim($user->Role ?? ''));
    if (!$roleName && isset($user->Role_ID)) {
        $roleData = app(\App\Services\Core\RoleService::class)->getRoleById($user->Role_ID);
        $roleName = strtoupper(trim($roleData['Role_Name'] ?? ''));
    }

    $scannerUrl = ($roleName === 'STUDENT' || str_contains($roleName, 'STUDENT'))
        ? route('attendances.student.scanner')
        : route('hr.attendance.qr.scanner');
@endphp

@if($roleName === 'STUDENT' || str_contains($roleName, 'STUDENT'))
    <!-- Student Scan stays independent from the five equal-width nav tabs. -->
    <a id="student-scan-fab"
       href="{{ route('attendances.student.scanner') }}"
       aria-label="Scan QR Siswa"
       title="Scan QR"
       class="fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom,0px))] left-1/2 z-50 flex h-16 w-16 -translate-x-1/2 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-[0_12px_28px_rgba(2,132,199,0.42)] transition hover:scale-105 hover:shadow-[0_14px_32px_rgba(2,132,199,0.5)] active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 lg:hidden {{ request()->routeIs('attendances.student.scanner') ? 'ring-2 ring-sky-300 ring-offset-2' : '' }}">
        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2M16 4h2a2 2 0 012 2v2M16 20h2a2 2 0 002-2v-2" />
            <rect x="7" y="7" width="3.5" height="3.5" rx=".5" fill="currentColor" stroke="none" />
            <rect x="13.5" y="7" width="3.5" height="3.5" rx=".5" fill="currentColor" stroke="none" />
            <rect x="7" y="13.5" width="3.5" height="3.5" rx=".5" fill="currentColor" stroke="none" />
            <path stroke-linecap="round" d="M14 14h3v3h-3z" />
        </svg>
    </a>
@endif

<!-- FLOATING QR SCANNER QUICK ACTION BUTTON (FAB - DESKTOP ONLY) -->
<div id="floating-qr-button" class="fixed bottom-6 right-6 z-50 hidden {{ ($roleName === 'STUDENT' || str_contains($roleName, 'STUDENT')) ? 'lg:flex' : 'md:flex' }} group">
    <!-- Tooltip Banner -->
    <div class="absolute bottom-full right-0 mb-3 hidden group-hover:flex items-center gap-2 px-3.5 py-1.5 bg-slate-900/90 backdrop-blur-md text-white text-xs font-semibold rounded-full shadow-lg whitespace-nowrap border border-white/10 animate-fade-in">
        <span>📷 Absensi QR Code (Pindai)</span>
        <div class="w-2 h-2 bg-emerald-400 rounded-full animate-ping"></div>
    </div>

    <!-- Glowing Glassmorphism Button -->
    <a href="{{ $scannerUrl }}" 
       title="Absensi QR Code"
       class="relative w-16 h-16 rounded-full bg-gradient-to-tr from-purple-600/90 via-indigo-600/90 to-blue-500/90 backdrop-blur-xl border-2 border-white/40 shadow-[0_8px_32px_rgba(99,102,241,0.4)] hover:shadow-[0_12px_45px_rgba(147,51,234,0.6)] hover:scale-110 active:scale-95 transition-all duration-300 flex items-center justify-center group-hover:border-white text-white">
        
        <!-- Pulse Glow Effect -->
        <span class="absolute inset-0 rounded-full bg-indigo-500/30 animate-ping opacity-75 pointer-events-none"></span>

        <!-- Scanner Icon matching physical target frame -->
        <svg class="w-8 h-8 text-white group-hover:scale-110 transition-transform duration-300 drop-shadow-md" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <!-- Viewfinder Corner Brackets -->
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2M16 4h2a2 2 0 012 2v2M16 20h2a2 2 0 002-2v-2" />
            <!-- Center QR Code Elements -->
            <rect x="7" y="7" width="3.5" height="3.5" rx="0.5" fill="currentColor" stroke="none" />
            <rect x="13.5" y="7" width="3.5" height="3.5" rx="0.5" fill="currentColor" stroke="none" />
            <rect x="7" y="13.5" width="3.5" height="3.5" rx="0.5" fill="currentColor" stroke="none" />
            <rect x="13.5" y="13.5" width="1.8" height="1.8" fill="currentColor" stroke="none" />
            <rect x="15.2" y="15.2" width="1.8" height="1.8" fill="currentColor" stroke="none" />
        </svg>
    </a>
</div>
