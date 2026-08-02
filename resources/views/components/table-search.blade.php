@props(['placeholder' => 'Cari data...', 'name' => 'search'])

<div class="relative w-full md:w-72 shrink-0 group">
    <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none transition-colors group-focus-within:text-emerald-500">
        <svg class="w-4 h-4 text-slate-400 group-focus-within:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
    </div>
    <input type="text" name="{{ $name }}"
        {{ $attributes->merge(['class' => 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white text-sm rounded-full focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 block w-full pl-10 pr-4 py-2.5 transition-all shadow-sm hover:border-slate-300 dark:hover:border-slate-600 placeholder-slate-400 dark:placeholder-slate-500 focus:shadow-md']) }} 
        placeholder="{{ $placeholder }}"
        value="{{ request($name) }}">
</div>



