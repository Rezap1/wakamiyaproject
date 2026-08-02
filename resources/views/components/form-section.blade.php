@props(['title', 'description' => ''])

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6']) }}>
    <div class="px-6 py-4 border-b border-gray-100 bg-slate-50">
        <h3 class="text-sm font-bold text-slate-800">{{ $title }}</h3>
        @if($description)
            <p class="text-xs text-slate-500 mt-1">{{ $description }}</p>
        @endif
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{ $slot }}
        </div>
    </div>
</div>



