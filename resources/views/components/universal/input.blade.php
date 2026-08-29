@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'helper' => '', 'readonly' => false, 'placeholder' => ''])

<div class="mb-4">
    <label for="{{ $name }}" class="block text-[13px] font-bold text-slate-700 mb-1.5">
        {{ $label }} 
        @if($required)
            <span class="text-rose-500 font-black ml-0.5" title="Wajib diisi">*</span>
        @endif
    </label>
    
    <div class="relative" @if($type === 'password') x-data="{ show: false }" @endif>
        <input 
            @if($type === 'password') :type="show ? 'text' : 'password'" @else type="{{ $type }}" @endif
            name="{{ $name }}" 
            id="{{ $name }}" 
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
            @if($readonly) readonly @endif
            class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all
                   focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500
                   @if($readonly) bg-slate-50 text-slate-500 cursor-not-allowed @endif
                   @if($type === 'password') pr-10 @endif
                   @error($name) border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror"
        >
        
        @if($type === 'password')
        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-sky-600 focus:outline-none transition-colors">
            <!-- Eye Icon -->
            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            <!-- Eye Slash Icon -->
            <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.978 9.978 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
        </button>
        @endif

        @error($name)
            <div class="absolute inset-y-0 @if($type === 'password') right-8 @else right-0 @endif pr-3 flex items-center pointer-events-none">
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
