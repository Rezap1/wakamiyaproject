@props([
    'disabled' => false, 
    'readonly' => false,
    'name' => '',
    'id' => null,
    'label' => false, 
    'required' => false,
    'helper' => false,
    'rows' => 4
])

@php
    $id = $id ?? $name;
    $hasError = $errors->has($name);
    
    $baseClasses = 'block w-full text-[13px] rounded-xl bg-white transition-all duration-200 focus:ring-2 focus:ring-offset-0 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 read-only:bg-slate-50 read-only:text-slate-500 p-3.5 shadow-sm';
    $stateClasses = $hasError 
        ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500/20 placeholder-red-300' 
        : 'border-slate-200 text-slate-800 focus:border-emerald-500 focus:ring-emerald-500/20 placeholder-slate-400';
    
    $classes = "$baseClasses $stateClasses";
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

    <div class="relative w-full">
        <textarea 
            name="{{ $name }}" 
            id="{{ $id }}"
            rows="{{ $rows }}"
            {{ $disabled ? 'disabled' : '' }} 
            {{ $readonly ? 'readonly' : '' }} 
            {{ $required ? 'required' : '' }} 
            {!! $attributes->merge(['class' => $classes]) !!}
        >{{ $slot }}</textarea>
    </div>

    @if($hasError)
        <p class="mt-1.5 text-xs font-bold text-red-500">{{ $errors->first($name) }}</p>
    @elseif($helper)
        <p class="mt-1.5 text-xs font-medium text-slate-500">{{ $helper }}</p>
    @endif
</div>



