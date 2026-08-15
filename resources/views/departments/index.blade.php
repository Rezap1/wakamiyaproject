@extends('layouts.app')

@section('header', 'Manajemen Departemen')

@section('content')
<x-universal.index-layout 
    title="Departemen" 
    description="Kelola data departemen dan struktur organisasi perusahaan."
    :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'Departemen' => route('departments.index')]"
    add-action="{{ route('departments.create') }}"
    add-text="Tambah Departemen Baru"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="departments" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('departments.index') }}" 
            refresh-url="{{ route('departments.index') }}"
            export-url="{{ route('departments.export-pdf') }}"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($departments) === 0" empty-title="Tidak ada departemen ditemukan" empty-description="Data dari Google Sheets masih kosong.">
        <x-slot:header>
            <th class="px-6 py-4">ID Departemen / Kode</th>
            <th class="px-6 py-4">Nama Departemen</th>
            <th class="px-6 py-4">Manajer / Pimpinan</th>
            <th class="px-6 py-4">Dibuat Pada</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($departments as $department)
            @php
                $status = ($department['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif';
                $badgeColor = $status === 'Aktif' ? 'green' : 'red';
            @endphp
            <tr class="hover:bg-slate-50 transition-colors {{ $status === 'Nonaktif' ? 'opacity-50' : '' }}">
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800">{{ $department['Department_ID'] ?? '-' }}</div>
                    <div class="text-xs text-slate-500">{{ $department['Department_Code'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800">{{ $department['Department_Name'] ?? '-' }}</div>
                    <div class="text-xs text-slate-500">{{ Str::limit($department['Notes'] ?? '-', 30) }}</div>
                </td>
                <td class="px-6 py-4 text-sm font-bold text-slate-700">
                    {{ !empty($department['Manager_Name']) && $department['Manager_Name'] !== '-' ? $department['Manager_Name'] : ($department['Manager_Employee_ID'] ?: '-') }}
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm font-medium">{{ !empty($department['Created_At']) ? \Carbon\Carbon::parse($department['Created_At'])->format('d M Y, H:i') : '-' }}</div>
                    <div class="text-xs text-slate-500">{{ !empty($department['Created_By_Name']) && $department['Created_By_Name'] !== '-' ? $department['Created_By_Name'] : ($department['Created_By'] ?? '-') }}</div>
                </td>
                <td class="px-6 py-4">
                    <x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <x-universal.action-button action="detail" url="{{ route('departments.show', $department['Department_ID']) }}" />
                        <x-universal.action-button action="edit" url="{{ route('departments.edit', $department['Department_ID']) }}" />
                        <x-universal.action-button action="delete" url="{{ route('departments.destroy', $department['Department_ID']) }}" />
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($departments, 'links'))
                <x-universal.pagination :paginator="$departments" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>
@endsection
