@extends('layouts.app')
@section('header', 'Detail Kelas (Rombel)')
@section('content')

@php
    $isActive = ($class['Is_Active'] ?? 'TRUE') === 'TRUE';
    $statusColor = $isActive ? 'green' : 'red';
    $statusText = $isActive ? 'Sistem Aktif' : 'Sistem Nonaktif';
@endphp

<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout 
        title="{{ $class['Class_Name'] }}" 
        description="Kode: {{ $class['Class_Code'] }} | Program: {{ $class['Program_Name'] }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Kelas' => route('classes.index'), 'Profil' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('classes.edit', $class['Class_ID']) }}" />
            @if($isActive)
                <x-universal.action-button action="delete" url="{{ route('classes.destroy', $class['Class_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Pembelajaran</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Angkatan (Batch)</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $class['Batch_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Wali Kelas</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $class['Teacher_Name'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex flex-col justify-center items-center text-center">
                            <p class="text-xs font-bold text-slate-400 uppercase">Daya Tampung</p>
                            <p class="mt-1 flex items-baseline justify-center gap-1">
                                <span class="text-2xl font-extrabold {{ ($class['Current_Student'] == $class['Capacity']) ? 'text-rose-600' : 'text-slate-800' }}">{{ $class['Current_Student'] }}</span>
                                <span class="text-xs font-bold text-slate-400">/ {{ $class['Capacity'] }}</span>
                            </p>
                            @if($class['Current_Student'] == $class['Capacity'])
                                <span class="mt-1 text-[10px] font-bold text-rose-500 uppercase bg-rose-50 px-2 py-0.5 rounded">KELAS PENUH</span>
                            @endif
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Kelas</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">
                                <x-badge color="blue">{{ $class['Class_Status'] }}</x-badge>
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Deskripsi / Ruang Kelas</h3>
                    <p class="text-sm font-medium text-slate-800 mt-1 whitespace-pre-wrap">{{ $class['Description'] ?: 'Tidak ada deskripsi yang ditambahkan.' }}</p>
                </div>

                @if(!empty($class['Notes']))
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan Internal</h3>
                    <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200 whitespace-pre-wrap">{{ $class['Notes'] }}</p>
                </div>
                @endif
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Record ID</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $class['Class_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($class['Created_At']) ? \Carbon\Carbon::parse($class['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ \App\Helpers\UserResolverHelper::getName($class['Created_By'] ?? '') }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($class['Updated_At']) ? \Carbon\Carbon::parse($class['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $class['Updated_By'] ?? 'Sistem' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
