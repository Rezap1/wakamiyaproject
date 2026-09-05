@props(['name', 'label', 'options' => [], 'value' => '', 'required' => false, 'helper' => '', 'readonly' => false, 'placeholder' => 'Pilih salah satu...'])

@php
    $oldValue = old($name, $value);
    // Convert options array to JSON for Alpine
    $alpineOptions = [];
    $selectedLabel = $placeholder;
    foreach($options as $key => $labelOption) {
        $alpineOptions[] = [
            'value' => (string)$key,
            'label' => $labelOption
        ];
        if ((string)$key === (string)$oldValue) {
            $selectedLabel = $labelOption;
        }
    }
@endphp

<div class="mb-4" 
    x-data="{
        open: false,
        search: '',
        value: '{{ addslashes($oldValue) }}',
        selectedLabel: '{{ addslashes($selectedLabel) }}',
        options: {{ json_encode($alpineOptions) }},
        get filteredOptions() {
            if (this.search === '') {
                return this.options;
            }
            return this.options.filter(opt => opt.label.toLowerCase().includes(this.search.toLowerCase()));
        },
        selectOption(val, label) {
            this.value = val;
            this.selectedLabel = label;
            this.open = false;
            this.search = '';
            this.$nextTick(() => {
                this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
    }"
    @click.away="open = false"
>
    <label for="{{ $name }}-trigger" class="block text-[13px] font-bold text-slate-700 mb-1.5">
        {{ $label }} 
        @if($required)
            <span class="text-rose-500 font-black ml-0.5" title="Wajib diisi">*</span>
        @endif
    </label>
    
    <div class="relative">
        <input type="hidden" id="{{ $name }}" name="{{ $name }}" x-model="value" x-ref="hiddenInput" @if($required) x-bind:required="!value" @endif>
        
        <button 
            id="{{ $name }}-trigger"
            type="button" 
            @click="!{{ $readonly ? 'true' : 'false' }} && (open = !open)"
            @keydown.escape="open = false"
            aria-haspopup="listbox"
            :aria-expanded="open.toString()"
            aria-controls="{{ $name }}-options"
            class="w-full flex justify-between items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500
                   @if($readonly) bg-slate-50 text-slate-500 cursor-not-allowed @endif
                   @error($name) border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror"
        >
            <span x-text="selectedLabel" :class="{'text-slate-400': !value, 'text-slate-900': value}" class="truncate block text-left w-full h-[20px]"></span>
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>

        <div x-show="open" 
             x-transition.opacity
             class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden"
             style="display: none;"
        >
            <div class="p-2 border-b border-slate-100">
                <div class="relative">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" x-model="search" class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:bg-white focus:ring-0 transition-colors" placeholder="Cari nama atau role..." @keydown.escape="open = false">
                </div>
            </div>
            <ul id="{{ $name }}-options" role="listbox" class="max-h-60 overflow-y-auto p-1" style="scrollbar-width: thin;">
                <template x-for="option in filteredOptions" :key="option.value">
                    <li @click="selectOption(option.value, option.label)" role="option" :aria-selected="(value === option.value).toString()"
                        class="px-3 py-2 cursor-pointer rounded-lg text-sm transition-colors hover:bg-blue-50 hover:text-blue-700"
                        :class="{'bg-blue-50 text-blue-700 font-bold': value === option.value, 'text-slate-700': value !== option.value}"
                    >
                        <span x-text="option.label"></span>
                    </li>
                </template>
                <li x-show="filteredOptions.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                    Tidak ditemukan
                </li>
            </ul>
        </div>
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
