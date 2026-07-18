@extends('layouts.app')

@section('header', 'Tambah Visa')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 flex items-center gap-4">
            <a href="{{ route('visas.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h2 class="text-2xl font-extrabold text-gray-900">Tambah Data Visa Baru</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">Buat pengajuan Visa untuk kandidat yang telah lolos COE.</p>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('visas.store') }}" method="POST" class="space-y-10">
                @csrf
                
                <!-- 1. Data Relasi & Kandidat -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">1. Data Relasi & Kandidat</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Student_ID" class="block text-sm font-bold text-gray-700 mb-2">Siswa / Kandidat <span class="text-red-500">*</span></label>
                            <select name="Student_ID" id="Student_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Siswa --</option>
                                @foreach($students as $student)
                                    <option value="{{ $student['Student_ID'] }}" {{ old('Student_ID') == $student['Student_ID'] ? 'selected' : '' }}>
                                        {{ $student['Registration_Number'] ?? $student['Student_ID'] }} - {{ $student['Full_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Student_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Passport_Number" class="block text-sm font-bold text-gray-700 mb-2">Nomor Paspor <span class="text-red-500">*</span></label>
                            <input type="text" name="Passport_Number" id="Passport_Number" value="{{ old('Passport_Number') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Contoh: A12345678" required>
                            @error('Passport_Number') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="COE_ID" class="block text-sm font-bold text-gray-700 mb-2">Terkait COE (Opsional)</label>
                            <select name="COE_ID" id="COE_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="">-- Pilih COE Terkait --</option>
                                @foreach($coes as $coe)
                                    <option value="{{ $coe['COE_ID'] }}" {{ old('COE_ID') == $coe['COE_ID'] ? 'selected' : '' }}>
                                        {{ $coe['COE_Number'] ?? $coe['COE_ID'] }} ({{ $coe['COE_Status'] ?? 'Unknown' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('COE_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Application_ID" class="block text-sm font-bold text-gray-700 mb-2">Aplikasi Pekerjaan (Opsional)</label>
                            <select name="Application_ID" id="Application_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="">-- Pilih Aplikasi Kerja --</option>
                                @foreach($applications as $app)
                                    <option value="{{ $app['Application_ID'] }}" {{ old('Application_ID') == $app['Application_ID'] ? 'selected' : '' }}>
                                        {{ $app['Application_Number'] ?? $app['Application_ID'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Application_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 2. Informasi Visa -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">2. Informasi Visa & Kedutaan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="Visa_Number" class="block text-sm font-bold text-gray-700 mb-2">Nomor Visa <span class="text-red-500">*</span></label>
                            <input type="text" name="Visa_Number" id="Visa_Number" value="{{ old('Visa_Number') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Nomor penerbitan visa" required>
                            @error('Visa_Number') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Visa_Type" class="block text-sm font-bold text-gray-700 mb-2">Tipe Visa <span class="text-red-500">*</span></label>
                            <input type="text" name="Visa_Type" id="Visa_Type" value="{{ old('Visa_Type') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Contoh: Tokutei Ginou" required>
                            @error('Visa_Type') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Embassy" class="block text-sm font-bold text-gray-700 mb-2">Kedutaan / Konsulat <span class="text-red-500">*</span></label>
                            <input type="text" name="Embassy" id="Embassy" value="{{ old('Embassy') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Contoh: Kedutaan Besar Jepang, Jakarta" required>
                            @error('Embassy') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 3. Timeline & Status -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">3. Timeline & Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label for="Submission_Date" class="block text-sm font-bold text-gray-700 mb-2">Tgl Pengajuan (Submit)</label>
                            <input type="date" name="Submission_Date" id="Submission_Date" value="{{ old('Submission_Date') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Submission_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Approval_Date" class="block text-sm font-bold text-gray-700 mb-2">Tgl Disetujui (Approved)</label>
                            <input type="date" name="Approval_Date" id="Approval_Date" value="{{ old('Approval_Date') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Approval_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Issue_Date" class="block text-sm font-bold text-gray-700 mb-2">Tgl Terbit (Issue)</label>
                            <input type="date" name="Issue_Date" id="Issue_Date" value="{{ old('Issue_Date') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Issue_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Expiry_Date" class="block text-sm font-bold text-gray-700 mb-2">Tgl Kedaluwarsa</label>
                            <input type="date" name="Expiry_Date" id="Expiry_Date" value="{{ old('Expiry_Date') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Expiry_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="Visa_Status" class="block text-sm font-bold text-gray-700 mb-2">Status Visa <span class="text-red-500">*</span></label>
                            <select name="Visa_Status" id="Visa_Status" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="PREPARING" {{ old('Visa_Status') == 'PREPARING' ? 'selected' : '' }}>PREPARING (Persiapan Berkas)</option>
                                <option value="SUBMITTED" {{ old('Visa_Status') == 'SUBMITTED' ? 'selected' : '' }}>SUBMITTED (Diajukan)</option>
                                <option value="APPROVED" {{ old('Visa_Status') == 'APPROVED' ? 'selected' : '' }}>APPROVED (Disetujui / Terbit)</option>
                                <option value="REJECTED" {{ old('Visa_Status') == 'REJECTED' ? 'selected' : '' }}>REJECTED (Ditolak)</option>
                            </select>
                            @error('Visa_Status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 4. Catatan Tambahan -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">4. Catatan</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="Remarks" class="block text-sm font-bold text-gray-700 mb-2">Remarks</label>
                            <textarea name="Remarks" id="Remarks" rows="2" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Keterangan tambahan (ditampilkan)">{{ old('Remarks') }}</textarea>
                        </div>

                        <div>
                            <label for="Notes" class="block text-sm font-bold text-gray-700 mb-2">Catatan Internal WMS</label>
                            <textarea name="Notes" id="Notes" rows="2" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Catatan khusus untuk internal staff">{{ old('Notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white p-4 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] border-t">
                    <a href="{{ route('visas.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Data Visa</span>
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
