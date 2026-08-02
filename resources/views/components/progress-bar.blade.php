@props(['percent' => 0, 'label' => null, 'size' => 'md', 'color' => 'blue', 'showValue' => true])

@php
    $percent = max(0, min(100, (int)$percent));
    
    $colorClasses = match($color) {
        'red' => 'bg-red-500',
        'orange', 'amber' => 'bg-orange-500',
        'green', 'emerald' => 'bg-emerald-500',
        'purple' => 'bg-purple-500',
        'cyan' => 'bg-cyan-500',
        'blue', 'default' => 'bg-gradient-to-r from-blue-500 to-blue-600',
        default => 'bg-emerald-500',
    };
    
    $heightClass = match($size) {
        'sm' => 'h-1.5',
        'md' => 'h-2.5',
        'lg' => 'h-4',
        default => 'h-2.5',
    };
@endphp

<div class="w-full">
    @if($label || $showValue)
        <div class="flex justify-between items-end mb-1.5">
            @if($label)
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $label }}</span>
            @else
                <span></span>
            @endif
            
            @if($showValue)
                <span class="text-xs font-extrabold {{ $percent == 100 ? 'text-green-600 dark:text-green-400' : 'text-slate-600 dark:text-slate-400' }}">
                    {{ $percent }}%
                </span>
            @endif
        </div>
    @endif
    
    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden {{ $heightClass }}">
        <div class="{{ $colorClasses }} h-full rounded-full transition-all duration-500 ease-out" style="width: {{ $percent }}%"></div>
    </div>
</div>



