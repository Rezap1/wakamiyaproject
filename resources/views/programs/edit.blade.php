@extends('layouts.app')

@section('header', 'Perbarui Data Program')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900">Formulir Pembaruan Program</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">ID: <span class="font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded font-mono">{{ $program['Program_ID'] }}</span></p>
            </div>
            <div>
                @if(($program['Is_Active'] ?? 'TRUE') === 'TRUE')
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200">
                        Status: Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200">
                        Status: Nonaktif
                    </span>
                @endif
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('programs.update', $program['Program_ID']) }}" method="POST" class="space-y-8">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Program Code -->
                    <div>
                        <label for="Program_Code" class="block text-sm font-bold text-gray-700">Kode Program <span class="text-red-500">*</span></label>
                        <input type="text" name="Program_Code" id="Program_Code" value="{{ old('Program_Code', $program['Program_Code']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors uppercase @error('Program_Code') border-red-300 text-red-900 @enderror">
                        @error('Program_Code') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Program Name -->
                    <div class="md:col-span-2">
                        <label for="Program_Name" class="block text-sm font-bold text-gray-700">Nama Program <span class="text-red-500">*</span></label>
                        <input type="text" name="Program_Name" id="Program_Name" value="{{ old('Program_Name', $program['Program_Name']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Program_Name') border-red-300 text-red-900 @enderror">
                        @error('Program_Name') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Program Category -->
                    <div class="md:col-span-2">
                        <label for="Program_Category" class="block text-sm font-bold text-gray-700">Kategori Program <span class="text-red-500">*</span></label>
                        <select name="Program_Category" id="Program_Category" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Program_Category') border-red-300 text-red-900 @enderror">
                            <option value="Akademik" {{ old('Program_Category', $program['Program_Category']) == 'Akademik' ? 'selected' : '' }}>Akademik</option>
                            <option value="Pelatihan Bahasa" {{ old('Program_Category', $program['Program_Category']) == 'Pelatihan Bahasa' ? 'selected' : '' }}>Pelatihan Bahasa</option>
                            <option value="Sertifikasi Profesi" {{ old('Program_Category', $program['Program_Category']) == 'Sertifikasi Profesi' ? 'selected' : '' }}>Sertifikasi Profesi</option>
                            <option value="Ekstrakurikuler" {{ old('Program_Category', $program['Program_Category']) == 'Ekstrakurikuler' ? 'selected' : '' }}>Ekstrakurikuler</option>
                            <option value="Lainnya" {{ old('Program_Category', $program['Program_Category']) == 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                        @error('Program_Category') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="Description" class="block text-sm font-bold text-gray-700">Deskripsi Program</label>
                        <textarea id="Description" name="Description" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Description', $program['Description']) }}</textarea>
                    </div>
                    
                    <!-- Is Active -->
                    <div>
                        <label for="Is_Active" class="block text-sm font-bold text-gray-700">Status Aktif Sistem <span class="text-red-500">*</span></label>
                        <select name="Is_Active" id="Is_Active" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Is_Active') border-red-300 text-red-900 @enderror">
                            <option value="TRUE" {{ old('Is_Active', $program['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'selected' : '' }}>Aktif</option>
                            <option value="FALSE" {{ old('Is_Active', $program['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'selected' : '' }}>Nonaktif</option>
                        </select>
                        @error('Is_Active') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Internal</label>
                        <textarea id="Notes" name="Notes" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes', $program['Notes']) }}</textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white/80 backdrop-blur-md pb-4 rounded-b-2xl">
                    <a href="{{ route('programs.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Perbarui Program</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
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
    });
</script>
@endsection
