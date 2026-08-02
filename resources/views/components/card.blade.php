<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl shadow-sm border border-slate-200 p-6 transition-all duration-200']) }}>
    @if(isset($title))
        <div class="mb-4">
            <h3 class="text-lg font-bold text-slate-800">{{ $title }}</h3>
            @if(isset($description))
                <p class="text-sm text-slate-500 mt-1">{{ $description }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>



