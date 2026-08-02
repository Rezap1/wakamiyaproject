@props(['name', 'src' => null, 'size' => 'md', 'type' => 'rounded', 'status' => null])

@php
    $sizeClasses = [
        'xs' => 'w-6 h-6 text-[10px]',
        'sm' => 'w-8 h-8 text-xs',
        'md' => 'w-10 h-10 text-sm',
        'lg' => 'w-12 h-12 text-base',
        'xl' => 'w-16 h-16 text-xl',
        '2xl' => 'w-24 h-24 text-3xl',
    ];

    $roundedClass = $type === 'circle' ? 'rounded-full' : 'rounded-xl';
    
    // Generate color based on name (simple hash)
    $colors = ['bg-emerald-100 text-emerald-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-orange-100 text-orange-700', 'bg-pink-100 text-pink-700', 'bg-indigo-100 text-indigo-700'];
    $colorIndex = abs(crc32($name)) % count($colors);
    $colorClass = $colors[$colorIndex];
    
    $initials = collect(explode(' ', $name))->map(function($segment) {
        return strtoupper(substr($segment, 0, 1));
    })->take(2)->join('');
@endphp

<div class="relative inline-block">
    @if($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="{{ $sizeClasses[$size] }} {{ $roundedClass }} object-cover border-2 border-white dark:border-slate-800 shadow-sm">
    @else
        <div class="{{ $sizeClasses[$size] }} {{ $roundedClass }} {{ $colorClass }} font-extrabold flex items-center justify-center border-2 border-white dark:border-slate-800 shadow-sm">
            {{ $initials }}
        </div>
    @endif
    
    @if($status)
        @php
            $statusColor = match($status) {
                'online', 'active' => 'bg-green-500',
                'offline', 'inactive' => 'bg-slate-400',
                'busy', 'dnd' => 'bg-red-500',
                'away' => 'bg-amber-500',
                default => 'bg-slate-400',
            };
            
            $statusSize = match($size) {
                'xs', 'sm' => 'w-2 h-2',
                'md' => 'w-2.5 h-2.5',
                'lg' => 'w-3 h-3',
                'xl', '2xl' => 'w-4 h-4 border-2',
            };
            
            $statusPosition = $type === 'circle' ? 'bottom-0 right-0' : '-bottom-1 -right-1';
        @endphp
        <span class="absolute {{ $statusPosition }} block {{ $statusSize }} {{ $statusColor }} rounded-full ring-2 ring-white dark:ring-slate-900"></span>
    @endif
</div>



