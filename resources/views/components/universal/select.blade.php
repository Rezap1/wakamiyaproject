@props(['name', 'label', 'options' => [], 'value' => '', 'required' => false, 'helper' => '', 'readonly' => false, 'placeholder' => 'Pilih salah satu...'])

<div class="mb-4">
    <label for="{{ $name }}" class="block text-[13px] font-bold text-slate-700 mb-1.5">
        {{ $label }} 
        @if($required)
            <span class="text-rose-500 font-black ml-0.5" title="Wajib diisi">*</span>
        @endif
    </label>
    
    <div class="relative">
        <select 
            name="{{ $name }}" 
            id="{{ $name }}" 
            @if($required) required @endif
            @if($readonly) disabled @endif
            class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all
                   focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500
                   @if($readonly) bg-slate-50 text-slate-500 cursor-not-allowed @endif
                   @error($name) border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror"
        >
            <option value="">{{ $placeholder }}</option>
            @foreach($options as $key => $labelOption)
                <option value="{{ $key }}" {{ old($name, $value) == $key ? 'selected' : '' }}>
                    {{ $labelOption }}
                </option>
            @endforeach
        </select>

        <!-- Hidden input to submit value if disabled -->
        @if($readonly)
            <input type="hidden" name="{{ $name }}" value="{{ old($name, $value) }}">
        @endif
    </div>

    @error($name)
        <p class="mt-1.5 text-xs font-bold text-rose-500 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ $message }}
        </p>
    @else
        @if($helper)
            <p class="mt-1.5 text-[11px] text-slate-500">{{ $helper }}</p>
        @endif
    @enderror
</div>
