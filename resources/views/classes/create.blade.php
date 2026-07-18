@extends('layouts.app')

@section('header', 'Tambah Kelas Baru')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white">
            <h2 class="text-xl font-extrabold text-gray-900">Formulir Pendaftaran Kelas (Rombel)</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Buat ruang kelas baru dan tautkan ke Program, Angkatan, serta Wali Kelas.</p>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('classes.store') }}" method="POST" class="space-y-8">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Class Code -->
                    <div>
                        <label for="Class_Code" class="block text-sm font-bold text-gray-700">Kode Kelas <span class="text-red-500">*</span></label>
                        <input type="text" name="Class_Code" id="Class_Code" value="{{ old('Class_Code') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors uppercase @error('Class_Code') border-red-300 text-red-900 @enderror" placeholder="Contoh: KLS-A, KLS-JP-01">
                        @error('Class_Code') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Class Name -->
                    <div>
                        <label for="Class_Name" class="block text-sm font-bold text-gray-700">Nama Kelas <span class="text-red-500">*</span></label>
                        <input type="text" name="Class_Name" id="Class_Name" value="{{ old('Class_Name') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Class_Name') border-red-300 text-red-900 @enderror" placeholder="Contoh: Kelas Pagi A">
                        @error('Class_Name') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Program ID -->
                    <div>
                        <label for="Program_ID" class="block text-sm font-bold text-gray-700">Tautkan ke Program <span class="text-red-500">*</span></label>
                        <select name="Program_ID" id="Program_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Program_ID') border-red-300 text-red-900 @enderror">
                            <option value="" disabled selected>Pilih Program...</option>
                            @foreach($programs as $program)
                                <option value="{{ $program['Program_ID'] }}" {{ old('Program_ID') == $program['Program_ID'] ? 'selected' : '' }}>
                                    {{ $program['Program_Code'] }} - {{ $program['Program_Name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('Program_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Batch ID -->
                    <div>
                        <label for="Batch_ID" class="block text-sm font-bold text-gray-700">Tautkan ke Angkatan <span class="text-red-500">*</span></label>
                        <select name="Batch_ID" id="Batch_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed @error('Batch_ID') border-red-300 text-red-900 @enderror">
                            <option value="" disabled selected>Pilih Program terlebih dahulu...</option>
                            @foreach($batches as $batch)
                                <option value="{{ $batch['Batch_ID'] }}" data-program="{{ $batch['Program_ID'] }}" class="hidden" {{ old('Batch_ID') == $batch['Batch_ID'] ? 'selected' : '' }}>
                                    {{ $batch['Batch_Code'] }} - {{ $batch['Batch_Name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('Batch_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[11px] text-gray-400 font-medium">*Daftar angkatan akan disaring berdasarkan program terpilih.</p>
                    </div>

                    <!-- Homeroom Teacher -->
                    <div class="md:col-span-2">
                        <label for="Homeroom_Teacher_ID" class="block text-sm font-bold text-gray-700">Wali Kelas <span class="text-red-500">*</span></label>
                        <select name="Homeroom_Teacher_ID" id="Homeroom_Teacher_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Homeroom_Teacher_ID') border-red-300 text-red-900 @enderror">
                            <option value="" disabled selected>Pilih Wali Kelas...</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher['Teacher_ID'] }}" {{ old('Homeroom_Teacher_ID') == $teacher['Teacher_ID'] ? 'selected' : '' }}>
                                    {{ $teacher['Teacher_Code'] }} - {{ $teacher['Full_Name'] }} ({{ $teacher['Specialization'] }})
                                </option>
                            @endforeach
                        </select>
                        @error('Homeroom_Teacher_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Capacity -->
                    <div>
                        <label for="Capacity" class="block text-sm font-bold text-gray-700">Kapasitas Maksimal <span class="text-red-500">*</span></label>
                        <div class="mt-2 relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                            <input type="number" name="Capacity" id="Capacity" min="1" value="{{ old('Capacity', 20) }}" class="block w-full pl-11 rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-3 bg-gray-50 focus:bg-white transition-colors @error('Capacity') border-red-300 text-red-900 @enderror" placeholder="20">
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm font-medium">Siswa</span>
                            </div>
                        </div>
                        @error('Capacity') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Current Student -->
                    <div>
                        <label for="Current_Student" class="block text-sm font-bold text-gray-700">Jumlah Siswa Saat Ini</label>
                        <div class="mt-2 relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <input type="number" name="Current_Student" id="Current_Student" min="0" value="{{ old('Current_Student', 0) }}" class="block w-full pl-11 rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm py-3 bg-gray-50 focus:bg-white transition-colors @error('Current_Student') border-red-300 text-red-900 @enderror" placeholder="0">
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm font-medium">Siswa</span>
                            </div>
                        </div>
                        @error('Current_Student') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Class Status -->
                    <div class="md:col-span-2">
                        <label for="Class_Status" class="block text-sm font-bold text-gray-700">Status Kelas</label>
                        <select name="Class_Status" id="Class_Status" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Class_Status') border-red-300 text-red-900 @enderror">
                            <option value="Persiapan" {{ old('Class_Status') == 'Persiapan' ? 'selected' : '' }}>Persiapan</option>
                            <option value="Aktif" {{ old('Class_Status', 'Aktif') == 'Aktif' ? 'selected' : '' }}>Aktif Berjalan</option>
                            <option value="Penuh" {{ old('Class_Status') == 'Penuh' ? 'selected' : '' }}>Penuh</option>
                            <option value="Selesai" {{ old('Class_Status') == 'Selesai' ? 'selected' : '' }}>Selesai (Lulus)</option>
                            <option value="Ditutup" {{ old('Class_Status') == 'Ditutup' ? 'selected' : '' }}>Ditutup / Nonaktif</option>
                        </select>
                        @error('Class_Status') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="Description" class="block text-sm font-bold text-gray-700">Deskripsi / Ruangan</label>
                        <textarea id="Description" name="Description" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Informasi tambahan, letak ruang kelas, atau jadwal...">{{ old('Description') }}</textarea>
                    </div>

                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Internal</label>
                        <textarea id="Notes" name="Notes" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes') }}</textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white/80 backdrop-blur-md pb-4 rounded-b-2xl">
                    <a href="{{ route('classes.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Kelas</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Loading Button Logic
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            const text = document.getElementById('submitText');
            const loading = document.getElementById('submitLoading');
            
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.classList.remove('hover:-translate-y-0.5');
            text.innerText = 'Memproses...';
            loading.classList.remove('hidden');
        });
        
        // Chained Dropdown Logic
        const programSelect = document.getElementById('Program_ID');
        const batchSelect = document.getElementById('Batch_ID');
        const batchOptions = batchSelect.querySelectorAll('option[data-program]');
        const oldBatchId = "{{ old('Batch_ID') }}";

        function filterBatches() {
            const selectedProgramId = programSelect.value;
            let hasValidOption = false;

            batchOptions.forEach(option => {
                if (option.getAttribute('data-program') === selectedProgramId) {
                    option.classList.remove('hidden');
                    option.disabled = false;
                    hasValidOption = true;
                } else {
                    option.classList.add('hidden');
                    option.disabled = true;
                }
            });

            if (selectedProgramId) {
                batchSelect.disabled = false;
                
                // If the previously selected batch is not valid for this program, reset it
                const selectedOption = batchSelect.options[batchSelect.selectedIndex];
                if (selectedOption.disabled && selectedOption.value !== "") {
                    batchSelect.value = "";
                }
            } else {
                batchSelect.disabled = true;
                batchSelect.value = "";
            }
        }

        programSelect.addEventListener('change', filterBatches);
        
        // Initial run in case of old() validation errors
        if (programSelect.value) {
            filterBatches();
            if(oldBatchId) {
                batchSelect.value = oldBatchId;
            }
        }
    });
</script>
@endsection
