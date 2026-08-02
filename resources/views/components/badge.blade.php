@props(['color' => null, 'type' => 'soft', 'status' => null, 'dot' => false])

@php
    // Map known statuses to colors
    if ($status && !$color) {
        $statusMap = [
            'active' => 'green',
            'inactive' => 'gray',
            'pending' => 'amber',
            'approved' => 'green',
            'rejected' => 'red',
            'completed' => 'blue',
            'draft' => 'gray',
            'published' => 'blue',
            'expired' => 'red',
            'processing' => 'amber',
            'academic' => 'purple',
            'finance' => 'emerald',
            'document' => 'cyan',
            
            // Legacy / Others
            'late' => 'red',
            'archived' => 'gray',
            'progress' => 'indigo',
        ];
        $color = $statusMap[strtolower($status)] ?? 'gray';
    }
    
    $color = $color ?? 'gray';
    
    $baseClasses = 'inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold tracking-wide transition-all shadow-sm border';
    
    $colorClasses = match($color) {
        'red' => $type === 'solid' ? 'bg-red-500 text-white border-red-600' : 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:border-red-800',
        'orange', 'amber' => $type === 'solid' ? 'bg-orange-500 text-white border-orange-600' : 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/20 dark:border-orange-800',
        'green', 'emerald' => $type === 'solid' ? 'bg-green-500 text-white border-green-600' : 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/20 dark:border-green-800',
        'blue' => $type === 'solid' ? 'bg-emerald-600 text-white border-blue-700' : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-blue-900/20 dark:border-blue-800',
        'indigo' => $type === 'solid' ? 'bg-indigo-500 text-white border-indigo-600' : 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-900/20 dark:border-indigo-800',
        'purple' => $type === 'solid' ? 'bg-purple-500 text-white border-purple-600' : 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/20 dark:border-purple-800',
        'cyan' => $type === 'solid' ? 'bg-cyan-500 text-white border-cyan-600' : 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-900/20 dark:border-cyan-800',
        'gray', 'slate' => $type === 'solid' ? 'bg-slate-600 text-white border-slate-700' : 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300',
        default => $type === 'solid' ? 'bg-slate-600 text-white border-slate-700' : 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800 dark:border-slate-700',
    };
    
    $classes = "$baseClasses $colorClasses";
    
    $dotClasses = match($color) {
        'red' => 'bg-red-500',
        'orange', 'amber' => 'bg-orange-500',
        'green', 'emerald' => 'bg-green-500',
        'blue' => 'bg-emerald-500',
        'indigo' => 'bg-indigo-500',
        'purple' => 'bg-purple-500',
        'cyan' => 'bg-cyan-500',
        'gray', 'slate' => 'bg-slate-500',
        default => 'bg-slate-500'
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $type === 'solid' ? 'bg-white' : $dotClasses }}"></span>
    @endif
    {{ $status ?? $slot }}
</span>



