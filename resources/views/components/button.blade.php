@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'loading' => false])

@php
    $baseClasses = 'relative inline-flex items-center justify-center font-bold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-70 disabled:cursor-wait group overflow-hidden min-h-[44px] md:min-h-0';
    
    $variantClasses = [
        'primary' => 'bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white shadow-md shadow-blue-600/20 focus:ring-blue-600 border border-transparent dark:focus:ring-offset-slate-900',
        'secondary' => 'bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 shadow-sm focus:ring-slate-200 dark:focus:ring-slate-600',
        'success' => 'bg-green-600 hover:bg-green-700 text-white shadow-sm shadow-green-600/20 focus:ring-green-600 border border-transparent dark:focus:ring-offset-slate-900',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white shadow-sm shadow-red-600/20 focus:ring-red-600 border border-transparent dark:focus:ring-offset-slate-900',
        'warning' => 'bg-orange-500 hover:bg-orange-600 text-white shadow-sm shadow-orange-500/20 focus:ring-orange-500 border border-transparent dark:focus:ring-offset-slate-900',
        'outline' => 'bg-transparent border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 focus:ring-slate-200 shadow-sm',
        'ghost' => 'bg-transparent hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 border border-transparent focus:ring-slate-200',
    ];
    
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs rounded-lg gap-1.5',
        'md' => 'px-4 py-2.5 text-[13px] rounded-xl gap-2',
        'lg' => 'px-6 py-3 text-[15px] rounded-2xl gap-2.5',
        'icon' => 'p-2 rounded-xl flex items-center justify-center',
    ];

    $classes = $baseClasses . ' ' . ($variantClasses[$variant] ?? $variantClasses['primary']) . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md']);
@endphp

@if($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} 
        @if($type === 'submit') onclick="if(!this.form || this.form.checkValidity()) { this.classList.add('is-loading'); setTimeout(() => this.disabled = true, 10); }" @endif>
        
        <!-- Loading Spinner -->
        <span class="absolute inset-0 flex items-center justify-center opacity-0 group-[.is-loading]:opacity-100 transition-opacity">
            <svg class="animate-spin h-5 w-5 {{ in_array($variant, ['secondary', 'outline', 'ghost']) ? 'text-slate-600 dark:text-slate-400' : 'text-white' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </span>

        <!-- Original Content -->
        <span class="flex items-center gap-[inherit] transition-opacity group-[.is-loading]:opacity-0">
            {{ $slot }}
        </span>
    </button>
@endif



