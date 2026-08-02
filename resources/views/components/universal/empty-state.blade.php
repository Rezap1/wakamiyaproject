@props(['title' => 'Data Kosong', 'description' => 'Belum ada data yang ditambahkan.', 'actionText' => null, 'actionUrl' => null])

<div class="flex flex-col items-center justify-center py-16 px-4 bg-white rounded-2xl shadow-sm border border-slate-200">
    <div class="w-24 h-24 mb-6 text-slate-300">
        <!-- SVG Empty Box -->
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
        </svg>
    </div>
    <h3 class="text-lg font-bold text-slate-800 mb-2 text-center">{{ $title }}</h3>
    <p class="text-sm text-slate-500 max-w-md text-center mb-6">{{ $description }}</p>
    
    @if($actionText && $actionUrl)
        <a href="{{ $actionUrl }}" class="inline-flex items-center px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-sm focus:ring-4 focus:ring-emerald-200 transition-all">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            {{ $actionText }}
        </a>
    @endif
</div>



