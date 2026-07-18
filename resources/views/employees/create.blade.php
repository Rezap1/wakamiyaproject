@extends('layouts.app')

@section('header', 'Tambah Karyawan Baru')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white">
            <h2 class="text-xl font-extrabold text-gray-900">Formulir Pendaftaran Karyawan</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Lengkapi informasi di bawah ini. Nomor Induk Karyawan (NIK) akan dibuat otomatis oleh sistem.</p>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('employees.store') }}" method="POST" class="space-y-10">
                @csrf
                
                <!-- SECTION: DATA PRIBADI -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-6 flex items-center">
                        <span class="bg-primary-100 text-primary-700 rounded-lg p-1.5 mr-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </span>
                        Data Pribadi
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Full Name -->
                        <div class="md:col-span-2">
                            <label for="Full_Name" class="block text-sm font-bold text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="Full_Name" id="Full_Name" value="{{ old('Full_Name') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Full_Name') border-red-300 text-red-900 @enderror" placeholder="Sesuai dengan KTP">
                            @error('Full_Name') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Gender -->
                        <div>
                            <label for="Gender" class="block text-sm font-bold text-gray-700">Jenis Kelamin <span class="text-red-500">*</span></label>
                            <select name="Gender" id="Gender" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Gender') border-red-300 text-red-900 @enderror">
                                <option value="" disabled selected>Pilih...</option>
                                <option value="Laki-laki" {{ old('Gender') == 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                <option value="Perempuan" {{ old('Gender') == 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                            @error('Gender') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- National ID -->
                        <div>
                            <label for="National_ID" class="block text-sm font-bold text-gray-700">Nomor KTP (NIK Nasional)</label>
                            <input type="text" name="National_ID" id="National_ID" value="{{ old('National_ID') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('National_ID') border-red-300 text-red-900 @enderror" placeholder="16 Digit NIK">
                            @error('National_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Birth Place & Date -->
                        <div>
                            <label for="Birth_Place" class="block text-sm font-bold text-gray-700">Tempat Lahir</label>
                            <input type="text" name="Birth_Place" id="Birth_Place" value="{{ old('Birth_Place') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Kota Kelahiran">
                        </div>
                        <div>
                            <label for="Birth_Date" class="block text-sm font-bold text-gray-700">Tanggal Lahir</label>
                            <input type="date" name="Birth_Date" id="Birth_Date" value="{{ old('Birth_Date') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="Email" class="block text-sm font-bold text-gray-700">Email Utama <span class="text-red-500">*</span></label>
                            <input type="email" name="Email" id="Email" value="{{ old('Email') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Email') border-red-300 text-red-900 @enderror" placeholder="email@contoh.com">
                            @error('Email') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label for="Phone_Number" class="block text-sm font-bold text-gray-700">Nomor Telepon / WhatsApp <span class="text-red-500">*</span></label>
                            <input type="text" name="Phone_Number" id="Phone_Number" value="{{ old('Phone_Number') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Phone_Number') border-red-300 text-red-900 @enderror" placeholder="08123456789">
                            @error('Phone_Number') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Address -->
                        <div class="md:col-span-2">
                            <label for="Address" class="block text-sm font-bold text-gray-700">Alamat Domisili</label>
                            <textarea name="Address" id="Address" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Alamat lengkap...">{{ old('Address') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- SECTION: DATA PEKERJAAN -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-6 flex items-center">
                        <span class="bg-blue-100 text-blue-700 rounded-lg p-1.5 mr-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </span>
                        Data Pekerjaan (Organisasi)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Department -->
                        <div>
                            <label for="Department_ID" class="block text-sm font-bold text-gray-700">Departemen <span class="text-red-500">*</span></label>
                            <select name="Department_ID" id="Department_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Department_ID') border-red-300 text-red-900 @enderror">
                                <option value="" disabled selected>Pilih Departemen...</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept['Department_ID'] }}" {{ old('Department_ID') == $dept['Department_ID'] ? 'selected' : '' }}>
                                        {{ $dept['Department_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Department_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Position (Dependent) -->
                        <div>
                            <label for="Position_ID" class="block text-sm font-bold text-gray-700">Posisi / Jabatan <span class="text-red-500">*</span></label>
                            <select name="Position_ID" id="Position_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Position_ID') border-red-300 text-red-900 @enderror">
                                <option value="" disabled selected>Pilih Departemen Terlebih Dahulu...</option>
                                @foreach($positions as $pos)
                                    <option value="{{ $pos['Position_ID'] }}" data-dept="{{ $pos['Department_ID'] }}" {{ old('Position_ID') == $pos['Position_ID'] ? 'selected' : '' }}>
                                        {{ $pos['Position_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Position_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Join Date -->
                        <div>
                            <label for="Join_Date" class="block text-sm font-bold text-gray-700">Tanggal Bergabung <span class="text-red-500">*</span></label>
                            <input type="date" name="Join_Date" id="Join_Date" value="{{ old('Join_Date', date('Y-m-d')) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Join_Date') border-red-300 text-red-900 @enderror">
                            @error('Join_Date') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Employment Status -->
                        <div>
                            <label for="Employment_Status" class="block text-sm font-bold text-gray-700">Status Kepegawaian <span class="text-red-500">*</span></label>
                            <select name="Employment_Status" id="Employment_Status" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Employment_Status') border-red-300 text-red-900 @enderror">
                                <option value="" disabled selected>Pilih Status...</option>
                                <option value="Tetap (PKWTT)" {{ old('Employment_Status') == 'Tetap (PKWTT)' ? 'selected' : '' }}>Tetap (PKWTT)</option>
                                <option value="Kontrak (PKWT)" {{ old('Employment_Status') == 'Kontrak (PKWT)' ? 'selected' : '' }}>Kontrak (PKWT)</option>
                                <option value="Probation" {{ old('Employment_Status') == 'Probation' ? 'selected' : '' }}>Probation (Percobaan)</option>
                                <option value="Magang" {{ old('Employment_Status') == 'Magang' ? 'selected' : '' }}>Magang (Internship)</option>
                            </select>
                            @error('Employment_Status') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- SECTION: DATA PERBANKAN & PAJAK -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-6 flex items-center">
                        <span class="bg-green-100 text-green-700 rounded-lg p-1.5 mr-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        </span>
                        Data Perbankan & Pajak
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tax Number (NPWP) -->
                        <div>
                            <label for="Tax_Number" class="block text-sm font-bold text-gray-700">NPWP</label>
                            <input type="text" name="Tax_Number" id="Tax_Number" value="{{ old('Tax_Number') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Nomor NPWP">
                        </div>

                        <!-- Bank Name -->
                        <div>
                            <label for="Bank_Name" class="block text-sm font-bold text-gray-700">Nama Bank</label>
                            <select name="Bank_Name" id="Bank_Name" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="" disabled selected>Pilih Bank...</option>
                                <option value="BCA" {{ old('Bank_Name') == 'BCA' ? 'selected' : '' }}>BCA</option>
                                <option value="Mandiri" {{ old('Bank_Name') == 'Mandiri' ? 'selected' : '' }}>Mandiri</option>
                                <option value="BNI" {{ old('Bank_Name') == 'BNI' ? 'selected' : '' }}>BNI</option>
                                <option value="BRI" {{ old('Bank_Name') == 'BRI' ? 'selected' : '' }}>BRI</option>
                                <option value="BSI" {{ old('Bank_Name') == 'BSI' ? 'selected' : '' }}>BSI</option>
                                <option value="Lainnya" {{ old('Bank_Name') == 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                            </select>
                        </div>

                        <!-- Bank Account Number -->
                        <div>
                            <label for="Bank_Account_Number" class="block text-sm font-bold text-gray-700">Nomor Rekening</label>
                            <input type="text" name="Bank_Account_Number" id="Bank_Account_Number" value="{{ old('Bank_Account_Number') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Nomor Rekening">
                        </div>

                        <!-- Account Holder -->
                        <div>
                            <label for="Account_Holder_Name" class="block text-sm font-bold text-gray-700">Nama Pemilik Rekening</label>
                            <input type="text" name="Account_Holder_Name" id="Account_Holder_Name" value="{{ old('Account_Holder_Name') }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Sesuai Buku Tabungan">
                        </div>
                    </div>
                </div>

                <!-- SECTION: LAINNYA -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-6 flex items-center">
                        <span class="bg-gray-100 text-gray-700 rounded-lg p-1.5 mr-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </span>
                        Lainnya
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Tambahan</label>
                            <textarea id="Notes" name="Notes" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Informasi medis, kontak darurat, atau catatan khusus lainnya...">{{ old('Notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white/80 backdrop-blur-md pb-4 rounded-b-2xl">
                    <a href="{{ route('employees.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Karyawan Baru</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dependent Dropdown for Position based on Department
        const deptSelect = document.getElementById('Department_ID');
        const posSelect = document.getElementById('Position_ID');
        const posOptions = Array.from(posSelect.options);
        const oldPosId = "{{ old('Position_ID') }}";

        function filterPositions() {
            const selectedDept = deptSelect.value;
            let hasValidOptions = false;
            
            posSelect.innerHTML = '';
            
            if (!selectedDept) {
                const defaultOption = new Option('Pilih Departemen Terlebih Dahulu...', '', true, true);
                defaultOption.disabled = true;
                posSelect.add(defaultOption);
                return;
            }

            const defaultOption = new Option('Pilih Posisi / Jabatan...', '', true, true);
            defaultOption.disabled = true;
            posSelect.add(defaultOption);

            posOptions.forEach(option => {
                if (option.value && option.getAttribute('data-dept') === selectedDept) {
                    const newOption = new Option(option.text, option.value);
                    if (option.value === oldPosId) {
                        newOption.selected = true;
                    }
                    posSelect.add(newOption);
                    hasValidOptions = true;
                }
            });
            
            if (!hasValidOptions) {
                posSelect.innerHTML = '';
                const emptyOption = new Option('Tidak ada posisi di departemen ini', '', true, true);
                emptyOption.disabled = true;
                posSelect.add(emptyOption);
            }
        }

        deptSelect.addEventListener('change', filterPositions);
        
        // Initial filter on page load (in case of validation error)
        if (deptSelect.value) {
            filterPositions();
        }

        // Form Submission Loading State
        document.querySelector('form').addEventListener('submit', function(e) {
            // Cek jika field disabled tidak dikirim
            const btn = document.getElementById('submitBtn');
            const text = document.getElementById('submitText');
            const loading = document.getElementById('submitLoading');
            
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.classList.remove('hover:-translate-y-0.5');
            text.innerText = 'Memproses...';
            loading.classList.remove('hidden');
        });
    });
</script>
@endsection
