@props(['url', 'title', 'isPrimary' => false])

@php
    $bgClass = $isPrimary 
        ? 'bg-primary-500 hover:bg-primary-600 text-white shadow-primary-500/30 border-primary-500' 
        : 'bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-primary-600 dark:text-primary-400 border-slate-100 dark:border-slate-700';
@endphp

<a href="{{ $url }}" class="inline-flex items-center px-5 py-2 rounded-full border shadow-sm text-sm font-bold {{ $bgClass }} transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md">
    {{ $title }}
</a>



