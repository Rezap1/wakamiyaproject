@props([
    'title' => 'Tidak Ada Data', 
    'description' => 'Saat ini tidak ada data yang dapat ditampilkan di bagian ini.',
    'icon' => null,
    'action' => null
])

<div class="flex flex-col items-center justify-center py-16 px-6 text-center bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
    <div class="w-24 h-24 mb-6 relative">
        <div class="absolute inset-0 bg-emerald-50 dark:bg-slate-800 rounded-full opacity-50 blur-xl"></div>
        <div class="relative w-full h-full bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700 shadow-sm">
            @if($icon)
                {{ $icon }}
            @else
                <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            @endif
        </div>
    </div>
    <h3 class="text-lg font-extrabold text-slate-800 dark:text-white mb-2 tracking-tight">{{ $title }}</h3>
    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-8 max-w-md mx-auto leading-relaxed">{{ $description }}</p>
    @if($action)
        <div>
            {{ $action }}
        </div>
    @endif
</div>



