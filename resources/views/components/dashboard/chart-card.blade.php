@props(['title', 'icon' => null])

@php
    $titleLower = strtolower($title);
    $borderColor = 'border-t-primary-500'; // Default
    
    if (str_contains($titleLower, 'student') || str_contains($titleLower, 'siswa')) {
        $borderColor = 'border-t-pink-500';
    } elseif (str_contains($titleLower, 'teacher') || str_contains($titleLower, 'guru')) {
        $borderColor = 'border-t-purple-500';
    } elseif (str_contains($titleLower, 'employee') || str_contains($titleLower, 'pegawai')) {
        $borderColor = 'border-t-indigo-500';
    } elseif (str_contains($titleLower, 'company') || str_contains($titleLower, 'perusahaan')) {
        $borderColor = 'border-t-amber-500';
    } elseif (str_contains($titleLower, 'job order') || str_contains($titleLower, 'lowongan')) {
        $borderColor = 'border-t-blue-500';
    } elseif (str_contains($titleLower, 'program') || str_contains($titleLower, 'class') || str_contains($titleLower, 'batch') || str_contains($titleLower, 'angkatan')) {
        $borderColor = 'border-t-teal-500';
    } elseif (str_contains($titleLower, 'status') || str_contains($titleLower, 'application') || str_contains($titleLower, 'aplikasi')) {
        $borderColor = 'border-t-cyan-500';
    }
@endphp

<x-card class="p-6 md:p-8 flex flex-col h-full hover:shadow-md transition-shadow duration-300 group">
    <div class="flex items-center justify-between mb-8">
        <h3 class="text-lg font-bold text-slate-800 flex items-center">
            @if($icon)
                {!! $icon !!}
            @else
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center mr-4 text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                </div>
            @endif
            {{ $title }}
        </h3>
        
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold px-2 py-1 bg-green-50 text-green-600 rounded uppercase tracking-widest border border-green-100 hidden sm:block">Live</span>
            <!-- Fullscreen Placeholder Button -->
            <button type="button" class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-slate-50 rounded-lg transition-colors border border-transparent hover:border-slate-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
            </button>
        </div>
    </div>
    <div class="relative flex-1 min-h-[300px]">
        {{ $slot }}
    </div>
</x-card>



