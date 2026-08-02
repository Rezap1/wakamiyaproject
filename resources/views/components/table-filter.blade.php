@props(['label' => 'Filter', 'icon' => true, 'badge' => null])

<div class="relative inline-block text-left group/filter">
    <button type="button" {{ $attributes->merge(['class' => 'flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-full hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-emerald-600 dark:hover:text-blue-400 focus:z-10 focus:ring-2 focus:ring-emerald-500 transition-all shadow-sm shrink-0']) }} onclick="this.nextElementSibling.classList.toggle('hidden')">
        @if($icon)
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
        @endif
        
        <span>{{ $label }}</span>

        @if($badge)
            <span class="inline-flex items-center justify-center w-5 h-5 ml-1 text-[10px] font-bold text-white bg-emerald-600 rounded-full">{{ $badge }}</span>
        @endif
        
        <svg class="w-4 h-4 ml-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </button>

    <!-- Dropdown menu -->
    <div class="hidden absolute right-0 z-20 w-56 mt-2 origin-top-right bg-white dark:bg-slate-900 rounded-2xl shadow-lg border border-slate-200 dark:border-slate-800 focus:outline-none transition-all duration-200">
        <div class="p-3">
            {{ $slot }}
        </div>
    </div>
</div>

<script>
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.group\\/filter')) {
            document.querySelectorAll('.group\\/filter > div:not(.hidden)').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
        }
    });
</script>



