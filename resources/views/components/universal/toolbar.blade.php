@props([
    'searchUrl' => '',
    'filters' => [],
    'exportUrl' => null,
    'refreshUrl' => null,
    'showDateFilter' => true,
    'showStatusFilter' => true,
    'showSortFilter' => true,
    'dateFilterLabels' => false,
    'dateAutoSubmit' => true,
    'showFilterButtons' => false,
])

<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-6 min-w-0 max-w-full">
    <form action="{{ $searchUrl }}" method="GET" class="flex min-w-0 flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
        <div class="flex-1 min-w-0 flex flex-col md:flex-row md:flex-wrap gap-4 w-full">
            <!-- Search -->
            <div class="w-full md:max-w-xs">
                @if($dateFilterLabels)<span class="mb-1 block text-xs font-bold text-slate-600">Pencarian</span>@endif
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari..." class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block pl-10 p-2.5 transition-colors">
                </div>
            </div>

            <!-- Custom Filters -->
            {{ $slot }}

            @if($showDateFilter)
            <!-- Date Range Filter -->
            <div class="grid min-w-0 grid-cols-1 gap-2 w-full sm:grid-cols-2 md:w-auto">
                <label class="min-w-0 text-xs font-bold text-slate-600">
                @if($dateFilterLabels)<span class="mb-1 block">Tanggal Mulai</span>@endif
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                    class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors"
                    @if($dateAutoSubmit) onchange="this.form.submit()" @endif title="Tanggal Mulai">
                </label>
                <label class="min-w-0 text-xs font-bold text-slate-600">
                @if($dateFilterLabels)<span class="mb-1 block">Tanggal Selesai</span>@endif
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                    class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors"
                    @if($dateAutoSubmit) onchange="this.form.submit()" @endif title="Tanggal Selesai">
                </label>
            </div>
            @endif

            <!-- Status Filter -->
            @if($showStatusFilter)<div class="w-full md:w-auto">
                <select name="status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif / Disetujui</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Tidak Aktif / Dibatalkan</option>
                </select>
            </div>@endif
            
            <!-- Sort Filter -->
            @if($showSortFilter)<div class="w-full md:w-auto">
                @if($dateFilterLabels)<label class="mb-1 block text-xs font-bold text-slate-600" for="toolbar-sort">Urutan</label>@endif
                <select id="toolbar-sort" name="sort" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="desc" {{ request('sort', 'desc') === 'desc' ? 'selected' : '' }}>Terbaru</option>
                    <option value="asc" {{ request('sort') === 'asc' ? 'selected' : '' }}>Terlama</option>
                </select>
            </div>@endif

            @if($showFilterButtons)
                <div class="flex w-full items-end gap-2 md:w-auto">
                    <button type="submit" class="min-h-11 rounded-xl bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700">Terapkan Filter</button>
                    <a href="{{ $searchUrl }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50">Reset Filter</a>
                </div>
            @endif
            
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
            
            @if(!$showFilterButtons && (request()->anyFilled(['search', 'status', 'date_from', 'date_to']) || collect(request()->all())->except(['page'])->isNotEmpty()))
                <a href="{{ $searchUrl }}" class="flex items-center justify-center p-2.5 text-red-500 bg-white border border-red-200 rounded-xl hover:bg-red-50 focus:ring-2 focus:ring-red-500 transition-colors shadow-sm" title="Reset Filter">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </a>
            @endif
        </div>
    </form>
</div>



