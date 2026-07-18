@extends('layouts.app')

@section('header', 'Tambah Dokumen')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 flex items-center gap-4">
            <a href="{{ route('documents.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h2 class="text-2xl font-extrabold text-gray-900">Tambah Dokumen Baru</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">Unggah dan rekam detail dokumen legal untuk kandidat.</p>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('documents.store') }}" method="POST" class="space-y-10">
                @csrf
                
                <!-- 1. Kepemilikan Dokumen -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">1. Kepemilikan Dokumen</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Student_ID" class="block text-sm font-bold text-gray-700 mb-2">Pemilik Dokumen (Siswa) <span class="text-red-500">*</span></label>
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
                            <label for="Application_ID" class="block text-sm font-bold text-gray-700 mb-2">Terkait Aplikasi (Opsional)</label>
                            <select name="Application_ID" id="Application_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="">-- Pilih Aplikasi Kerja --</option>
                                @foreach($applications as $app)
                                    <option value="{{ $app['Application_ID'] }}" {{ old('Application_ID') == $app['Application_ID'] ? 'selected' : '' }}>
                                        {{ $app['Application_Number'] ?? $app['Application_ID'] }} ({{ $app['Application_Status'] ?? 'Unknown' }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Isi jika dokumen ini khusus digunakan untuk aplikasi pekerjaan tertentu.</p>
                            @error('Application_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 2. Detail Dokumen -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">2. Detail Dokumen</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Document_Number" class="block text-sm font-bold text-gray-700 mb-2">Nomor Dokumen (Opsional)</label>
                            <input type="text" name="Document_Number" id="Document_Number" value="{{ old('Document_Number') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Cth: PASSPORT-12345">
                            @error('Document_Number') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Document_Type" class="block text-sm font-bold text-gray-700 mb-2">Tipe Dokumen <span class="text-red-500">*</span></label>
                            <select name="Document_Type" id="Document_Type" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Tipe --</option>
                                <option value="PASSPORT" {{ old('Document_Type') == 'PASSPORT' ? 'selected' : '' }}>Passport</option>
                                <option value="VISA" {{ old('Document_Type') == 'VISA' ? 'selected' : '' }}>Visa</option>
                                <option value="CONTRACT" {{ old('Document_Type') == 'CONTRACT' ? 'selected' : '' }}>Kontrak Kerja</option>
                                <option value="MEDICAL" {{ old('Document_Type') == 'MEDICAL' ? 'selected' : '' }}>Medical Checkup</option>
                                <option value="CERTIFICATE" {{ old('Document_Type') == 'CERTIFICATE' ? 'selected' : '' }}>Sertifikat/Ijazah</option>
                                <option value="OTHER" {{ old('Document_Type') == 'OTHER' ? 'selected' : '' }}>Lainnya</option>
                            </select>
                            @error('Document_Type') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="Document_Name" class="block text-sm font-bold text-gray-700 mb-2">Nama Dokumen <span class="text-red-500">*</span></label>
                            <input type="text" name="Document_Name" id="Document_Name" value="{{ old('Document_Name') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Cth: Paspor Budi Santoso 2023" required>
                            @error('Document_Name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="File_Name" class="block text-sm font-bold text-gray-700 mb-2">Nama File (Opsional)</label>
                            <input type="text" name="File_Name" id="File_Name" value="{{ old('File_Name') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Cth: passport_budi.pdf">
                            @error('File_Name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="File_URL" class="block text-sm font-bold text-gray-700 mb-2">Link Dokumen / Tautan (Opsional)</label>
                            <input type="url" name="File_URL" id="File_URL" value="{{ old('File_URL') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="https://drive.google.com/...">
                            @error('File_URL') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 3. Validitas & Status -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">3. Validitas & Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="Issue_Date" class="block text-sm font-bold text-gray-700 mb-2">Tanggal Terbit (Issue Date)</label>
                            <input type="date" name="Issue_Date" id="Issue_Date" value="{{ old('Issue_Date') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Issue_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Expiry_Date" class="block text-sm font-bold text-gray-700 mb-2">Tanggal Berakhir (Expiry Date)</label>
                            <input type="date" name="Expiry_Date" id="Expiry_Date" value="{{ old('Expiry_Date') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Expiry_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Document_Status" class="block text-sm font-bold text-gray-700 mb-2">Status Verifikasi <span class="text-red-500">*</span></label>
                            <select name="Document_Status" id="Document_Status" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="PENDING" {{ old('Document_Status') == 'PENDING' ? 'selected' : '' }}>Pending (Menunggu Verifikasi)</option>
                                <option value="VERIFIED" {{ old('Document_Status') == 'VERIFIED' ? 'selected' : '' }}>Verified (Disetujui)</option>
                                <option value="REJECTED" {{ old('Document_Status') == 'REJECTED' ? 'selected' : '' }}>Rejected (Ditolak)</option>
                                <option value="EXPIRED" {{ old('Document_Status') == 'EXPIRED' ? 'selected' : '' }}>Expired (Kedaluwarsa)</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Set ke 'Verified' untuk otomatis mencatat nama Anda sebagai Verifikator.</p>
                            @error('Document_Status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 4. Catatan -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">4. Catatan Tambahan</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="Remarks" class="block text-sm font-bold text-gray-700 mb-2">Remarks</label>
                            <textarea name="Remarks" id="Remarks" rows="2" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" placeholder="Keterangan tambahan untuk kandidat">{{ old('Remarks') }}</textarea>
                        </div>

                        <div>
                            <label for="Notes" class="block text-sm font-bold text-gray-700 mb-2">Catatan Internal WMS</label>
                            <textarea name="Notes" id="Notes" rows="2" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white p-4 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] border-t">
                    <a href="{{ route('documents.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Dokumen</span>
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
