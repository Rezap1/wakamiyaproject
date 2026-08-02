@extends('layouts.app')
@section('header', 'Detail Angkatan (Batch)')
@section('content')

@php
    $isActive = ($batch['Is_Active'] ?? 'TRUE') === 'TRUE';
    $statusColor = $isActive ? 'green' : 'red';
    $statusText = $isActive ? 'Sistem Aktif' : 'Sistem Nonaktif';
@endphp

<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout 
        title="{{ $batch['Batch_Name'] }}" 
        description="Kode: {{ $batch['Batch_Code'] }} | Program: {{ $batch['Program_Name'] }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Angkatan' => route('batches.index'), 'Profil' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('batches.edit', $batch['Batch_ID']) }}" />
            @if($isActive)
                <x-universal.action-button action="delete" url="{{ route('batches.destroy', $batch['Batch_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Jadwal</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Mulai</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ \Carbon\Carbon::parse($batch['Start_Date'])->format('d M Y') }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Selesai</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ \Carbon\Carbon::parse($batch['End_Date'])->format('d M Y') }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Angkatan</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">
                                <x-badge color="amber">{{ $batch['Batch_Status'] }}</x-badge>
                            </p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Program Induk</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $batch['Program_Name'] }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Deskripsi Angkatan</h3>
                    <p class="text-sm font-medium text-slate-800 mt-1 whitespace-pre-wrap">{{ $batch['Description'] ?: 'Tidak ada deskripsi yang ditambahkan.' }}</p>
                </div>

                @if(!empty($batch['Notes']))
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan Internal</h3>
                    <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200 whitespace-pre-wrap">{{ $batch['Notes'] }}</p>
                </div>
                @endif
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Record ID</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $batch['Batch_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($batch['Created_At']) ? \Carbon\Carbon::parse($batch['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $batch['Created_By'] ?? 'Sistem' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($batch['Updated_At']) ? \Carbon\Carbon::parse($batch['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $batch['Updated_By'] ?? 'Sistem' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
