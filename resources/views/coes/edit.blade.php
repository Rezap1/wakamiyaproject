@extends('layouts.app')

@section('header', 'Edit COE')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('coes.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900">Edit Data COE</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Perbarui data atau ubah status pengajuan untuk COE <span class="font-bold text-gray-700">{{ $coe['COE_Number'] ?? $coe['COE_ID'] }}</span>.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('coes.show', $coe['COE_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Lihat Detail
                </a>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('coes.update', $coe['COE_ID']) }}" method="POST" class="space-y-10">
                @csrf
                @method('PUT')
                
                <!-- 1. Data Utama & Relasi -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">1. Data Kandidat & Relasi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="COE_Number" class="block text-sm font-bold text-gray-700 mb-2">No. COE <span class="text-red-500">*</span></label>
                            <input type="text" name="COE_Number" id="COE_Number" value="{{ old('COE_Number', $coe['COE_Number'] ?? '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                            @error('COE_Number') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Application_ID" class="block text-sm font-bold text-gray-700 mb-2">Aplikasi Pekerjaan Terkait (Opsional)</label>
                            <select name="Application_ID" id="Application_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                                <option value="">-- Pilih Aplikasi Kerja --</option>
                                @foreach($applications as $app)
                                    <option value="{{ $app['Application_ID'] }}" {{ old('Application_ID', $coe['Application_ID'] ?? '') == $app['Application_ID'] ? 'selected' : '' }}>
                                        {{ $app['Application_Number'] ?? $app['Application_ID'] }} ({{ $app['Application_Status'] ?? 'Unknown' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('Application_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Student_ID" class="block text-sm font-bold text-gray-700 mb-2">Siswa / Kandidat <span class="text-red-500">*</span></label>
                            <select name="Student_ID" id="Student_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Siswa --</option>
                                @foreach($students as $student)
                                    <option value="{{ $student['Student_ID'] }}" {{ old('Student_ID', $coe['Student_ID'] ?? '') == $student['Student_ID'] ? 'selected' : '' }}>
                                        {{ $student['Registration_Number'] ?? $student['Student_ID'] }} - {{ $student['Full_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Student_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Company_ID" class="block text-sm font-bold text-gray-700 mb-2">Perusahaan <span class="text-red-500">*</span></label>
                            <select name="Company_ID" id="Company_ID" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="">-- Pilih Perusahaan --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company['Company_ID'] }}" {{ old('Company_ID', $coe['Company_ID'] ?? '') == $company['Company_ID'] ? 'selected' : '' }}>
                                        {{ $company['Company_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Company_ID') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 2. Timeline & Status -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">2. Timeline & Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label for="Application_Date" class="block text-sm font-bold text-gray-700 mb-2">Tgl Persiapan</label>
                            <input type="date" name="Application_Date" id="Application_Date" value="{{ old('Application_Date', !empty($coe['Application_Date']) ? \Carbon\Carbon::parse($coe['Application_Date'])->format('Y-m-d') : '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Application_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Submission_Date" class="block text-sm font-bold text-gray-700 mb-2">Tgl Pengajuan (Submit)</label>
                            <input type="date" name="Submission_Date" id="Submission_Date" value="{{ old('Submission_Date', !empty($coe['Submission_Date']) ? \Carbon\Carbon::parse($coe['Submission_Date'])->format('Y-m-d') : '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Submission_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Approval_Date" class="block text-sm font-bold text-gray-700 mb-2">Tgl Disetujui (Approved)</label>
                            <input type="date" name="Approval_Date" id="Approval_Date" value="{{ old('Approval_Date', !empty($coe['Approval_Date']) ? \Carbon\Carbon::parse($coe['Approval_Date'])->format('Y-m-d') : '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Approval_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="COE_Expiry_Date" class="block text-sm font-bold text-gray-700 mb-2">Tgl Kedaluwarsa</label>
                            <input type="date" name="COE_Expiry_Date" id="COE_Expiry_Date" value="{{ old('COE_Expiry_Date', !empty($coe['COE_Expiry_Date']) ? \Carbon\Carbon::parse($coe['COE_Expiry_Date'])->format('Y-m-d') : '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('COE_Expiry_Date') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="COE_Status" class="block text-sm font-bold text-gray-700 mb-2">Status COE <span class="text-red-500">*</span></label>
                            <select name="COE_Status" id="COE_Status" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors" required>
                                <option value="PREPARING" {{ old('COE_Status', $coe['COE_Status'] ?? '') == 'PREPARING' ? 'selected' : '' }}>PREPARING (Persiapan Berkas)</option>
                                <option value="SUBMITTED" {{ old('COE_Status', $coe['COE_Status'] ?? '') == 'SUBMITTED' ? 'selected' : '' }}>SUBMITTED (Diajukan ke Imigrasi)</option>
                                <option value="APPROVED" {{ old('COE_Status', $coe['COE_Status'] ?? '') == 'APPROVED' ? 'selected' : '' }}>APPROVED (Disetujui)</option>
                                <option value="REJECTED" {{ old('COE_Status', $coe['COE_Status'] ?? '') == 'REJECTED' ? 'selected' : '' }}>REJECTED (Ditolak)</option>
                            </select>
                            @error('COE_Status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="Immigration_Office" class="block text-sm font-bold text-gray-700 mb-2">Kantor Imigrasi (Opsional)</label>
                            <input type="text" name="Immigration_Office" id="Immigration_Office" value="{{ old('Immigration_Office', $coe['Immigration_Office'] ?? '') }}" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">
                            @error('Immigration_Office') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- 3. Catatan Tambahan -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">3. Catatan</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="Remarks" class="block text-sm font-bold text-gray-700 mb-2">Remarks</label>
                            <textarea name="Remarks" id="Remarks" rows="2" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Remarks', $coe['Remarks'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label for="Notes" class="block text-sm font-bold text-gray-700 mb-2">Catatan Internal WMS</label>
                            <textarea name="Notes" id="Notes" rows="2" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes', $coe['Notes'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white p-4 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] border-t">
                    <a href="{{ route('coes.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Simpan Perubahan</span>
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
