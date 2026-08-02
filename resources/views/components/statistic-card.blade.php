@props(['title', 'value', 'icon', 'trend' => null, 'trendType' => 'up', 'color' => 'blue'])

@php
    $colorClasses = match($color) {
        'red' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 border-red-100 dark:border-red-800',
        'orange', 'amber' => 'bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 border-orange-100 dark:border-orange-800',
        'green', 'emerald' => 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400 border-green-100 dark:border-green-800',
        'purple' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400 border-purple-100 dark:border-purple-800',
        'cyan' => 'bg-cyan-50 text-cyan-600 dark:bg-cyan-900/30 dark:text-cyan-400 border-cyan-100 dark:border-cyan-800',
        'blue', 'default' => 'bg-emerald-50 text-emerald-600 dark:bg-blue-900/30 dark:text-blue-400 border-blue-100 dark:border-blue-800',
        default => 'bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-200 dark:border-slate-700',
    };
@endphp

<div class="bg-white dark:bg-slate-900 p-5 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 flex items-start gap-4 transition-all hover:shadow-md">
    <div class="flex-shrink-0 w-12 h-12 rounded-xl border flex items-center justify-center {{ $colorClasses }}">
        @if(isset($icon))
            {{ $icon }}
        @else
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        @endif
    </div>
    <div class="flex-1 mt-1">
        <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $title }}</h4>
        <div class="mt-1 flex items-baseline gap-2">
            <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $value }}</span>
            @if($trend)
                @if($trendType === 'up' || $trendType === 'positive')
                    <span class="inline-flex items-center text-xs font-bold text-green-600 bg-green-50 px-1.5 py-0.5 rounded-md">
                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                        {{ $trend }}
                    </span>
                @elseif($trendType === 'down' || $trendType === 'negative')
                    <span class="inline-flex items-center text-xs font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded-md">
                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                        {{ $trend }}
                    </span>
                @else
                    <span class="inline-flex items-center text-xs font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded-md">
                        {{ $trend }}
                    </span>
                @endif
            @endif
        </div>
    </div>
</div>



