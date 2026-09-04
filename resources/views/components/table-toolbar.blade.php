@props(['search' => true, 'filter' => true, 'export' => true, 'refresh' => true])

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-4">
    <!-- Left side: Bulk Actions or Custom Actions -->
    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0">
        {{ $left ?? '' }}
    </div>

    <!-- Right side: Cari...ilter, Export, Refresh -->
    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
        @if($search)
            @if(isset($searchSlot))
                {{ $searchSlot }}
            @else
                <x-table-search />
            @endif
        @endif
        
        @if($filter)
            @if(isset($filterSlot))
                {{ $filterSlot }}
            @else
                <x-table-filter />
            @endif
        @endif

        @if($export)
            <button type="button" class="flex items-center justify-center p-2.5 text-slate-500 bg-white border border-slate-200 rounded-full hover:bg-slate-50 hover:text-emerald-600 focus:ring-2 focus:ring-emerald-500 transition-colors shadow-sm shrink-0" title="Ekspor Data">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            </button>
        @endif

        @if($refresh)
            <button type="button" class="flex items-center justify-center p-2.5 text-slate-500 bg-white border border-slate-200 rounded-full hover:bg-slate-50 hover:text-emerald-600 focus:ring-2 focus:ring-emerald-500 transition-colors shadow-sm shrink-0" title="Segarkan Data">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        @endif
        
        {{ $slot }}
    </div>
</div>



