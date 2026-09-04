@props(['title', 'description' => ''])

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-5 md:mb-6']) }}>
    <div class="px-4 py-3 sm:px-6 sm:py-4 border-b border-gray-100 bg-slate-50">
        <h3 class="text-sm font-bold text-slate-800 break-words">{{ $title }}</h3>
        @if($description)
            <p class="text-xs text-slate-500 mt-1 break-words">{{ $description }}</p>
        @endif
    </div>
    <div class="p-4 sm:p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
            {{ $slot }}
        </div>
    </div>
</div>



