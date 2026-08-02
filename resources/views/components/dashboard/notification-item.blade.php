@props(['color' => 'blue', 'title', 'subtitle', 'count' => null])

@php
    $colors = match($color) {
        'red' => ['border' => 'bg-red-500', 'badge' => 'bg-red-50 text-red-600', 'icon' => 'text-red-500'],
        'orange' => ['border' => 'bg-orange-500', 'badge' => 'bg-orange-50 text-orange-600', 'icon' => 'text-orange-500'],
        'amber' => ['border' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-600', 'icon' => 'text-amber-500'],
        'emerald' => ['border' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-600', 'icon' => 'text-emerald-500'],
        'blue' => ['border' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-600', 'icon' => 'text-emerald-500'],
        'indigo' => ['border' => 'bg-indigo-500', 'badge' => 'bg-indigo-50 text-indigo-600', 'icon' => 'text-indigo-500'],
        'purple' => ['border' => 'bg-purple-500', 'badge' => 'bg-purple-50 text-purple-600', 'icon' => 'text-purple-500'],
        default => ['border' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-600', 'icon' => 'text-emerald-500'],
    };
@endphp

<div class="group flex items-start gap-4 p-4 rounded-xl border border-transparent hover:bg-slate-50 hover:border-slate-100 transition-all cursor-pointer relative overflow-hidden">
    <!-- Left Border Accent -->
    <div class="absolute left-0 top-0 bottom-0 w-1 {{ $colors['border'] }} opacity-0 group-hover:opacity-100 transition-opacity"></div>
    
    <!-- Icon/Badge -->
    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 {{ $colors['badge'] }}">
        <svg class="w-5 h-5 {{ $colors['icon'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            @if($color === 'red')
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            @elseif($color === 'orange' || $color === 'amber')
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            @else
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            @endif
        </svg>
    </div>
    
    <!-- Content -->
    <div class="flex-1 min-w-0 mt-0.5">
        <div class="flex justify-between items-start gap-2 mb-1">
            <h5 class="text-[13px] font-bold text-slate-800 truncate group-hover:text-emerald-600 transition-colors">
                {{ $title }}
            </h5>
            @if($count)
            <span class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-bold rounded-full {{ $colors['badge'] }}">
                {{ $count }}
            </span>
            @endif
        </div>
        <p class="text-[12px] text-slate-500 font-medium leading-relaxed">{{ $subtitle }}</p>
    </div>
</div>



