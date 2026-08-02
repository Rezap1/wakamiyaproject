@extends('layouts.app')
@section('header', 'Detail Informasi Program')
@section('content')

@php
    $isActive = ($program['Is_Active'] ?? 'TRUE') === 'TRUE';
    $statusColor = $isActive ? 'green' : 'red';
    $statusText = $isActive ? 'Aktif Berjalan' : 'Nonaktif';
@endphp

<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout 
        title="{{ $program['Program_Name'] }}" 
        description="Kategori: {{ $program['Program_Category'] }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Program' => route('programs.index'), 'Profil' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('programs.edit', $program['Program_ID']) }}" />
            @if($isActive)
                <x-universal.action-button action="delete" url="{{ route('programs.destroy', $program['Program_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Program</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Kode Program</p>
                            <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $program['Program_Code'] }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Kategori</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $program['Program_Category'] }}</p>
                        </div>
                        <div class="sm:col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Deskripsi Program</p>
                            <p class="text-sm font-medium text-slate-800 mt-1 whitespace-pre-wrap">{{ $program['Description'] ?: 'Tidak ada deskripsi yang ditambahkan.' }}</p>
                        </div>
                    </div>
                </div>

                @if(!empty($program['Notes']))
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan Internal</h3>
                    <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200 whitespace-pre-wrap">{{ $program['Notes'] }}</p>
                </div>
                @endif
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Record ID</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $program['Program_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($program['Created_At']) ? \Carbon\Carbon::parse($program['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $program['Created_By'] ?? 'Sistem' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($program['Updated_At']) ? \Carbon\Carbon::parse($program['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $program['Updated_By'] ?? 'Sistem' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
