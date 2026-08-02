@props(['tabs' => [], 'activeTab' => 'informasi'])

<div class="border-b border-slate-200">
    <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
        @foreach($tabs as $key => $label)
            <a href="?tab={{ $key }}" class="whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm transition-colors {{ $activeTab === $key ? 'border-blue-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>



