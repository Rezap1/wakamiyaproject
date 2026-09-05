@props(['loading' => false, 'empty' => false])

<div class="flex min-w-0 max-w-full flex-col bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
    
    @if (isset($toolbar))
        <div class="p-4 md:p-5 border-b border-slate-100 bg-white">
            {{ $toolbar }}
        </div>
    @endif

    <div class="app-table-responsive relative min-h-[200px] custom-scrollbar" role="region" aria-label="Tabel data" tabindex="0">
        @if($loading)
            <div class="absolute inset-0 z-20 flex flex-col justify-center items-center bg-white/90 backdrop-blur-sm">
                <x-loading />
            </div>
        @endif

        <table class="w-full text-left border-collapse whitespace-nowrap">
            @if(isset($header))
            <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur-sm shadow-[0_1px_0_0_#f1f5f9]">
                <tr class="text-slate-500 text-[11px] font-extrabold uppercase tracking-widest">
                    {{ $header }}
                </tr>
            </thead>
            @endif
            
            <tbody class="divide-y divide-slate-100 text-[13px] text-slate-700 font-medium">
                @if($empty && !$loading)
                    <tr>
                        <td colspan="100%">
                            @if(isset($emptyState))
                                {{ $emptyState }}
                            @else
                                <x-universal.empty-state />
                            @endif
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @if (isset($pagination))
        <div class="p-4 border-t border-slate-100 bg-slate-50">
            {{ $pagination }}
        </div>
    @endif
</div>



