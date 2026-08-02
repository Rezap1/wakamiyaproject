@extends('layouts.app')
@section('header', 'Edit Dokumen')
@section('content')

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.form 
        action="{{ route('documents.update', $document['Document_ID']) }}" 
        method="PUT"
        title="Edit Dokumen" 
        description="Ubah data atau perbarui status verifikasi untuk dokumen {{ $document['Document_Number'] ?? $document['Document_ID'] }}."
        buttonText="Simpan Perubahan"
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
                            value="{{ old('Student_ID', $document['Student_ID'] ?? '') }}" 
                        />
                    </div>
                </div>
            </div>

            <!-- 2. Detail Dokumen -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">2. Detail Dokumen</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input name="Document_Number" label="Nomor Dokumen (Opsional)" value="{{ $document['Document_Number'] ?? '' }}" />
                    
                    <x-universal.select 
                        name="Document_Type" 
                        label="Tipe Dokumen" 
                        :options="['' => '-- Pilih Tipe --', 'INVOICE' => 'Faktur', 'RECEIPT' => 'Kwitansi', 'PAYROLL_SLIP' => 'Slip Gaji', 'ACADEMIC_REPORT' => 'Laporan Akademik', 'CERTIFICATE' => 'Sertifikat', 'CONTRACT' => 'Kontrak Kerja', 'MEDICAL' => 'Pemeriksaan Kesehatan', 'OTHER' => 'Lainnya']"
                        value="{{ $document['Document_Type'] ?? '' }}"
                        :required="true"
                    />

                    <div class="md:col-span-2">
                        <x-universal.input name="Document_Name" label="Nama Dokumen" value="{{ $document['Document_Name'] ?? '' }}" :required="true" />
                    </div>

                    <x-universal.input name="File_Name" label="Nama File (Opsional)" value="{{ $document['File_Name'] ?? '' }}" />
                    
                    <x-universal.input type="url" name="File_URL" label="Link Dokumen / Tautan (Opsional)" value="{{ $document['File_URL'] ?? '' }}" />
                </div>
            </div>

            <!-- 3. Validitas & Status -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">3. Validitas & Status</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <x-universal.input type="date" name="Issue_Date" label="Tanggal Terbit (Issue Date)" value="{{ !empty($document['Issue_Date']) ? \Carbon\Carbon::parse($document['Issue_Date'])->format('Y-m-d') : '' }}" />
                    <x-universal.input type="date" name="Expiry_Date" label="Tanggal Berakhir (Expiry Date)" value="{{ !empty($document['Expiry_Date']) ? \Carbon\Carbon::parse($document['Expiry_Date'])->format('Y-m-d') : '' }}" />
                    
                    <x-universal.select 
                        name="Document_Status" 
                        label="Status Verifikasi" 
                        :options="['PENDING' => 'Pending (Menunggu Verifikasi)', 'VERIFIED' => 'Verified (Disetujui)', 'REJECTED' => 'Rejected (Ditolak)', 'EXPIRED' => 'Expired (Kedaluwarsa)']"
                        value="{{ $document['Document_Status'] ?? '' }}"
                        :required="true"
                    />
                </div>
            </div>

            <!-- 4. Catatan -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">4. Catatan Tambahan</h3>
                <div class="grid grid-cols-1 gap-6">
                    <x-universal.textarea name="Remarks" label="Keterangan" rows="2" value="{{ $document['Remarks'] ?? '' }}" />
                    <x-universal.textarea name="Notes" label="Catatan Internal WMS" rows="2" value="{{ $document['Notes'] ?? '' }}" />
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
