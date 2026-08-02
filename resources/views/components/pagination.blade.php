@props(['paginator' => null])

<div class="flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="hidden sm:block">
        <p class="text-[13px] text-slate-500 font-medium">
            Menampilkan <span class="font-bold text-slate-800">1</span> hingga <span class="font-bold text-slate-800">10</span> dari <span class="font-bold text-slate-800">97</span> hasil
        </p>
    </div>
    <div class="flex-1 flex justify-between sm:justify-end gap-2 w-full sm:w-auto">
        <button type="button" class="px-3 py-1.5 text-sm font-medium text-slate-500 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-slate-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            <span class="hidden sm:inline">Sebelumnya</span>
        </button>
        
        <div class="hidden sm:flex items-center gap-1">
            <button type="button" class="w-8 h-8 flex items-center justify-center text-sm font-bold text-white bg-emerald-600 border border-blue-600 rounded-lg shadow-sm">1</button>
            <button type="button" class="w-8 h-8 flex items-center justify-center text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">2</button>
            <button type="button" class="w-8 h-8 flex items-center justify-center text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">3</button>
            <span class="px-1 text-slate-400">...</span>
            <button type="button" class="w-8 h-8 flex items-center justify-center text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">10</button>
        </div>

        <button type="button" class="px-3 py-1.5 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-slate-800 shadow-sm transition-colors flex items-center gap-1">
            <span class="hidden sm:inline">Selanjutnya</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
    </div>
</div>



