@extends('layouts.app')

@section('header', 'Edit Modul')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 dark:border-slate-800">
            <div class="flex items-center gap-4">
                <a href="{{ route('modules.index') }}" class="p-2 rounded-xl text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/80 dark:bg-slate-800 dark:hover:bg-slate-800 dark:bg-slate-800 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white">Edit Modul</h2>
                    <p class="text-sm font-medium text-gray-500 dark:text-slate-400 mt-1">Perbarui informasi untuk modul <span class="font-bold">{{ $module['Module_Name'] ?? '' }}</span>.</p>
                </div>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('modules.update', $module['Module_ID']) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Module Code -->
                    <div>
                        <label for="Module_Code" class="block text-sm font-bold text-gray-700 dark:text-slate-300 mb-2">Kode Modul <span class="text-red-500">*</span></label>
                        <input type="text" name="Module_Code" id="Module_Code" value="{{ old('Module_Code', $module['Module_Code'] ?? '') }}" class="block w-full rounded-xl border-gray-300 dark:border-slate-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 dark:bg-slate-800 focus:bg-white dark:bg-slate-900 transition-colors" required placeholder="Contoh: USR_MGT">
                        @error('Module_Code') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Module Name -->
                    <div>
                        <label for="Module_Name" class="block text-sm font-bold text-gray-700 dark:text-slate-300 mb-2">Nama Modul <span class="text-red-500">*</span></label>
                        <input type="text" name="Module_Name" id="Module_Name" value="{{ old('Module_Name', $module['Module_Name'] ?? '') }}" class="block w-full rounded-xl border-gray-300 dark:border-slate-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 dark:bg-slate-800 focus:bg-white dark:bg-slate-900 transition-colors" required placeholder="Contoh: User Management">
                        @error('Module_Name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Module Group -->
                    <div>
                        <label for="Module_Group" class="block text-sm font-bold text-gray-700 dark:text-slate-300 mb-2">Grup Modul <span class="text-red-500">*</span></label>
                        <input type="text" name="Module_Group" id="Module_Group" value="{{ old('Module_Group', $module['Module_Group'] ?? '') }}" class="block w-full rounded-xl border-gray-300 dark:border-slate-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 dark:bg-slate-800 focus:bg-white dark:bg-slate-900 transition-colors" required placeholder="Contoh: Settings">
                        @error('Module_Group') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Module Order -->
                    <div>
                        <label for="Module_Order" class="block text-sm font-bold text-gray-700 dark:text-slate-300 mb-2">Urutan (Angka) <span class="text-red-500">*</span></label>
                        <input type="number" name="Module_Order" id="Module_Order" value="{{ old('Module_Order', $module['Module_Order'] ?? '1') }}" class="block w-full rounded-xl border-gray-300 dark:border-slate-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 dark:bg-slate-800 focus:bg-white dark:bg-slate-900 transition-colors" required min="0">
                        @error('Module_Order') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="Is_Active" class="block text-sm font-bold text-gray-700 dark:text-slate-300 mb-2">Status Aktif</label>
                        <select name="Is_Active" id="Is_Active" class="block w-full rounded-xl border-gray-300 dark:border-slate-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 dark:bg-slate-800 focus:bg-white dark:bg-slate-900 transition-colors">
                            <option value="TRUE" {{ old('Is_Active', $module['Is_Active'] ?? 'TRUE') == 'TRUE' ? 'selected' : '' }}>Aktif</option>
                            <option value="FALSE" {{ old('Is_Active', $module['Is_Active'] ?? 'TRUE') == 'FALSE' ? 'selected' : '' }}>Nonaktif</option>
                        </select>
                        @error('Is_Active') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    
                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <label for="Notes" class="block text-sm font-bold text-gray-700 dark:text-slate-300 mb-2">Catatan</label>
                        <textarea name="Notes" id="Notes" rows="3" class="block w-full rounded-xl border-gray-300 dark:border-slate-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 dark:bg-slate-800 focus:bg-white dark:bg-slate-900 transition-colors" placeholder="Tambahkan catatan jika ada">{{ old('Notes', $module['Notes'] ?? '') }}</textarea>
                        @error('Notes') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 dark:border-slate-800 flex justify-end gap-3">
                    <a href="{{ route('modules.index') }}" class="px-6 py-3 border border-gray-300 dark:border-slate-600 shadow-sm text-sm font-bold rounded-xl text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-800/80 dark:bg-slate-800 dark:hover:bg-slate-800 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Perubahan</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('submitText');
        const loading = document.getElementById('submitLoading');
        
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
        btn.classList.remove('hover:-translate-y-0.5');
        text.innerText = 'Menyimpan...';
        loading.classList.remove('hidden');
    });
</script>
@endsection



