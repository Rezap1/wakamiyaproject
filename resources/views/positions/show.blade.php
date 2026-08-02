@extends('layouts.app')

@section('header', 'Detail Posisi')

@section('content')
<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout
        title="{{ $position['Position_Name'] ?? '-' }}"
        description="ID: {{ $position['Position_ID'] ?? '-' }} | Kode: {{ $position['Position_Code'] ?? '-' }}"
        :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'Posisi' => route('positions.index'), 'Detail' => '#']"
        status="{{ ($position['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}"
        badgeColor="{{ ($position['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'green' : 'red' }}"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('positions.edit', $position['Position_ID']) }}" />
            @if(($position['Is_Active'] ?? 'TRUE') === 'TRUE')
            <x-universal.action-button action="delete" url="{{ route('positions.destroy', $position['Position_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Kolom 1 -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Informasi Dasar</h3>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-slate-500">ID Posisi</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Position_ID'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500">Kode Posisi</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Position_Code'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500">Nama Posisi</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Position_Name'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500">Level Jabatan</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Position_Level'] ?? '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Struktur Organisasi</h3>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <dl>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500">Departemen Induk</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Department_Name'] ?? 'Tidak Diketahui' }}</dd>
                                    <dd class="mt-0.5 text-xs text-slate-500">ID: {{ $position['Department_ID'] ?? '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Kolom 2 -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Catatan</h3>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 min-h-[100px]">
                            <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $position['Notes'] ?: 'Tidak ada catatan.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="bg-slate-50 rounded-xl p-6 border border-slate-100">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Dibuat Pada</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Created_At'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Dibuat Oleh</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Created_By'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Terakhir Diperbarui</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Updated_At'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Diperbarui Oleh</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-900">{{ $position['Updated_By'] ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
