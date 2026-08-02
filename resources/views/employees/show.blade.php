@extends('layouts.app')
@section('header', 'Detail Karyawan')
@section('content')

@php
    $isActive = ($employee['Is_Active'] ?? 'TRUE') === 'TRUE';
    $statusColor = $isActive ? 'green' : 'red';
    $statusText = $isActive ? 'Pegawai Aktif' : 'Nonaktif';
@endphp

<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout 
        title="{{ $employee['Full_Name'] }}" 
        description="NIK: {{ $employee['Employee_Number'] }} | {{ $employee['Position_Name'] }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard'), 'Data Master' => '#', 'Karyawan' => route('employees.index'), 'Profil' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('employees.edit', $employee['Employee_ID']) }}" />
            @if($isActive)
                <x-universal.action-button action="delete" url="{{ route('employees.destroy', $employee['Employee_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Pekerjaan</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Departemen</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $employee['Department_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Posisi</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $employee['Position_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Pegawai</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Employment_Status'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Bergabung</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($employee['Join_Date']) ? \Carbon\Carbon::parse($employee['Join_Date'])->format('d M Y') : '-' }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Pribadi</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">NIK / No. KTP</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['National_ID'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Jenis Kelamin</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Gender'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tempat, Tanggal Lahir</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">
                                {{ $employee['Birth_Place'] ?: '-' }}, 
                                {{ !empty($employee['Birth_Date']) ? \Carbon\Carbon::parse($employee['Birth_Date'])->format('d F Y') : '-' }}
                            </p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Kontak</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">
                                {{ $employee['Phone_Number'] ?: '-' }}<br>
                                <span class="text-xs text-slate-500">{{ $employee['Email'] ?: '-' }}</span>
                            </p>
                        </div>
                        <div class="sm:col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Alamat Domisili</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Address'] ?: 'Tidak ada informasi alamat.' }}</p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Perbankan & Pajak</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor NPWP</p>
                            <p class="text-sm font-mono font-medium text-slate-800 mt-1">{{ $employee['Tax_Number'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nama Bank</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Bank_Name'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor Rekening</p>
                            <p class="text-sm font-mono font-medium text-slate-800 mt-1">{{ $employee['Bank_Account_Number'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Atas Nama (Pemilik)</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee['Account_Holder_Name'] ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                @if(!empty($employee['Notes']))
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan Khusus</h3>
                    <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200">{{ $employee['Notes'] }}</p>
                </div>
                @endif
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Rekaman</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $employee['Employee_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Pengguna (SSOT)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $employee['User_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($employee['Created_At']) ? \Carbon\Carbon::parse($employee['Created_At'])->format('d M Y, H:i:s') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $employee['Created_By'] ?? 'Sistem' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($employee['Updated_At']) ? \Carbon\Carbon::parse($employee['Updated_At'])->format('d M Y, H:i:s') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $employee['Updated_By'] ?? 'Sistem' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
