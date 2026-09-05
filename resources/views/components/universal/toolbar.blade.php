@props(['searchUrl' => '', 'filters' => [], 'exportUrl' => null, 'refreshUrl' => null])

<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-6 min-w-0 max-w-full">
    <form action="{{ $searchUrl }}" method="GET" class="flex min-w-0 flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
        <div class="flex-1 min-w-0 flex flex-col md:flex-row md:flex-wrap gap-4 w-full">
            <!-- Search -->
            <div class="w-full md:max-w-xs relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari..." class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block pl-10 p-2.5 transition-colors">
            </div>

            <!-- Custom Filters -->
            {{ $slot }}

            <!-- Date Range Filter -->
            <div class="flex min-w-0 items-center gap-1.5 w-full md:w-auto">
                <input type="date" name="date_from" value="{{ request('date_from') }}" 
                    class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors"
                    onchange="this.form.submit()" title="Dari tanggal">
                <span class="text-slate-400 text-xs font-bold shrink-0">—</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" 
                    class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors"
                    onchange="this.form.submit()" title="Sampai tanggal">
            </div>

            <!-- Status Filter -->
            <div class="w-full md:w-auto">
                <select name="status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif / Disetujui</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Tidak Aktif / Dibatalkan</option>
                </select>
            </div>
            
            <!-- Sort Filter -->
            <div class="w-full md:w-auto">
                <select name="sort" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="desc" {{ request('sort', 'desc') === 'desc' ? 'selected' : '' }}>Terbaru</option>
                    <option value="asc" {{ request('sort') === 'asc' ? 'selected' : '' }}>Terlama</option>
                </select>
            </div>
            
            <button type="submit" class="hidden">Cari</button>
        </div>

        <!-- Action Tools -->
        <div class="wms-action-group w-full sm:w-auto">
            @if($refreshUrl)
                <a href="{{ $refreshUrl }}" class="flex items-center justify-center p-2.5 text-slate-500 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-emerald-600 focus:ring-2 focus:ring-emerald-500 transition-colors shadow-sm" title="Segarkan">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </a>
            @endif
            
            @if($exportUrl)
                <a href="{{ $exportUrl }}" class="flex items-center justify-center p-2.5 text-slate-500 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-green-600 focus:ring-2 focus:ring-green-500 transition-colors shadow-sm" title="Ekspor Data">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                </a>
            @endif
            
            @if(request()->anyFilled(['search', 'status', 'date_from', 'date_to']) || collect(request()->all())->except(['page'])->isNotEmpty())
                <a href="{{ $searchUrl }}" class="flex items-center justify-center p-2.5 text-red-500 bg-white border border-red-200 rounded-xl hover:bg-red-50 focus:ring-2 focus:ring-red-500 transition-colors shadow-sm" title="Reset Filter">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </a>
            @endif
        </div>
    </form>
</div>



