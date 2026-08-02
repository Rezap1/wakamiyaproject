@props(['title' => null, 'content', 'position' => 'bottom'])

@php
    $positionClasses = match($position) {
        'top' => 'bottom-full left-1/2 -translate-x-1/2 mb-2 origin-bottom',
        'bottom' => 'top-full left-1/2 -translate-x-1/2 mt-2 origin-top',
        'left' => 'right-full top-1/2 -translate-y-1/2 mr-2 origin-right',
        'right' => 'left-full top-1/2 -translate-y-1/2 ml-2 origin-left',
        default => 'top-full left-1/2 -translate-x-1/2 mt-2 origin-top',
    };
@endphp

<div class="relative inline-block" x-data="{ open: false }" @click.away="open = false">
    <div @click="open = !open" class="cursor-pointer">
        {{ $slot }}
    </div>
    
    <!-- Popover -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute z-50 w-64 bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 {{ $positionClasses }}"
         style="display: none;">
        
        @if($title)
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 rounded-t-2xl">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white">{{ $title }}</h3>
            </div>
        @endif
        
        <div class="p-4 text-sm text-slate-600 dark:text-slate-300">
            {{ $content }}
        </div>
    </div>
</div>

<!-- Note: Popover requires AlpineJS (x-data, x-show) to work properly. -->
<!-- Alternatively, here is a pure JS version if Alpine is not present: -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Alpine === 'undefined') {
            // Vanilla JS fallback for elements lacking AlpineJS
            document.querySelectorAll('.popover-trigger-vanilla').forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const popover = this.nextElementSibling;
                    popover.classList.toggle('hidden');
                    popover.classList.toggle('opacity-0');
                    popover.classList.toggle('scale-95');
                });
            });
            
            document.addEventListener('click', function(e) {
                document.querySelectorAll('.popover-content-vanilla').forEach(popover => {
                    if (!popover.contains(e.target)) {
                        popover.classList.add('hidden', 'opacity-0', 'scale-95');
                    }
                });
            });
        }
    });
</script>



