@extends('layouts.app')
@section('header', 'Manajemen Karyawan')
@section('content')

<x-universal.index-layout 
    title="Daftar Karyawan" 
    description="Kelola data seluruh karyawan, informasi pribadi, foto profil, dan jabatan."
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Data Master' => '#', 'Karyawan' => route('employees.index')]"
    add-action="{{ route('employees.create') }}"
    add-text="Tambah Karyawan"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="employees" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('employees.index') }}" 
            refresh-url="{{ route('employees.index') }}"
            export-url="{{ route('employees.export-pdf') }}"
        >
            <div class="w-full md:w-auto">
                <select name="department" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Departemen</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept['Department_ID'] }}" {{ request('department') == $dept['Department_ID'] ? 'selected' : '' }}>{{ $dept['Department_Name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-auto">
                <select name="position" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="">Semua Posisi</option>
                    @foreach($positions as $pos)
                        <option value="{{ $pos['Position_ID'] }}" {{ request('position') == $pos['Position_ID'] ? 'selected' : '' }} data-dept="{{ $pos['Department_ID'] }}">{{ $pos['Position_Name'] }}</option>
                    @endforeach
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($employees) === 0" empty-title="Data Karyawan Kosong" empty-description="Belum ada karyawan yang terdaftar.">
        <x-slot:header>
            <th class="px-6 py-4">Karyawan</th>
            <th class="px-6 py-4">Kontak</th>
            <th class="px-6 py-4">Departemen & Posisi</th>
            <th class="px-6 py-4">Status Pegawai</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($employees as $employee)
        <tr class="hover:bg-slate-50 transition-colors {{ ($employee['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }}">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    @php
                        $avatarUrl = !empty($employee['Profile_Photo']) ? $employee['Profile_Photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($employee['Full_Name'] ?? 'Emp') . '&background=0D8ABC&color=fff&size=80';
                    @endphp
                    <div class="w-10 h-10 rounded-full overflow-hidden border border-slate-200 shrink-0 bg-slate-100 shadow-sm">
                        <img src="{{ $avatarUrl }}" alt="{{ $employee['Full_Name'] }}" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <div class="font-bold text-slate-800">{{ $employee['Full_Name'] }}</div>
                        <div class="text-xs font-bold text-blue-600 mt-0.5">{{ $employee['Employee_Number'] }}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="font-medium text-slate-800">{{ $employee['Email'] ?: '-' }}</div>
                <div class="text-xs font-medium text-slate-500 mt-0.5">{{ $employee['Phone_Number'] ?: '-' }}</div>
            </td>
            <td class="px-6 py-4">
                <div class="font-bold text-slate-800">{{ $employee['Position_Name'] }}</div>
                <div class="text-xs font-medium text-slate-500 mt-0.5">{{ $employee['Department_Name'] }}</div>
            </td>
            <td class="px-6 py-4">
                <x-badge color="blue">{{ $employee['Employment_Status'] }}</x-badge>
                <div class="text-[11px] font-medium text-slate-500 mt-1.5">Bergabung: {{ $employee['Join_Date'] ? \Carbon\Carbon::parse($employee['Join_Date'])->format('d M Y') : '-' }}</div>
            </td>
            <td class="px-6 py-4">
                <x-badge status="{{ ($employee['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                    {{ ($employee['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}
                </x-badge>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="detail" url="{{ route('employees.show', $employee['Employee_ID'] ?: '0') }}" />
                    <x-universal.action-button action="edit" url="{{ route('employees.edit', $employee['Employee_ID'] ?: '0') }}" />
                    @if(($employee['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <x-universal.action-button action="delete" url="{{ route('employees.destroy', $employee['Employee_ID'] ?: '0') }}" />
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        
        <x-slot:pagination>
            <x-universal.pagination :paginator="$employees" />
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection
