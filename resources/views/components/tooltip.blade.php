@props(['text', 'position' => 'top'])

@php
    $positionClasses = match($position) {
        'top' => 'bottom-full left-1/2 -translate-x-1/2 mb-2',
        'bottom' => 'top-full left-1/2 -translate-x-1/2 mt-2',
        'left' => 'right-full top-1/2 -translate-y-1/2 mr-2',
        'right' => 'left-full top-1/2 -translate-y-1/2 ml-2',
        default => 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    };
    
    $arrowClasses = match($position) {
        'top' => 'top-full left-1/2 -translate-x-1/2 -mt-1 border-t-slate-800 dark:border-t-slate-700 border-l-transparent border-r-transparent border-b-transparent',
        'bottom' => 'bottom-full left-1/2 -translate-x-1/2 -mb-1 border-b-slate-800 dark:border-b-slate-700 border-l-transparent border-r-transparent border-t-transparent',
        'left' => 'left-full top-1/2 -translate-y-1/2 -ml-1 border-l-slate-800 dark:border-l-slate-700 border-t-transparent border-b-transparent border-r-transparent',
        'right' => 'right-full top-1/2 -translate-y-1/2 -mr-1 border-r-slate-800 dark:border-r-slate-700 border-t-transparent border-b-transparent border-l-transparent',
        default => 'top-full left-1/2 -translate-x-1/2 -mt-1 border-t-slate-800 border-l-transparent border-r-transparent border-b-transparent',
    };
@endphp

<div class="relative inline-block group">
    {{ $slot }}
    
    <!-- Tooltip -->
    <div class="absolute z-50 invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200 w-max max-w-xs {{ $positionClasses }}">
        <div class="bg-slate-800 dark:bg-slate-700 text-white text-xs font-medium py-1.5 px-3 rounded-lg shadow-lg">
            {{ $text }}
        </div>
        <!-- Arrow -->
        <div class="absolute border-4 {{ $arrowClasses }}"></div>
    </div>
</div>



