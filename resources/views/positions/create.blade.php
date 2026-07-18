@extends('layouts.app')

@section('header', 'Tambah Posisi Baru')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white">
            <h2 class="text-xl font-extrabold text-gray-900">Formulir Pendaftaran Posisi</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Lengkapi informasi di bawah ini untuk membuat data posisi baru.</p>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('positions.store') }}" method="POST" class="space-y-6">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Kode Posisi -->
                    <div>
                        <label for="Position_Code" class="block text-sm font-bold text-gray-700">Kode Posisi <span class="text-red-500">*</span></label>
                        <div class="mt-2 relative rounded-xl shadow-sm">
                            <input type="text" name="Position_Code" id="Position_Code" value="{{ old('Position_Code') }}" 
                                class="block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Position_Code') border-red-300 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500 @enderror" 
                                placeholder="Contoh: HR-MGR">
                        </div>
                        @error('Position_Code')
                            <p class="mt-2 text-sm text-red-600 font-medium flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Nama Posisi -->
                    <div>
                        <label for="Position_Name" class="block text-sm font-bold text-gray-700">Nama Posisi <span class="text-red-500">*</span></label>
                        <div class="mt-2 relative rounded-xl shadow-sm">
                            <input type="text" name="Position_Name" id="Position_Name" value="{{ old('Position_Name') }}" 
                                class="block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Position_Name') border-red-300 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500 @enderror" 
                                placeholder="Contoh: HR Manager">
                        </div>
                        @error('Position_Name')
                            <p class="mt-2 text-sm text-red-600 font-medium flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Department ID -->
                    <div>
                        <label for="Department_ID" class="block text-sm font-bold text-gray-700">Departemen Induk <span class="text-red-500">*</span></label>
                        <div class="mt-2 relative rounded-xl shadow-sm">
                            <select name="Department_ID" id="Department_ID" 
                                class="block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Department_ID') border-red-300 text-red-900 focus:ring-red-500 focus:border-red-500 @enderror">
                                <option value="" disabled selected>Pilih Departemen...</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept['Department_ID'] }}" {{ old('Department_ID') == $dept['Department_ID'] ? 'selected' : '' }}>
                                        {{ $dept['Department_Name'] }} ({{ $dept['Department_Code'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('Department_ID')
                            <p class="mt-2 text-sm text-red-600 font-medium flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Position Level -->
                    <div>
                        <label for="Position_Level" class="block text-sm font-bold text-gray-700">Level Jabatan <span class="text-red-500">*</span></label>
                        <div class="mt-2 relative rounded-xl shadow-sm">
                            <select name="Position_Level" id="Position_Level" 
                                class="block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Position_Level') border-red-300 text-red-900 focus:ring-red-500 focus:border-red-500 @enderror">
                                <option value="" disabled selected>Pilih Level...</option>
                                <option value="Direksi" {{ old('Position_Level') == 'Direksi' ? 'selected' : '' }}>Direksi</option>
                                <option value="Manajer" {{ old('Position_Level') == 'Manajer' ? 'selected' : '' }}>Manajer</option>
                                <option value="Supervisor" {{ old('Position_Level') == 'Supervisor' ? 'selected' : '' }}>Supervisor</option>
                                <option value="Staff" {{ old('Position_Level') == 'Staff' ? 'selected' : '' }}>Staff</option>
                            </select>
                        </div>
                        @error('Position_Level')
                            <p class="mt-2 text-sm text-red-600 font-medium flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan (Opsional)</label>
                    <div class="mt-2">
                        <textarea id="Notes" name="Notes" rows="3" 
                            class="block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" 
                            placeholder="Tambahkan catatan khusus untuk posisi ini jika diperlukan...">{{ old('Notes') }}</textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                    <a href="{{ route('positions.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Posisi</span>
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
