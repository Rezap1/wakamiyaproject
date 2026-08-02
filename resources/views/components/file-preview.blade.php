@props(['name', 'size' => null, 'type' => 'document', 'url' => '#', 'date' => null])

@php
    $iconClasses = match($type) {
        'pdf' => 'text-red-500 bg-red-50 dark:bg-red-900/30',
        'image', 'jpg', 'png' => 'text-emerald-500 bg-emerald-50 dark:bg-blue-900/30',
        'excel', 'csv', 'spreadsheet' => 'text-emerald-500 bg-emerald-50 dark:bg-emerald-900/30',
        'word', 'doc', 'docx' => 'text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30',
        default => 'text-slate-500 bg-slate-50 dark:bg-slate-800',
    };
@endphp

<a href="{{ $url }}" target="_blank" class="group flex items-start p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md hover:border-blue-300 dark:hover:border-blue-700 transition-all">
    <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center {{ $iconClasses }} transition-transform group-hover:scale-105">
        @if(in_array($type, ['image', 'jpg', 'png']))
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        @elseif(in_array($type, ['excel', 'csv', 'spreadsheet']))
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
        @elseif(in_array($type, ['pdf']))
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
        @else
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        @endif
    </div>
    
    <div class="ml-4 flex-1 min-w-0">
        <h4 class="text-sm font-bold text-slate-800 dark:text-white truncate group-hover:text-emerald-600 transition-colors">{{ $name }}</h4>
        <div class="mt-1 flex items-center gap-3">
            @if($size)
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $size }}</span>
            @endif
            @if($date)
                <span class="text-xs font-medium text-slate-400 flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ $date }}
                </span>
            @endif
        </div>
    </div>
    
    <div class="ml-2 flex-shrink-0 text-slate-300 dark:text-slate-600 group-hover:text-emerald-500 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
    </div>
</a>



