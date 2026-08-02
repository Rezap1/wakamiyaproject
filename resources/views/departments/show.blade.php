@extends('layouts.app')

@section('header', 'Detail Departemen')

@section('content')
<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout
        title="{{ $department['Department_Name'] ?? '-' }}"
        description="ID: {{ $department['Department_ID'] ?? '-' }} | Kode: {{ $department['Department_Code'] ?? '-' }}"
        :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'Departemen' => route('departments.index'), 'Detail' => '#']"
        status="{{ ($department['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}"
        badgeColor="{{ ($department['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'green' : 'red' }}"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('departments.edit', $department['Department_ID']) }}" />
            <x-universal.action-button action="delete" url="{{ route('departments.destroy', $department['Department_ID']) }}" />
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
                                    <dt class="text-sm font-medium text-slate-500">ID Departemen</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $department['Department_ID'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500">Kode Departemen</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $department['Department_Code'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500">Nama Departemen</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $department['Department_Name'] ?? '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Manajemen</h3>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <dl>
                                <div>
                                    <dt class="text-sm font-medium text-slate-500">ID Manajer (Karyawan)</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $department['Manager_Employee_ID'] ?: 'Belum ditentukan' }}</dd>
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
                            <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $department['Notes'] ?: 'Tidak ada catatan.' }}</p>
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
                        <dd class="mt-1 text-sm font-bold text-slate-900">{{ $department['Created_At'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Dibuat Oleh</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-900">{{ $department['Created_By'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Terakhir Diperbarui</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-900">{{ $department['Updated_At'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Diperbarui Oleh</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-900">{{ $department['Updated_By'] ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
