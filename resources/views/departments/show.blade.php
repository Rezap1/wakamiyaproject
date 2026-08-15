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
                                    <dt class="text-sm font-medium text-slate-500">Manajer / Pimpinan</dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ !empty($department['Manager_Name']) && $department['Manager_Name'] !== '-' ? $department['Manager_Name'] : ($department['Manager_Employee_ID'] ?: 'Belum ditentukan') }}
                                    </dd>
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
                            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $department['Notes'] ?: 'Tidak ada catatan.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <span class="text-xs font-medium text-slate-400 block uppercase">Dibuat Pada</span>
                    <span class="text-sm font-bold text-slate-700 mt-1 block">{{ !empty($department['Created_At']) ? \Carbon\Carbon::parse($department['Created_At'])->format('d M Y, H:i') : '-' }}</span>
                    <span class="text-xs text-slate-500">Oleh: {{ !empty($department['Created_By_Name']) && $department['Created_By_Name'] !== '-' ? $department['Created_By_Name'] : ($department['Created_By'] ?? '-') }}</span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <span class="text-xs font-medium text-slate-400 block uppercase">Diperbarui Pada</span>
                    <span class="text-sm font-bold text-slate-700 mt-1 block">{{ !empty($department['Updated_At']) ? \Carbon\Carbon::parse($department['Updated_At'])->format('d M Y, H:i') : '-' }}</span>
                    <span class="text-xs text-slate-500">Oleh: {{ $department['Updated_By'] ?? '-' }}</span>
                </div>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
