@props([
    'type' => 'default', // default, table, card, profile, list
    'rows' => 3
])

@if($type === 'table')
    <div class="animate-pulse space-y-4">
        <div class="h-10 bg-gray-200 dark:bg-slate-700 rounded-xl w-full"></div>
        @for($i = 0; $i < $rows; $i++)
            <div class="flex space-x-4">
                <div class="h-12 bg-gray-100 dark:bg-slate-800 rounded-lg w-full"></div>
            </div>
        @endfor
    </div>
@elseif($type === 'card')
    <div class="animate-pulse bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 p-6 space-y-4 w-full">
        <div class="h-4 bg-gray-200 dark:bg-slate-700 rounded w-1/3"></div>
        <div class="space-y-2 pt-4">
            <div class="h-3 bg-gray-100 dark:bg-slate-800 rounded w-full"></div>
            <div class="h-3 bg-gray-100 dark:bg-slate-800 rounded w-5/6"></div>
            <div class="h-3 bg-gray-100 dark:bg-slate-800 rounded w-4/6"></div>
        </div>
    </div>
@elseif($type === 'profile')
    <div class="animate-pulse flex items-center space-x-4 w-full">
        <div class="rounded-full bg-gray-200 dark:bg-slate-700 h-16 w-16"></div>
        <div class="flex-1 space-y-2 py-1">
            <div class="h-4 bg-gray-200 dark:bg-slate-700 rounded w-1/3"></div>
            <div class="h-3 bg-gray-100 dark:bg-slate-800 rounded w-1/4"></div>
        </div>
    </div>
@elseif($type === 'list')
    <div class="animate-pulse space-y-3 w-full">
        @for($i = 0; $i < $rows; $i++)
            <div class="h-16 bg-gray-100 dark:bg-slate-800 rounded-xl w-full"></div>
        @endfor
    </div>
@else
    <div class="animate-pulse flex space-x-4 w-full">
        <div class="flex-1 space-y-4 py-1">
            <div class="h-4 bg-gray-200 dark:bg-slate-700 rounded w-3/4"></div>
            <div class="space-y-2">
                <div class="h-3 bg-gray-100 dark:bg-slate-800 rounded"></div>
                <div class="h-3 bg-gray-100 dark:bg-slate-800 rounded w-5/6"></div>
            </div>
        </div>
    </div>
@endif



