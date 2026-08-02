@props([
    'disabled' => false, 
    'readonly' => false,
    'name' => '',
    'id' => null,
    'label' => false, 
    'required' => false,
    'helper' => false,
])

@php
    $id = $id ?? $name;
    $hasError = $errors->has($name);
    
    $baseClasses = 'block w-full text-[13px] rounded-xl bg-white transition-all duration-200 focus:ring-2 focus:ring-offset-0 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 pl-4 pr-10 py-2.5 shadow-sm appearance-none';
    $stateClasses = $hasError 
        ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500/20' 
        : 'border-slate-200 text-slate-800 focus:border-emerald-500 focus:ring-emerald-500/20';
    
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
        @if(isset($icon))
            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                {{ $icon }}
            </div>
        @endif

        <select 
            name="{{ $name }}" 
            id="{{ $id }}"
            {{ $disabled ? 'disabled' : '' }} 
            {{ $required ? 'required' : '' }} 
            {!! $attributes->merge(['class' => $classes . (isset($icon) ? ' pl-10' : '')]) !!}
        >
            {{ $slot }}
        </select>
        
        <!-- Custom Dropdown Arrow -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
    </div>

    @if($hasError)
        <p class="mt-1.5 text-xs font-bold text-red-500">{{ $errors->first($name) }}</p>
    @elseif($helper)
        <p class="mt-1.5 text-xs font-medium text-slate-500">{{ $helper }}</p>
    @endif
</div>



