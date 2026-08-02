@props(['activities' => []])

<h4 class="text-[13px] font-bold text-slate-800 tracking-widest uppercase flex items-center gap-2 mb-6">
    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
    Aktivitas Terbaru
</h4>
<div class="space-y-4 flex-1 overflow-y-auto custom-scrollbar pr-2">
    @forelse($activities as $index => $log)
        @php
            $actionType = $log['Action'] ?? '';
            if ($actionType === 'CREATE') {
                $iconBg = 'bg-purple-100 text-purple-600';
                $icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>';
                $actionTitle = 'Data baru ditambahkan';
            } elseif ($actionType === 'UPDATE') {
                $iconBg = 'bg-emerald-100 text-emerald-600';
                $icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                $actionTitle = 'Pembaruan data';
            } elseif ($actionType === 'DELETE') {
                $iconBg = 'bg-red-100 text-red-600';
                $icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
                $actionTitle = 'Data dihapus';
            } else {
                $iconBg = 'bg-emerald-100 text-emerald-600';
                $icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                $actionTitle = 'Aktivitas sistem';
            }
        @endphp
        
        <div class="flex gap-4">
            <div class="w-8 h-8 rounded-full {{ $iconBg }} flex items-center justify-center shrink-0">
                {!! $icon !!}
            </div>
            <div class="flex-1 {{ !$loop->last ? 'border-b border-slate-50 pb-4' : '' }}">
                <div class="flex justify-between items-start">
                    <p class="text-[12px] font-bold text-slate-800 leading-tight">
                        {{ $actionTitle }} ({{ $log['Module'] ?? '-' }})
                    </p>
                    <p class="text-[10px] text-slate-400 font-medium whitespace-nowrap ml-2">{{ \Carbon\Carbon::parse($log['Created_At'])->diffForHumans(null, true) }} yang lalu</p>
                </div>
                <p class="text-[11px] text-slate-500 mt-0.5 truncate max-w-xs">{{ $log['Description'] ?? 'Oleh: ' . ($log['User_ID'] ?? 'SYSTEM') }}</p>
            </div>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center py-12 text-slate-400 h-full">
            <svg class="w-10 h-10 mb-3 text-slate-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            <p class="text-[11px] font-bold text-slate-500">Tidak ada riwayat aktivitas.</p>
        </div>
    @endforelse
</div>



