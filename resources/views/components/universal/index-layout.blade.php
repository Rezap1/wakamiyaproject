@props(['title', 'description', 'breadcrumbs' => [], 'addAction' => null, 'addText' => 'Tambah Data'])
@php
    $finalAddAction = $addAction ?? $attributes->get('add-action') ?? $attributes->get('add_action');
    $finalAddText = $addText ?? $attributes->get('add-text') ?? $attributes->get('add_text') ?? 'Tambah Data';
@endphp

<div class="min-w-0 max-w-full space-y-6">
    <!-- Header Section -->
    <div class="flex min-w-0 flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="min-w-0 max-w-full">
            @if(!empty($breadcrumbs))
                <x-universal.breadcrumb :links="$breadcrumbs" />
            @endif
            <h1 class="text-2xl font-black text-slate-800 tracking-tight">{!! strip_tags($title) !!}</h1>
            @if($description)
                <p class="text-sm font-medium text-slate-500 mt-1">{{ $description }}</p>
            @endif
        </div>
        
        <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto md:justify-end">
            {{ $headerActions ?? '' }}
            
            @if($finalAddAction)
                <a href="{{ $finalAddAction }}" class="inline-flex items-center px-4 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 transition-all shadow-md shadow-emerald-100">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    {{ $finalAddText }}
                </a>
            @endif
        </div>
    </div>

    <!-- Toolbar Section -->
    @if(isset($toolbar))
        {{ $toolbar }}
    @endif

    <!-- Main Content -->
    {{ $slot }}
</div>



