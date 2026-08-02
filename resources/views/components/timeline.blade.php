@props(['items' => []])

<div class="relative space-y-4 before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 dark:before:via-slate-700 before:to-transparent">
    @forelse($items as $index => $item)
        @php
            $type = $item['type'] ?? 'info';
            $dotColor = match($type) {
                'success' => 'bg-green-500 border-green-200',
                'warning' => 'bg-orange-500 border-orange-200',
                'danger' => 'bg-red-500 border-red-200',
                'primary' => 'bg-emerald-500 border-emerald-200',
                default => 'bg-slate-400 border-slate-200 dark:border-slate-600',
            };
        @endphp
        
        <div class="relative flex items-start justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
            <!-- Icon / Dot -->
            <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 shadow-sm shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 {{ $dotColor }} z-10 transition-transform group-hover:scale-110">
                @if(isset($item['icon']))
                    <span class="text-white w-4 h-4">{!! $item['icon'] !!}</span>
                @else
                    <span class="w-2.5 h-2.5 rounded-full bg-white"></span>
                @endif
            </div>

            <!-- Content Card -->
            <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-1">
                    <h4 class="font-bold text-slate-800 dark:text-white text-sm">{{ $item['title'] }}</h4>
                    <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-800 px-2 py-0.5 rounded-md">{{ $item['time'] ?? '' }}</span>
                </div>
                <p class="text-[13px] font-medium text-slate-500 dark:text-slate-400 leading-relaxed">{{ $item['description'] }}</p>
                
                @if(isset($item['meta']))
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
                        {!! $item['meta'] !!}
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center py-6">
            <p class="text-sm text-slate-500 dark:text-slate-400 italic">Tidak ada riwayat aktivitas.</p>
        </div>
    @endforelse
</div>



