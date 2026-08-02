@props(['announcements' => []])

<div class="flex justify-between items-center mb-6">
    <h4 class="text-[13px] font-bold text-slate-800 tracking-widest uppercase flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
        Pengumuman
    </h4>
    <a href="{{ Route::has('announcements.index') ? route('announcements.index') : '#' }}" class="text-[11px] text-emerald-600 font-bold hover:underline">Lihat Semua</a>
</div>
<div class="space-y-4 flex-1 overflow-y-auto custom-scrollbar pr-2">
    @forelse($announcements as $index => $announcement)
        @php
            $priority = strtolower($announcement['priority'] ?? 'normal');
            
            // Cycle colors if no specific priority colors are needed, or use priority
            $badgeColors = [
                'bg-emerald-100 text-emerald-600',
                'bg-emerald-100 text-emerald-600',
                'bg-amber-100 text-amber-600',
            ];
            $colorIndex = $index % count($badgeColors);
            
            if ($priority === 'high') {
                $badgeClass = 'bg-red-100 text-red-600';
            } elseif ($priority === 'low') {
                $badgeClass = 'bg-slate-100 text-slate-600';
            } else {
                $badgeClass = $badgeColors[$colorIndex];
            }
        @endphp
        
        <div class="{{ !$loop->last ? 'border-b border-slate-50 pb-3' : '' }}">
            <div class="flex items-center gap-2 mb-1">
                <span class="{{ $badgeClass }} text-[9px] font-extrabold px-2 py-0.5 rounded uppercase">INFO</span>
                <p class="text-[12px] font-bold text-slate-800">{{ $announcement['title'] ?? '' }}</p>
            </div>
            <p class="text-[11px] text-slate-500 ml-10">{{ $announcement['content'] ?? '' }}</p>
            <p class="text-[10px] text-slate-400 font-medium ml-10 mt-1">{{ $announcement['date'] ?? \Carbon\Carbon::now()->format('d M Y') }}</p>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center py-12 text-slate-400 h-full">
            <svg class="w-10 h-10 mb-3 text-slate-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
            <p class="text-[11px] font-bold text-slate-500">Tidak ada pengumuman.</p>
        </div>
    @endforelse
</div>



