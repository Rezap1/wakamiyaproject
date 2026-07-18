@extends('layouts.app')

@section('header', 'Perbarui Data Tenaga Pendidik')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900">Formulir Pembaruan Profil Guru</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">Kode: <span class="font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $teacher['Teacher_Code'] }}</span></p>
            </div>
            <div>
                @if(($teacher['Is_Active'] ?? 'TRUE') === 'TRUE')
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200">
                        Status Sistem: Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200">
                        Status Sistem: Nonaktif
                    </span>
                @endif
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('teachers.update', $teacher['Teacher_ID']) }}" method="POST" class="space-y-10">
                @csrf
                @method('PUT')
                
                <!-- SECTION: PILIH KARYAWAN -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-6 flex items-center">
                        <span class="bg-primary-100 text-primary-700 rounded-lg p-1.5 mr-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </span>
                        Tautan Karyawan
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="Employee_ID" class="block text-sm font-bold text-gray-700">Pegawai Tersambung <span class="text-red-500">*</span></label>
                            <select name="Employee_ID" id="Employee_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Employee_ID') border-red-300 text-red-900 @enderror">
                                @foreach($employees as $emp)
                                    <option value="{{ $emp['Employee_ID'] }}" {{ old('Employee_ID', $teacher['Employee_ID']) == $emp['Employee_ID'] ? 'selected' : '' }}>
                                        {{ $emp['Employee_Number'] }} - {{ $emp['Full_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Employee_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- SECTION: DATA PRIBADI (AUTOFILL READONLY) -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-6 flex items-center">
                        <span class="bg-gray-100 text-gray-700 rounded-lg p-1.5 mr-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </span>
                        Informasi Pribadi Tersinkronisasi (Read-Only)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Full Name -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700">Nama Lengkap</label>
                            <input type="text" id="auto_Full_Name" readonly class="mt-2 block w-full rounded-xl border-gray-200 sm:text-sm px-4 py-3 bg-gray-100 text-gray-600 cursor-not-allowed font-medium" placeholder="-">
                        </div>

                        <!-- Gender -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700">Jenis Kelamin</label>
                            <input type="text" id="auto_Gender" readonly class="mt-2 block w-full rounded-xl border-gray-200 sm:text-sm px-4 py-3 bg-gray-100 text-gray-600 cursor-not-allowed font-medium" placeholder="-">
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700">Nomor Telepon</label>
                            <input type="text" id="auto_Phone" readonly class="mt-2 block w-full rounded-xl border-gray-200 sm:text-sm px-4 py-3 bg-gray-100 text-gray-600 cursor-not-allowed font-medium" placeholder="-">
                        </div>

                        <!-- Email -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700">Email Utama</label>
                            <input type="email" id="auto_Email" readonly class="mt-2 block w-full rounded-xl border-gray-200 sm:text-sm px-4 py-3 bg-gray-100 text-gray-600 cursor-not-allowed font-medium" placeholder="-">
                        </div>
                    </div>
                </div>

                <!-- SECTION: DATA PENGAJAR -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-6 flex items-center">
                        <span class="bg-indigo-100 text-indigo-700 rounded-lg p-1.5 mr-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        </span>
                        Informasi Pengajaran
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Specialization -->
                        <div class="md:col-span-2">
                            <label for="Specialization" class="block text-sm font-bold text-gray-700">Spesialisasi Mata Pelajaran <span class="text-red-500">*</span></label>
                            <input type="text" name="Specialization" id="Specialization" value="{{ old('Specialization', $teacher['Specialization']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Specialization') border-red-300 text-red-900 @enderror">
                            @error('Specialization') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Hire Date -->
                        <div>
                            <label for="Hire_Date" class="block text-sm font-bold text-gray-700">Tanggal Mulai Mengajar <span class="text-red-500">*</span></label>
                            <input type="date" name="Hire_Date" id="Hire_Date" value="{{ old('Hire_Date', $teacher['Hire_Date']) }}" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Hire_Date') border-red-300 text-red-900 @enderror">
                            @error('Hire_Date') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Teaching Status -->
                        <div>
                            <label for="Teaching_Status" class="block text-sm font-bold text-gray-700">Status Mengajar <span class="text-red-500">*</span></label>
                            <select name="Teaching_Status" id="Teaching_Status" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Teaching_Status') border-red-300 text-red-900 @enderror">
                                <option value="Aktif Mengajar" {{ old('Teaching_Status', $teacher['Teaching_Status']) == 'Aktif Mengajar' ? 'selected' : '' }}>Aktif Mengajar</option>
                                <option value="Cuti Mengajar" {{ old('Teaching_Status', $teacher['Teaching_Status']) == 'Cuti Mengajar' ? 'selected' : '' }}>Cuti Mengajar</option>
                            </select>
                            @error('Teaching_Status') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- SECTION: PENGATURAN -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-6 flex items-center">
                        <span class="bg-gray-100 text-gray-700 rounded-lg p-1.5 mr-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </span>
                        Pengaturan Sistem & Lainnya
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Status Pegawai (Is_Active) -->
                        <div>
                            <label for="Is_Active" class="block text-sm font-bold text-gray-700">Akses Sistem (Status) <span class="text-red-500">*</span></label>
                            <select name="Is_Active" id="Is_Active" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Is_Active') border-red-300 text-red-900 @enderror">
                                <option value="TRUE" {{ old('Is_Active', $teacher['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'selected' : '' }}>Aktif</option>
                                <option value="FALSE" {{ old('Is_Active', $teacher['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'selected' : '' }}>Nonaktif</option>
                            </select>
                            @error('Is_Active') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Tambahan</label>
                            <textarea id="Notes" name="Notes" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes', $teacher['Notes']) }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white/80 backdrop-blur-md pb-4 rounded-b-2xl">
                    <a href="{{ route('teachers.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Perbarui Profil Guru</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Employee Data for Autofill
        const employeeData = {!! $employeeData !!};
        const empSelect = document.getElementById('Employee_ID');
        
        const autoName = document.getElementById('auto_Full_Name');
        const autoGender = document.getElementById('auto_Gender');
        const autoPhone = document.getElementById('auto_Phone');
        const autoEmail = document.getElementById('auto_Email');

        function triggerAutofill() {
            const selectedId = empSelect.value;
            if (selectedId && employeeData[selectedId]) {
                const data = employeeData[selectedId];
                autoName.value = data.Full_Name;
                autoGender.value = data.Gender;
                autoPhone.value = data.Phone_Number;
                autoEmail.value = data.Email;
            } else {
                autoName.value = '';
                autoGender.value = '';
                autoPhone.value = '';
                autoEmail.value = '';
            }
        }

        empSelect.addEventListener('change', triggerAutofill);
        
        if (empSelect.value) {
            triggerAutofill();
        }

        // Form Submission Loading State
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
