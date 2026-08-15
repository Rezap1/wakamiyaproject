@extends('layouts.app')
@section('header', 'Detail Profil Guru')
@section('content')

@php
    $isActive = ($teacher['Is_Active'] ?? 'TRUE') === 'TRUE';
    $statusColor = $isActive ? 'green' : 'red';
    $statusText = $isActive ? 'Aktif' : 'Nonaktif';
@endphp

<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout 
        title="{{ $teacher['Full_Name'] }}" 
        description="Kode: {{ $teacher['Teacher_Code'] }} | {{ $teacher['Specialization'] }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dashboard' => route('dashboard.hr'), 'Guru' => route('teachers.index'), 'Profil' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('teachers.edit', $teacher['Teacher_ID']) }}" />
            @if($isActive)
                <x-universal.action-button action="delete" url="{{ route('teachers.destroy', $teacher['Teacher_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Pengajaran</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Spesialisasi</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $teacher['Specialization'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Mulai Mengajar</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($teacher['Hire_Date']) ? \Carbon\Carbon::parse($teacher['Hire_Date'])->format('d F Y') : '-' }}</p>
                        </div>
                        <div class="sm:col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Mengajar</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">
                                <x-badge color="{{ ($teacher['Teaching_Status'] ?? '') === 'Aktif Mengajar' ? 'blue' : 'yellow' }}">
                                    {{ $teacher['Teaching_Status'] ?? '-' }}
                                </x-badge>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between items-center mb-4 pb-2 border-b border-slate-100">
                        <h3 class="text-sm font-bold text-slate-700">Informasi Pegawai Tersinkronisasi</h3>
                        @if(!empty($teacher['Employee_ID']))
                            <a href="{{ route('employees.show', $teacher['Employee_ID']) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">Lihat Detail Pegawai</a>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor Induk Pegawai (NIK)</p>
                            <p class="text-sm font-mono font-medium text-slate-800 mt-1">{{ $teacher['Employee_Number'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Jenis Kelamin</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $teacher['Gender'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor Telepon</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $teacher['Phone_Number'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Email</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $teacher['Email'] ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                @if(!empty($teacher['Notes']))
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan Khusus</h3>
                    <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200">{{ $teacher['Notes'] }}</p>
                </div>
                @endif
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Record ID</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $teacher['Teacher_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">User ID (SSOT)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $teacher['User_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ \App\Helpers\DateHelper::format($teacher['Created_At'] ?? '', 'd M Y, H:i:s') }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ \App\Helpers\UserResolverHelper::getName($teacher['Created_By'] ?? '') }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ \App\Helpers\DateHelper::format($teacher['Updated_At'] ?? '', 'd M Y, H:i:s') }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $teacher['Updated_By'] ?? 'Sistem' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
