@extends('layouts.app')

@section('header', 'Manajemen Posisi')

@section('content')
<x-universal.index-layout 
    title="Daftar Posisi (Jabatan)" 
    description="Kelola data posisi dan penugasannya ke departemen."
    :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'Posisi' => route('positions.index')]"
    add-action="{{ route('positions.create') }}"
    add-text="Tambah Posisi Baru"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="positions" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('positions.index') }}" 
            refresh-url="{{ route('positions.index') }}"
            export-url="{{ route('positions.export-pdf') }}"
        >
            <div class="w-full md:w-auto">
                <select name="department" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Departemen</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept['Department_ID'] }}" {{ request('department') == $dept['Department_ID'] ? 'selected' : '' }}>{{ $dept['Department_Name'] }}</option>
                    @endforeach
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($positions) === 0" empty-title="Belum ada data posisi" empty-description="Data dari Google Sheets masih kosong.">
        <x-slot:header>
            <th class="px-6 py-4 text-left">ID / Kode</th>
            <th class="px-6 py-4 text-left">Nama Posisi</th>
            <th class="px-6 py-4 text-left">Departemen</th>
            <th class="px-6 py-4 text-left">Level</th>
            <th class="px-6 py-4 text-left">Dibuat Pada</th>
            <th class="px-6 py-4 text-left">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($positions as $position)
            @php
                $status = ($position['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif';
                $badgeColor = $status === 'Aktif' ? 'green' : 'red';
            @endphp
            <tr class="hover:bg-slate-50 transition-colors {{ $status === 'Nonaktif' ? 'opacity-50' : '' }}">
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800">{{ $position['Position_Code'] }}</div>
                    <div class="text-xs text-slate-500">{{ $position['Position_ID'] }}</div>
                </td>
                <td class="px-6 py-4 font-bold text-slate-800">{{ $position['Position_Name'] }}</td>
                <td class="px-6 py-4"><span class="bg-slate-100 text-slate-700 px-2.5 py-1 rounded-lg text-xs font-bold">{{ $position['Department_Name'] }}</span></td>
                <td class="px-6 py-4 font-medium text-slate-700">{{ $position['Position_Level'] }}</td>
                <td class="px-6 py-4">
                    <div class="text-sm font-medium">{{ !empty($position['Created_At']) ? \Carbon\Carbon::parse($position['Created_At'])->format('d M Y, H:i') : '-' }}</div>
                    <div class="text-xs text-slate-500">{{ $position['Created_By'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                    <x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <x-universal.action-button action="detail" url="{{ route('positions.show', $position['Position_ID']) }}" />
                        <x-universal.action-button action="edit" url="{{ route('positions.edit', $position['Position_ID']) }}" />
                        @if($status === 'Aktif')
                        <x-universal.action-button action="delete" url="{{ route('positions.destroy', $position['Position_ID']) }}" />
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($positions, 'links'))
                <x-universal.pagination :paginator="$positions" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>
</x-universal.index-layout>
@endsection
