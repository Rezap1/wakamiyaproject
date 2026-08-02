@props(['empty' => false, 'emptyTitle' => 'Data Kosong', 'emptyDescription' => 'Belum ada data yang tersedia.'])

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
    @if($empty)
        <x-universal.empty-state :title="$emptyTitle" :description="$emptyDescription" />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 whitespace-nowrap">
                <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-100">
                    <tr>
                        {{ $header ?? '' }}
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/80">
                    {{ $slot }}
                </tbody>
            </table>
        </div>
        
        @if(isset($pagination))
            {{ $pagination }}
        @endif
    @endif
</div>



