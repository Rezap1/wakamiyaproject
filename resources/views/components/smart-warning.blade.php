@props(['message', 'type' => 'warning'])

@php
    $colors = [
        'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
        'info' => 'bg-emerald-50 text-blue-800 border-emerald-200',
    ];
    $icons = [
        'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        'error' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    
    $colorClass = $colors[$type] ?? $colors['warning'];
    $iconPath = $icons[$type] ?? $icons['warning'];
@endphp

<div {{ $attributes->merge(['class' => "flex items-center p-4 mb-4 border rounded-xl shadow-sm {$colorClass}"]) }}>
    <svg class="w-6 h-6 shrink-0 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"></path>
    </svg>
    <div class="text-[14px] font-medium flex-1">
        {!! $message !!}
    </div>
</div>



