@extends('layouts.app')

@section('header', 'Perbarui Data Angkatan')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900">Formulir Pembaruan Angkatan</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">ID: <span class="font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded font-mono">{{ $batch['Batch_ID'] }}</span></p>
            </div>
            <div>
                @if(($batch['Is_Active'] ?? 'TRUE') === 'TRUE')
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200">
                        Sistem Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200">
                        Sistem Nonaktif
                    </span>
                @endif
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('batches.update', $batch['Batch_ID']) }}" method="POST" class="space-y-8">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Batch Code -->
                    <div>
                        <label for="Batch_Code" class="block text-sm font-bold text-gray-700">Kode Angkatan <span class="text-red-500">*</span></label>
                        <input type="text" name="Batch_Code" id="Batch_Code" value="{{ old('Batch_Code', $batch['Batch_Code']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors uppercase @error('Batch_Code') border-red-300 text-red-900 @enderror">
                        @error('Batch_Code') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Batch Name -->
                    <div>
                        <label for="Batch_Name" class="block text-sm font-bold text-gray-700">Nama Angkatan <span class="text-red-500">*</span></label>
                        <input type="text" name="Batch_Name" id="Batch_Name" value="{{ old('Batch_Name', $batch['Batch_Name']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Batch_Name') border-red-300 text-red-900 @enderror">
                        @error('Batch_Name') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Program ID -->
                    <div class="md:col-span-2">
                        <label for="Program_ID" class="block text-sm font-bold text-gray-700">Tautkan ke Program <span class="text-red-500">*</span></label>
                        <select name="Program_ID" id="Program_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Program_ID') border-red-300 text-red-900 @enderror">
                            <option value="" disabled>Pilih Program / Mata Diklat...</option>
                            @foreach($programs as $program)
                                <option value="{{ $program['Program_ID'] }}" {{ old('Program_ID', $batch['Program_ID']) == $program['Program_ID'] ? 'selected' : '' }}>
                                    {{ $program['Program_Code'] }} - {{ $program['Program_Name'] }} {{ ($program['Is_Active'] ?? 'TRUE') === 'FALSE' ? '(Program Nonaktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('Program_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label for="Start_Date" class="block text-sm font-bold text-gray-700">Tanggal Mulai <span class="text-red-500">*</span></label>
                        <input type="date" name="Start_Date" id="Start_Date" value="{{ old('Start_Date', $batch['Start_Date']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Start_Date') border-red-300 text-red-900 @enderror">
                        @error('Start_Date') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- End Date -->
                    <div>
                        <label for="End_Date" class="block text-sm font-bold text-gray-700">Tanggal Selesai <span class="text-red-500">*</span></label>
                        <input type="date" name="End_Date" id="End_Date" value="{{ old('End_Date', $batch['End_Date']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('End_Date') border-red-300 text-red-900 @enderror">
                        @error('End_Date') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Batch Status -->
                    <div>
                        <label for="Batch_Status" class="block text-sm font-bold text-gray-700">Status Angkatan</label>
                        <select name="Batch_Status" id="Batch_Status" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Batch_Status') border-red-300 text-red-900 @enderror">
                            <option value="Persiapan" {{ old('Batch_Status', $batch['Batch_Status']) == 'Persiapan' ? 'selected' : '' }}>Persiapan (Pendaftaran)</option>
                            <option value="Berlangsung" {{ old('Batch_Status', $batch['Batch_Status']) == 'Berlangsung' ? 'selected' : '' }}>Sedang Berlangsung</option>
                            <option value="Selesai" {{ old('Batch_Status', $batch['Batch_Status']) == 'Selesai' ? 'selected' : '' }}>Selesai (Lulus)</option>
                            <option value="Ditunda" {{ old('Batch_Status', $batch['Batch_Status']) == 'Ditunda' ? 'selected' : '' }}>Ditunda</option>
                            <option value="Dibatalkan" {{ old('Batch_Status', $batch['Batch_Status']) == 'Dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                        </select>
                        @error('Batch_Status') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                    
                    <!-- Is Active -->
                    <div>
                        <label for="Is_Active" class="block text-sm font-bold text-gray-700">Status Sistem <span class="text-red-500">*</span></label>
                        <select name="Is_Active" id="Is_Active" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Is_Active') border-red-300 text-red-900 @enderror">
                            <option value="TRUE" {{ old('Is_Active', $batch['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'selected' : '' }}>Aktif</option>
                            <option value="FALSE" {{ old('Is_Active', $batch['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'selected' : '' }}>Nonaktif (Soft Delete)</option>
                        </select>
                        @error('Is_Active') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="Description" class="block text-sm font-bold text-gray-700">Deskripsi Singkat</label>
                        <textarea id="Description" name="Description" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Description', $batch['Description']) }}</textarea>
                    </div>

                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Internal</label>
                        <textarea id="Notes" name="Notes" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes', $batch['Notes']) }}</textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white/80 backdrop-blur-md pb-4 rounded-b-2xl">
                    <a href="{{ route('batches.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Perbarui Angkatan</span>
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
        
        // Auto-correct end date logic
        const startDateInput = document.getElementById('Start_Date');
        const endDateInput = document.getElementById('End_Date');
        
        startDateInput.addEventListener('change', function() {
            if (endDateInput.value && endDateInput.value < this.value) {
                endDateInput.value = this.value;
            }
            endDateInput.min = this.value;
        });
        endDateInput.min = startDateInput.value;
    });
</script>
@endsection
