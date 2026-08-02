@props(['value', 'required' => false])

<label {{ $attributes->merge(['class' => 'block mb-1.5 text-[13px] font-bold text-slate-700']) }}>
    {{ $value ?? $slot }}
    @if($required)
        <span class="text-red-500 ml-0.5">*</span>
    @endif
</label>



