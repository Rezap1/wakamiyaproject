@props([
    'disabled' => false, 
    'readonly' => false,
    'type' => 'text', 
    'name' => '',
    'id' => null,
    'label' => false, 
    'required' => false,
    'helper' => false,
    'prefix' => false,
    'suffix' => false,
])

@php
    $id = $id ?? $name;
    $hasError = $errors->has($name);
    
    // Classes for the actual input element
    $baseClasses = 'block w-full text-[13px] bg-white transition-all duration-200 focus:ring-2 focus:ring-offset-0 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 read-only:bg-slate-50 read-only:text-slate-500';
    $stateClasses = $hasError 
        ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500/20 placeholder-red-300' 
        : 'border-slate-200 text-slate-800 focus:border-emerald-500 focus:ring-emerald-500/20 placeholder-slate-400';
    
    // Rounded corners logic depending on prefix/suffix
    $roundedClasses = 'rounded-xl';
    if ($prefix) $roundedClasses = 'rounded-r-xl rounded-l-none border-l-0';
    if ($suffix) $roundedClasses = 'rounded-l-xl rounded-r-none border-r-0';
    if ($prefix && $suffix) $roundedClasses = 'rounded-none border-l-0 border-r-0';

    // Padding for icons
    $pl = isset($icon) ? 'pl-10' : 'pl-4';
    $pr = isset($iconRight) ? 'pr-10' : 'pr-4';

    $classes = "$baseClasses $stateClasses $roundedClasses $pl $pr py-2.5 shadow-sm";
@endphp

<div class="w-full">
    @if($label)
        <label for="{{ $id }}" class="block mb-1.5 text-[13px] font-bold text-slate-700">
            {{ $label }}
            @if($required)
                <span class="text-red-500 ml-0.5">*</span>
            @endif
        </label>
    @endif

    <div class="relative flex">
        @if($prefix)
            <span class="inline-flex items-center px-4 text-[13px] font-bold text-slate-500 bg-slate-50 border border-r-0 border-slate-200 rounded-l-xl">
                {{ $prefix }}
            </span>
        @endif

        <div class="relative w-full flex-1 min-w-0">
            @if(isset($icon))
                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                    {{ $icon }}
                </div>
            @endif

            <input 
                type="{{ $type }}" 
                name="{{ $name }}" 
                id="{{ $id }}"
                {{ $disabled ? 'disabled' : '' }} 
                {{ $readonly ? 'readonly' : '' }} 
                {{ $required ? 'required' : '' }} 
                {!! $attributes->merge(['class' => $classes]) !!}
            >

            @if(isset($iconRight))
                <div class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none text-slate-400">
                    {{ $iconRight }}
                </div>
            @endif
        </div>

        @if($suffix)
            <span class="inline-flex items-center px-4 text-[13px] font-bold text-slate-500 bg-slate-50 border border-l-0 border-slate-200 rounded-r-xl">
                {{ $suffix }}
            </span>
        @endif
    </div>

    @if($hasError)
        <p class="mt-1.5 text-xs font-bold text-red-500">{{ $errors->first($name) }}</p>
    @elseif($helper)
        <p class="mt-1.5 text-xs font-medium text-slate-500">{{ $helper }}</p>
    @endif
</div>



