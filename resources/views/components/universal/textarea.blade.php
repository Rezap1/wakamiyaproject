@props(['name', 'label', 'value' => '', 'required' => false, 'helper' => '', 'readonly' => false, 'placeholder' => '', 'rows' => 3])

<div class="mb-4">
    <label for="{{ $name }}" class="block text-[13px] font-bold text-slate-700 mb-1.5">
        {{ $label }} 
        @if($required)
            <span class="text-rose-500 font-black ml-0.5" title="Wajib diisi">*</span>
        @endif
    </label>
    
    <div class="relative">
        <textarea 
            name="{{ $name }}" 
            id="{{ $name }}" 
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
            @if($readonly) readonly @endif
            class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all
                   focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500
                   @if($readonly) bg-slate-50 text-slate-500 cursor-not-allowed @endif
                   @error($name) border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror"
        >{{ old($name, $value) }}</textarea>
        
        @error($name)
            <div class="absolute top-2 right-0 pr-3 flex items-start pointer-events-none">
                <svg class="h-5 w-5 text-rose-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>
        @enderror
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
