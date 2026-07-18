@extends('layouts.app')

@section('header', 'Perbarui Data Siswa')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900">Formulir Pembaruan Data Siswa</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">ID: <span class="font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded font-mono">{{ $student['Student_ID'] }}</span></p>
            </div>
            <div>
                @if(($student['Is_Active'] ?? 'TRUE') === 'TRUE')
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
            <form action="{{ route('students.update', $student['Student_ID']) }}" method="POST" class="space-y-10">
                @csrf
                @method('PUT')
                
                <!-- Section 1: Akademik & Penempatan -->
                <div>
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">1. Penempatan Akademik</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Registration Date -->
                        <div class="md:col-span-3">
                            <label for="Registration_Date" class="block text-sm font-bold text-gray-700">Tanggal Pendaftaran <span class="text-red-500">*</span></label>
                            <input type="date" name="Registration_Date" id="Registration_Date" value="{{ old('Registration_Date', $student['Registration_Date']) }}" class="mt-2 block w-full md:w-1/3 rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Registration_Date') border-red-300 text-red-900 @enderror">
                            @error('Registration_Date') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Program ID -->
                        <div>
                            <label for="Program_ID" class="block text-sm font-bold text-gray-700">Program <span class="text-red-500">*</span></label>
                            <select name="Program_ID" id="Program_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Program_ID') border-red-300 text-red-900 @enderror">
                                <option value="" disabled>Pilih Program...</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program['Program_ID'] }}" {{ old('Program_ID', $student['Program_ID']) == $program['Program_ID'] ? 'selected' : '' }}>
                                        {{ $program['Program_Name'] }} {{ ($program['Is_Active'] ?? 'TRUE') === 'FALSE' ? '(Nonaktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Program_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Batch ID -->
                        <div>
                            <label for="Batch_ID" class="block text-sm font-bold text-gray-700">Angkatan <span class="text-red-500">*</span></label>
                            <select name="Batch_ID" id="Batch_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Batch_ID') border-red-300 text-red-900 @enderror">
                                <option value="" disabled>Pilih Angkatan...</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch['Batch_ID'] }}" data-program="{{ $batch['Program_ID'] }}" class="hidden" {{ old('Batch_ID', $student['Batch_ID']) == $batch['Batch_ID'] ? 'selected' : '' }}>
                                        {{ $batch['Batch_Name'] }} {{ ($batch['Is_Active'] ?? 'TRUE') === 'FALSE' ? '(Nonaktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Batch_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Class ID -->
                        <div>
                            <label for="Class_ID" class="block text-sm font-bold text-gray-700">Kelas Dasar <span class="text-red-500">*</span></label>
                            <select name="Class_ID" id="Class_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Class_ID') border-red-300 text-red-900 @enderror">
                                <option value="" disabled>Pilih Kelas...</option>
                                @foreach($classes as $cls)
                                    <option value="{{ $cls['Class_ID'] }}" data-batch="{{ $cls['Batch_ID'] }}" class="hidden" {{ old('Class_ID', $student['Class_ID']) == $cls['Class_ID'] ? 'selected' : '' }}>
                                        {{ $cls['Class_Name'] }} {{ ($cls['Is_Active'] ?? 'TRUE') === 'FALSE' ? '(Nonaktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Class_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Enrollment Status -->
                        <div>
                            <label for="Enrollment_Status" class="block text-sm font-bold text-gray-700">Status Pendikan <span class="text-red-500">*</span></label>
                            <select name="Enrollment_Status" id="Enrollment_Status" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Enrollment_Status') border-red-300 text-red-900 @enderror">
                                <option value="Aktif Belajar" {{ old('Enrollment_Status', $student['Enrollment_Status']) == 'Aktif Belajar' ? 'selected' : '' }}>Aktif Belajar</option>
                                <option value="Menunggu Kelas" {{ old('Enrollment_Status', $student['Enrollment_Status']) == 'Menunggu Kelas' ? 'selected' : '' }}>Menunggu Kelas</option>
                                <option value="Cuti" {{ old('Enrollment_Status', $student['Enrollment_Status']) == 'Cuti' ? 'selected' : '' }}>Cuti / Istirahat</option>
                                <option value="Drop Out" {{ old('Enrollment_Status', $student['Enrollment_Status']) == 'Drop Out' ? 'selected' : '' }}>Drop Out (Berhenti)</option>
                            </select>
                        </div>
                        
                        <!-- Graduation Status -->
                        <div>
                            <label for="Graduation_Status" class="block text-sm font-bold text-gray-700">Status Kelulusan</label>
                            <select name="Graduation_Status" id="Graduation_Status" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="Belum Lulus" {{ old('Graduation_Status', $student['Graduation_Status']) == 'Belum Lulus' ? 'selected' : '' }}>Belum Lulus</option>
                                <option value="Lulus" {{ old('Graduation_Status', $student['Graduation_Status']) == 'Lulus' ? 'selected' : '' }}>Lulus (Alumni)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Data Pribadi -->
                <div>
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">2. Data Pribadi Siswa</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Student Number -->
                        <div>
                            <label for="Student_Number" class="block text-sm font-bold text-gray-700">Nomor Induk Siswa (NIS) <span class="text-red-500">*</span></label>
                            <input type="text" name="Student_Number" id="Student_Number" value="{{ old('Student_Number', $student['Student_Number']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono uppercase @error('Student_Number') border-red-300 text-red-900 @enderror">
                            @error('Student_Number') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- National ID -->
                        <div>
                            <label for="National_ID" class="block text-sm font-bold text-gray-700">NIK KTP</label>
                            <input type="text" name="National_ID" id="National_ID" value="{{ old('National_ID', $student['National_ID']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono @error('National_ID') border-red-300 text-red-900 @enderror">
                            @error('National_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Full Name -->
                        <div class="md:col-span-2">
                            <label for="Full_Name" class="block text-sm font-bold text-gray-700">Nama Lengkap Sesuai Identitas <span class="text-red-500">*</span></label>
                            <input type="text" name="Full_Name" id="Full_Name" value="{{ old('Full_Name', $student['Full_Name']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors uppercase @error('Full_Name') border-red-300 text-red-900 @enderror">
                            @error('Full_Name') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Gender -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Jenis Kelamin <span class="text-red-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center cursor-pointer p-3 border border-gray-200 rounded-xl hover:bg-blue-50 transition-colors w-full group">
                                    <input type="radio" name="Gender" value="Laki-laki" {{ old('Gender', $student['Gender']) == 'Laki-laki' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500 h-4 w-4 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-blue-700 font-medium">Laki-laki</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer p-3 border border-gray-200 rounded-xl hover:bg-pink-50 transition-colors w-full group">
                                    <input type="radio" name="Gender" value="Perempuan" {{ old('Gender', $student['Gender']) == 'Perempuan' ? 'checked' : '' }} class="text-pink-600 focus:ring-pink-500 h-4 w-4 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-pink-700 font-medium">Perempuan</span>
                                </label>
                            </div>
                        </div>

                        <!-- Education -->
                        <div>
                            <label for="Education" class="block text-sm font-bold text-gray-700">Pendidikan Terakhir <span class="text-red-500">*</span></label>
                            <select name="Education" id="Education" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Education') border-red-300 text-red-900 @enderror">
                                <option value="SMP Sederajat" {{ old('Education', $student['Education']) == 'SMP Sederajat' ? 'selected' : '' }}>SMP Sederajat</option>
                                <option value="SMA / SMK Sederajat" {{ old('Education', $student['Education']) == 'SMA / SMK Sederajat' ? 'selected' : '' }}>SMA / SMK Sederajat</option>
                                <option value="D3 / Diploma" {{ old('Education', $student['Education']) == 'D3 / Diploma' ? 'selected' : '' }}>D3 / Diploma</option>
                                <option value="S1 / Sarjana" {{ old('Education', $student['Education']) == 'S1 / Sarjana' ? 'selected' : '' }}>S1 / Sarjana</option>
                            </select>
                        </div>

                        <!-- Birth Place & Date -->
                        <div>
                            <label for="Birth_Place" class="block text-sm font-bold text-gray-700">Tempat Lahir</label>
                            <input type="text" name="Birth_Place" id="Birth_Place" value="{{ old('Birth_Place', $student['Birth_Place']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                        <div>
                            <label for="Birth_Date" class="block text-sm font-bold text-gray-700">Tanggal Lahir</label>
                            <input type="date" name="Birth_Date" id="Birth_Date" value="{{ old('Birth_Date', $student['Birth_Date']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Kontak & Alamat -->
                <div>
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">3. Kontak, Sistem & Alamat</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Phone -->
                        <div>
                            <label for="Phone_Number" class="block text-sm font-bold text-gray-700">Nomor WhatsApp / HP AKTIF</label>
                            <input type="tel" name="Phone_Number" id="Phone_Number" value="{{ old('Phone_Number', $student['Phone_Number']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="Email" class="block text-sm font-bold text-gray-700">Alamat Email</label>
                            <input type="email" name="Email" id="Email" value="{{ old('Email', $student['Email']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors lowercase">
                        </div>

                        <!-- Address -->
                        <div class="md:col-span-2">
                            <label for="Address" class="block text-sm font-bold text-gray-700">Alamat Lengkap</label>
                            <textarea id="Address" name="Address" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Address', $student['Address']) }}</textarea>
                        </div>
                        
                        <!-- Is Active -->
                        <div>
                            <label for="Is_Active" class="block text-sm font-bold text-gray-700">Status Sistem WMS <span class="text-red-500">*</span></label>
                            <select name="Is_Active" id="Is_Active" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-bold">
                                <option value="TRUE" {{ old('Is_Active', $student['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'selected' : '' }}>Aktif</option>
                                <option value="FALSE" {{ old('Is_Active', $student['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'selected' : '' }}>Nonaktif (Soft Delete)</option>
                            </select>
                        </div>
                        
                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Internal / Riwayat Penyakit</label>
                            <textarea id="Notes" name="Notes" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes', $student['Notes']) }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white/90 backdrop-blur-md pb-4 rounded-b-2xl">
                    <a href="{{ route('students.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Perbarui Profil Siswa</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Loading Button
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
        
        // Triple Chained Dropdowns Logic
        const programSelect = document.getElementById('Program_ID');
        const batchSelect = document.getElementById('Batch_ID');
        const classSelect = document.getElementById('Class_ID');
        
        const batchOptions = batchSelect.querySelectorAll('option[data-program]');
        const classOptions = classSelect.querySelectorAll('option[data-batch]');
        
        const currentBatchId = "{{ old('Batch_ID', $student['Batch_ID']) }}";
        const currentClassId = "{{ old('Class_ID', $student['Class_ID']) }}";

        function filterBatches() {
            const selectedProgramId = programSelect.value;
            
            batchOptions.forEach(option => {
                if (option.getAttribute('data-program') === selectedProgramId) {
                    option.classList.remove('hidden');
                    option.disabled = false;
                } else {
                    option.classList.add('hidden');
                    option.disabled = true;
                }
            });

            if (selectedProgramId) {
                const selectedOption = batchSelect.options[batchSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled && selectedOption.value !== "") {
                    batchSelect.value = "";
                }
            }
            
            filterClasses();
        }

        function filterClasses() {
            const selectedBatchId = batchSelect.value;
            
            classOptions.forEach(option => {
                if (option.getAttribute('data-batch') === selectedBatchId) {
                    option.classList.remove('hidden');
                    option.disabled = false;
                } else {
                    option.classList.add('hidden');
                    option.disabled = true;
                }
            });

            if (selectedBatchId) {
                const selectedOption = classSelect.options[classSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled && selectedOption.value !== "") {
                    classSelect.value = "";
                }
            }
        }

        programSelect.addEventListener('change', filterBatches);
        batchSelect.addEventListener('change', filterClasses);
        
        // Initial setup on load
        if (programSelect.value) {
            filterBatches();
            if(currentBatchId) {
                batchSelect.value = currentBatchId;
                filterClasses();
                if (currentClassId) {
                    classSelect.value = currentClassId;
                }
            }
        }
    });
</script>
@endsection
