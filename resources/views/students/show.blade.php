@extends('layouts.app')
@section('header', 'Profil Siswa')
@section('content')

@php
    $isActive = ($student['Is_Active'] ?? 'TRUE') === 'TRUE';
    $statusColor = $isActive ? 'green' : 'red';
    $statusText = $isActive ? 'Active' : 'Inactive';
@endphp

<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout 
        title="{{ $student['Full_Name'] }}" 
        description="NIS: {{ $student['Student_Number'] }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Siswa' => route('students.index'), 'Profil' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('students.edit', $student['Student_ID']) }}" />
            @if($isActive)
                <x-universal.action-button action="delete" url="{{ route('students.destroy', $student['Student_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Penempatan Akademik</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Program</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $student['Program_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Kelas / Batch</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['Class_Name'] }} - {{ $student['Batch_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Pendidikan</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['Enrollment_Status'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Kelulusan</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['Graduation_Status'] ?? 'Belum Lulus' }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Data Kependudukan</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">NIK / No. KTP</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['National_ID'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Jenis Kelamin</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['Gender'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tempat, Tanggal Lahir</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">
                                {{ $student['Birth_Place'] ?: '-' }}, 
                                {{ !empty($student['Birth_Date']) ? \Carbon\Carbon::parse($student['Birth_Date'])->translatedFormat('d F Y') : '-' }}
                            </p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Pendidikan Terakhir</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['Education'] }}</p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Kontak & Alamat</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor Telepon</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['Phone_Number'] ?: '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Email</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['Email'] ?: '-' }}</p>
                        </div>
                        <div class="sm:col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Alamat Tinggal</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $student['Address'] ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                @if(!empty($student['Notes']))
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan Khusus</h3>
                    <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200">{{ $student['Notes'] }}</p>
                </div>
                @endif
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Record ID</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $student['Student_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">User ID (SSOT)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $student['User_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($student['Created_At']) ? \Carbon\Carbon::parse($student['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ \App\Helpers\UserResolverHelper::getName($student['Created_By'] ?? '') }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($student['Updated_At']) ? \Carbon\Carbon::parse($student['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $student['Updated_By'] ?? 'Sistem' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
