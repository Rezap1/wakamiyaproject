@extends('layouts.app')

@section('header', 'Pendaftaran Siswa Baru')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white">
            <h2 class="text-xl font-extrabold text-gray-900">Formulir Pendaftaran Siswa</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Lengkapi data pribadi, kontak, dan penempatan akademik siswa.</p>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('students.store') }}" method="POST" class="space-y-10">
                @csrf
                
                <!-- Section 1: Akademik & Penempatan -->
                <div>
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">1. Penempatan Akademik</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Registration Date -->
                        <div class="md:col-span-3">
                            <label for="Registration_Date" class="block text-sm font-bold text-gray-700">Tanggal Pendaftaran <span class="text-red-500">*</span></label>
                            <input type="date" name="Registration_Date" id="Registration_Date" value="{{ old('Registration_Date', date('Y-m-d')) }}" class="mt-2 block w-full md:w-1/3 rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Registration_Date') border-red-300 text-red-900 @enderror">
                            @error('Registration_Date') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Program ID -->
                        <div>
                            <label for="Program_ID" class="block text-sm font-bold text-gray-700">Program <span class="text-red-500">*</span></label>
                            <select name="Program_ID" id="Program_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Program_ID') border-red-300 text-red-900 @enderror">
                                <option value="" disabled selected>1. Pilih Program</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program['Program_ID'] }}" {{ old('Program_ID') == $program['Program_ID'] ? 'selected' : '' }}>
                                        {{ $program['Program_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Program_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Batch ID -->
                        <div>
                            <label for="Batch_ID" class="block text-sm font-bold text-gray-700">Angkatan <span class="text-red-500">*</span></label>
                            <select name="Batch_ID" id="Batch_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed @error('Batch_ID') border-red-300 text-red-900 @enderror">
                                <option value="" disabled selected>2. Pilih Angkatan</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch['Batch_ID'] }}" data-program="{{ $batch['Program_ID'] }}" class="hidden" {{ old('Batch_ID') == $batch['Batch_ID'] ? 'selected' : '' }}>
                                        {{ $batch['Batch_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Batch_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Class ID -->
                        <div>
                            <label for="Class_ID" class="block text-sm font-bold text-gray-700">Kelas Dasar <span class="text-red-500">*</span></label>
                            <select name="Class_ID" id="Class_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed @error('Class_ID') border-red-300 text-red-900 @enderror">
                                <option value="" disabled selected>3. Pilih Kelas</option>
                                @foreach($classes as $cls)
                                    <option value="{{ $cls['Class_ID'] }}" data-batch="{{ $cls['Batch_ID'] }}" class="hidden" {{ old('Class_ID') == $cls['Class_ID'] ? 'selected' : '' }}>
                                        {{ $cls['Class_Name'] }} (Sisa: {{ $cls['Capacity'] - $cls['Current_Student'] }})
                                    </option>
                                @endforeach
                            </select>
                            @error('Class_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Enrollment Status -->
                        <div>
                            <label for="Enrollment_Status" class="block text-sm font-bold text-gray-700">Status Pendikan <span class="text-red-500">*</span></label>
                            <select name="Enrollment_Status" id="Enrollment_Status" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Enrollment_Status') border-red-300 text-red-900 @enderror">
                                <option value="Aktif Belajar" {{ old('Enrollment_Status') == 'Aktif Belajar' ? 'selected' : '' }}>Aktif Belajar</option>
                                <option value="Menunggu Kelas" {{ old('Enrollment_Status') == 'Menunggu Kelas' ? 'selected' : '' }}>Menunggu Kelas</option>
                                <option value="Cuti" {{ old('Enrollment_Status') == 'Cuti' ? 'selected' : '' }}>Cuti / Istirahat</option>
                                <option value="Drop Out" {{ old('Enrollment_Status') == 'Drop Out' ? 'selected' : '' }}>Drop Out (Berhenti)</option>
                            </select>
                            @error('Enrollment_Status') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
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
                            <input type="text" name="Student_Number" id="Student_Number" value="{{ old('Student_Number') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono uppercase @error('Student_Number') border-red-300 text-red-900 @enderror" placeholder="Contoh: NIS2023001">
                            @error('Student_Number') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- National ID -->
                        <div>
                            <label for="National_ID" class="block text-sm font-bold text-gray-700">NIK KTP</label>
                            <input type="text" name="National_ID" id="National_ID" value="{{ old('National_ID') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-mono @error('National_ID') border-red-300 text-red-900 @enderror" placeholder="16 Digit NIK KTP">
                            @error('National_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Full Name -->
                        <div class="md:col-span-2">
                            <label for="Full_Name" class="block text-sm font-bold text-gray-700">Nama Lengkap Sesuai Identitas <span class="text-red-500">*</span></label>
                            <input type="text" name="Full_Name" id="Full_Name" value="{{ old('Full_Name') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors uppercase @error('Full_Name') border-red-300 text-red-900 @enderror" placeholder="Nama Lengkap Siswa">
                            @error('Full_Name') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Gender -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Jenis Kelamin <span class="text-red-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center cursor-pointer p-3 border border-gray-200 rounded-xl hover:bg-blue-50 transition-colors w-full group">
                                    <input type="radio" name="Gender" value="Laki-laki" {{ old('Gender') == 'Laki-laki' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500 h-4 w-4 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-blue-700 font-medium">Laki-laki</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer p-3 border border-gray-200 rounded-xl hover:bg-pink-50 transition-colors w-full group">
                                    <input type="radio" name="Gender" value="Perempuan" {{ old('Gender') == 'Perempuan' ? 'checked' : '' }} class="text-pink-600 focus:ring-pink-500 h-4 w-4 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-pink-700 font-medium">Perempuan</span>
                                </label>
                            </div>
                            @error('Gender') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Education -->
                        <div>
                            <label for="Education" class="block text-sm font-bold text-gray-700">Pendidikan Terakhir <span class="text-red-500">*</span></label>
                            <select name="Education" id="Education" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Education') border-red-300 text-red-900 @enderror">
                                <option value="" disabled selected>Pilih Pendidikan...</option>
                                <option value="SMP Sederajat" {{ old('Education') == 'SMP Sederajat' ? 'selected' : '' }}>SMP Sederajat</option>
                                <option value="SMA / SMK Sederajat" {{ old('Education') == 'SMA / SMK Sederajat' ? 'selected' : '' }}>SMA / SMK Sederajat</option>
                                <option value="D3 / Diploma" {{ old('Education') == 'D3 / Diploma' ? 'selected' : '' }}>D3 / Diploma</option>
                                <option value="S1 / Sarjana" {{ old('Education') == 'S1 / Sarjana' ? 'selected' : '' }}>S1 / Sarjana</option>
                            </select>
                            @error('Education') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Birth Place & Date -->
                        <div>
                            <label for="Birth_Place" class="block text-sm font-bold text-gray-700">Tempat Lahir</label>
                            <input type="text" name="Birth_Place" id="Birth_Place" value="{{ old('Birth_Place') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Birth_Place') border-red-300 text-red-900 @enderror">
                        </div>
                        <div>
                            <label for="Birth_Date" class="block text-sm font-bold text-gray-700">Tanggal Lahir</label>
                            <input type="date" name="Birth_Date" id="Birth_Date" value="{{ old('Birth_Date') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Birth_Date') border-red-300 text-red-900 @enderror">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Kontak & Alamat -->
                <div>
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">3. Kontak & Alamat</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Phone -->
                        <div>
                            <label for="Phone_Number" class="block text-sm font-bold text-gray-700">Nomor WhatsApp / HP AKTIF</label>
                            <input type="tel" name="Phone_Number" id="Phone_Number" value="{{ old('Phone_Number') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Phone_Number') border-red-300 text-red-900 @enderror" placeholder="08123456789">
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="Email" class="block text-sm font-bold text-gray-700">Alamat Email</label>
                            <input type="email" name="Email" id="Email" value="{{ old('Email') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors lowercase @error('Email') border-red-300 text-red-900 @enderror" placeholder="email@contoh.com">
                            @error('Email') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Address -->
                        <div class="md:col-span-2">
                            <label for="Address" class="block text-sm font-bold text-gray-700">Alamat Lengkap</label>
                            <textarea id="Address" name="Address" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Nama Jalan, RT/RW, Desa, Kecamatan, Kab/Kota">{{ old('Address') }}</textarea>
                        </div>
                        
                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Internal / Riwayat Penyakit</label>
                            <textarea id="Notes" name="Notes" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white/90 backdrop-blur-md pb-4 rounded-b-2xl">
                    <a href="{{ route('students.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Daftarkan Siswa</span>
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
        
        const oldBatchId = "{{ old('Batch_ID') }}";
        const oldClassId = "{{ old('Class_ID') }}";

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
                batchSelect.disabled = false;
                const selectedOption = batchSelect.options[batchSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled && selectedOption.value !== "") {
                    batchSelect.value = "";
                }
            } else {
                batchSelect.disabled = true;
                batchSelect.value = "";
            }
            
            // Trigger class filter automatically
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
                classSelect.disabled = false;
                const selectedOption = classSelect.options[classSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled && selectedOption.value !== "") {
                    classSelect.value = "";
                }
            } else {
                classSelect.disabled = true;
                classSelect.value = "";
            }
        }

        programSelect.addEventListener('change', filterBatches);
        batchSelect.addEventListener('change', filterClasses);
        
        // Initial setup on load (handling old() form data)
        if (programSelect.value) {
            filterBatches();
            if(oldBatchId) {
                batchSelect.value = oldBatchId;
                filterClasses();
                if (oldClassId) {
                    classSelect.value = oldClassId;
                }
            }
        }
    });
</script>
@endsection
