@extends('layouts.app')

@section('header', 'Tambah Departemen Baru')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100">
            <div class="flex items-center gap-4">
                <a href="{{ route('departments.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900">Buat Departemen</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Lengkapi formulir di bawah ini untuk menambahkan departemen baru.</p>
                </div>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('departments.store') }}" method="POST" class="space-y-6">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Department Code -->
                    <div>
                        <label for="Department_Code" class="block text-sm font-bold text-gray-700 mb-2">Kode Departemen <span class="text-red-500">*</span></label>
                        <input type="text" name="Department_Code" id="Department_Code" value="{{ old('Department_Code') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required placeholder="Contoh: HRD">
                        @error('Department_Code') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Department Name -->
                    <div>
                        <label for="Department_Name" class="block text-sm font-bold text-gray-700 mb-2">Nama Departemen <span class="text-red-500">*</span></label>
                        <input type="text" name="Department_Name" id="Department_Name" value="{{ old('Department_Name') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required placeholder="Contoh: Human Resources">
                        @error('Department_Name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Manager Employee ID -->
                    <div>
                        <label for="Manager_Employee_ID" class="block text-sm font-bold text-gray-700 mb-2">ID Manajer (Opsional)</label>
                        <input type="text" name="Manager_Employee_ID" id="Manager_Employee_ID" value="{{ old('Manager_Employee_ID') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Contoh: EMP000001">
                        <p class="mt-1 text-xs text-gray-500">Masukkan Employee_ID dari karyawan yang menjadi manajer departemen ini.</p>
                        @error('Manager_Employee_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="Is_Active" class="block text-sm font-bold text-gray-700 mb-2">Status Aktif</label>
                        <select name="Is_Active" id="Is_Active" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            <option value="TRUE" {{ old('Is_Active') == 'TRUE' ? 'selected' : '' }}>Aktif</option>
                            <option value="FALSE" {{ old('Is_Active') == 'FALSE' ? 'selected' : '' }}>Nonaktif</option>
                        </select>
                        @error('Is_Active') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    
                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <label for="Notes" class="block text-sm font-bold text-gray-700 mb-2">Catatan</label>
                        <textarea name="Notes" id="Notes" rows="3" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Tambahkan catatan jika ada">{{ old('Notes') }}</textarea>
                        @error('Notes') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                    <a href="{{ route('departments.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Departemen</span>
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
