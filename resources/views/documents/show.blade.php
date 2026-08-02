@extends('layouts.app')
@section('header', 'Detail Dokumen')
@section('content')

@php
    $tab = request('tab', 'informasi');
    
    $status = $document['Document_Status'] ?? 'PENDING';
    $statusColor = match($status) {
        'VERIFIED' => 'green',
        'PENDING' => 'amber',
        'REJECTED' => 'red',
        'EXPIRED' => 'slate',
        default => 'slate'
    };
@endphp

<x-universal.detail-layout 
    title="{{ $document['Document_Name'] ?? 'Dokumen Tidak Diketahui' }}" 
    subtitle="{{ $document['Document_Type'] ?? 'Lainnya' }} • ID: {{ $document['Document_Number'] ?? $document['Document_ID'] }}"
    status="{{ $status ?: 'TIDAK DIKETAHUI' }}"
    statusColor="{{ $statusColor }}"
    avatarInitials="{{ substr($document['Document_Name'] ?? 'D', 0, 1) }}"
    activeTab="{{ $tab }}"
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Pemasaran' => '#', 'Dokumen' => route('documents.index'), 'Detail' => '#']"
>
    
    <x-slot:headerActions>
        @if(!empty($document['File_URL']))
            <x-universal.action-button action="view" url="{{ $document['File_URL'] }}" label="Buka File" target="_blank" />
        @endif
        <x-universal.action-button action="edit" url="{{ route('documents.edit', $document['Document_ID']) }}" />
    </x-slot:headerActions>

    <x-slot:sidebarContent>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Pemilik Dokumen</p>
            <p class="text-sm font-bold text-slate-800 mt-0.5">{{ $document['Student_Name'] ?? 'Siswa Tidak Diketahui' }}</p>
            <p class="text-[11px] text-slate-500 mt-0.5">NIS: {{ $document['Student_Registration_Number'] ?? '-' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Status Validitas</p>
            @php
                $isExpired = !empty($document['Expiry_Date']) && \Carbon\Carbon::parse($document['Expiry_Date'])->isPast();
                $expClass = $isExpired ? 'text-rose-600 font-bold' : 'text-slate-800 font-bold';
            @endphp
            <p class="text-sm {{ $expClass }} mt-0.5">
                Exp: {{ !empty($document['Expiry_Date']) ? \Carbon\Carbon::parse($document['Expiry_Date'])->format('d M Y') : 'Tidak ada' }}
            </p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Info File</p>
            @if(!empty($document['File_URL']))
                <a href="{{ $document['File_URL'] }}" target="_blank" class="text-sm font-medium text-blue-600 hover:underline flex items-center gap-1 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    {{ $document['File_Name'] ?? 'Lihat Berkas' }}
                </a>
            @else
                <p class="text-sm font-medium text-slate-400 mt-0.5">Tidak ada file</p>
            @endif
        </div>
    </x-slot:sidebarContent>

    @if($tab === 'informasi')
        <div class="space-y-8">
            <!-- Data Relasi Tambahan -->
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Data Terkait</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Dokumen</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $document['Document_ID'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Terbit</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($document['Issue_Date']) ? \Carbon\Carbon::parse($document['Issue_Date'])->format('d M Y') : '-' }}</p>
                    </div>

                </div>
            </div>

            <!-- Notes -->
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan & Keterangan</h3>
                <div class="grid grid-cols-1 gap-4">
                    @if($document['Document_Status'] === 'VERIFIED')
                    <div class="bg-emerald-50 p-4 rounded-xl border border-emerald-100">
                        <p class="text-xs font-bold text-emerald-700 uppercase mb-2">Informasi Verifikasi</p>
                        <p class="text-sm font-medium text-emerald-800">Diverifikasi oleh <strong>{{ $document['Verified_By'] ?? 'Tidak Diketahui' }}</strong> pada {{ !empty($document['Verification_Date']) ? \Carbon\Carbon::parse($document['Verification_Date'])->format('d M Y') : '-' }}</p>
                    </div>
                    @endif
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase mb-2">Keterangan</p>
                        <p class="text-sm font-medium text-slate-800 whitespace-pre-line">{{ $document['Remarks'] ?: 'Tidak ada remarks' }}</p>
                    </div>
                    @if($document['Notes'])
                    <div class="bg-amber-50 p-4 rounded-xl border border-amber-100">
                        <p class="text-xs font-bold text-amber-700 uppercase mb-2">Catatan Internal WMS</p>
                        <p class="text-sm font-medium text-amber-900 whitespace-pre-line">{{ $document['Notes'] }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    @elseif($tab === 'audit')
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Log Sistem</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">ID Rekam</p>
                    <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $document['Document_ID'] }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($document['Created_At']) ? \Carbon\Carbon::parse($document['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs font-medium text-slate-500 mt-1">Oleh: {{ $document['Created_By'] ?? 'Sistem' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Diupdate</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($document['Updated_At']) ? \Carbon\Carbon::parse($document['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs font-medium text-slate-500 mt-1">Oleh: {{ $document['Updated_By'] ?? 'Sistem' }}</p>
                </div>
            </div>
        </div>
    @else
        <x-universal.empty-state title="Belum Ada Data" description="Data untuk tab ini belum tersedia atau sedang dikembangkan." />
    @endif

</x-universal.detail-layout>

@endsection
