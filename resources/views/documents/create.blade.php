@extends('layouts.app')
@section('header', 'Tambah Dokumen')
@section('content')

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.form 
        action="{{ route('documents.store') }}" 
        method="POST"
        title="Tambah Dokumen Baru" 
        description="Unggah dan rekam detail dokumen legal untuk kandidat."
        buttonText="Simpan Dokumen"
    >
@php
    $studentOptions = [];
    if(isset($students)) {
        foreach($students as $s) {
            $studentOptions[$s['Student_ID'] ?? ''] = ($s['Registration_Number'] ?? $s['Student_ID'] ?? '') . ' - ' . ($s['Full_Name'] ?? 'Unknown');
        }
    }
@endphp
        <div class="space-y-8">
            <!-- 1. Kepemilikan Dokumen -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">1. Kepemilikan Dokumen</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-universal.searchable-select 
                            name="Student_ID" 
                            label="Pemilik Dokumen (Siswa)" 
                            :options="$studentOptions" 
                            :required="true" 
                            value="{{ old('Student_ID') }}" 
                        />
                    </div>
                </div>
            </div>

            <!-- 2. Detail Dokumen -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">2. Detail Dokumen</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input name="Document_Number" label="Nomor Dokumen (Opsional)" placeholder="Cth: PASSPORT-12345" />
                    
                    <x-universal.select 
                        name="Document_Type" 
                        label="Tipe Dokumen" 
                        :options="['' => '-- Pilih Tipe --', 'INVOICE' => 'Faktur', 'RECEIPT' => 'Kwitansi', 'PAYROLL_SLIP' => 'Slip Gaji', 'ACADEMIC_REPORT' => 'Laporan Akademik', 'CERTIFICATE' => 'Sertifikat', 'CONTRACT' => 'Kontrak Kerja', 'MEDICAL' => 'Pemeriksaan Kesehatan', 'OTHER' => 'Lainnya']"
                        :required="true"
                    />

                    <div class="md:col-span-2">
                        <x-universal.input name="Document_Name" label="Nama Dokumen" placeholder="Cth: Paspor Budi Santoso 2023" :required="true" />
                    </div>

                    <x-universal.input name="File_Name" label="Nama File (Opsional)" placeholder="Cth: passport_budi.pdf" />
                    
                    <x-universal.input type="url" name="File_URL" label="Link Dokumen / Tautan (Opsional)" placeholder="https://drive.google.com/..." />
                </div>
            </div>

            <!-- 3. Validitas & Status -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">3. Validitas & Status</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <x-universal.input type="date" name="Issue_Date" label="Tanggal Terbit (Issue Date)" />
                    <x-universal.input type="date" name="Expiry_Date" label="Tanggal Berakhir (Expiry Date)" />
                    
                    <div>
                        <label for="Document_Status" class="block mb-1.5 text-[13px] font-bold text-slate-700">Status Verifikasi <span class="text-rose-500">*</span></label>
                        <select name="Document_Status" id="Document_Status" class="block w-full text-sm rounded-xl border-slate-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-white" required>
                            <option value="PENDING" {{ old('Document_Status') == 'PENDING' ? 'selected' : '' }}>Pending (Menunggu Verifikasi)</option>
                            <option value="VERIFIED" {{ old('Document_Status') == 'VERIFIED' ? 'selected' : '' }}>Verified (Disetujui)</option>
                            <option value="REJECTED" {{ old('Document_Status') == 'REJECTED' ? 'selected' : '' }}>Rejected (Ditolak)</option>
                            <option value="EXPIRED" {{ old('Document_Status') == 'EXPIRED' ? 'selected' : '' }}>Expired (Kedaluwarsa)</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Set ke 'Verified' untuk otomatis mencatat nama Anda sebagai Verifikator.</p>
                        @error('Document_Status') <p class="mt-1.5 text-xs font-medium text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- 4. Catatan -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">4. Catatan Tambahan</h3>
                <div class="grid grid-cols-1 gap-6">
                    <x-universal.textarea name="Remarks" label="Keterangan" rows="2" placeholder="Keterangan tambahan untuk kandidat" />
                    <x-universal.textarea name="Notes" label="Catatan Internal WMS" rows="2" />
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
