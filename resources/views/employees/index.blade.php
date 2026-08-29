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

    @if(($employeeGroups ?? collect())->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            @foreach($employeeGroups as $group)
                <details class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden group">
                    <summary class="cursor-pointer list-none p-4 flex items-center justify-between gap-3 hover:bg-slate-50">
                        <div>
                            <h3 class="text-sm font-black text-slate-800">{{ $group['title'] }}</h3>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $group['subtitle'] }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="px-2.5 py-1 rounded-lg bg-sky-50 text-sky-700 text-xs font-black">{{ $group['total'] }} pegawai</span>
                            <span class="text-slate-400 group-open:rotate-180 transition-transform">v</span>
                        </div>
                    </summary>
                    <div class="border-t border-slate-100 p-4 bg-slate-50/50">
                        <div class="flex flex-wrap gap-2 mb-3 text-xs font-bold">
                            <span class="px-2 py-1 rounded bg-emerald-50 text-emerald-700">Aktif: {{ $group['active'] }}</span>
                            <span class="px-2 py-1 rounded bg-slate-100 text-slate-600">Nonaktif: {{ $group['inactive'] }}</span>
                        </div>
                        <div class="divide-y divide-slate-200 bg-white rounded-lg border border-slate-200 overflow-hidden">
                            @foreach($group['items'] as $employee)
                                <a href="{{ route('employees.show', $employee['Employee_ID']) }}" class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-sky-50">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-800 truncate">{{ $employee['Full_Name'] ?? \App\Helpers\UserResolverHelper::getName($employee['User_ID'] ?? '') }}</p>
                                        <p class="text-[11px] text-slate-500 font-mono">{{ $employee['Employee_Number'] ?? $employee['Employee_ID'] ?? '-' }}</p>
                                    </div>
                                    <span class="text-[11px] font-bold text-slate-500 shrink-0">{{ $employee['Position_Name'] ?? '-' }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>
    @endif

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
                        $empName = !empty($employee['Full_Name']) ? $employee['Full_Name'] : \App\Helpers\UserResolverHelper::getName($employee['User_ID'] ?? '');
                        $rawPhoto = $employee['Profile_Photo'] ?? '';
                        if (!empty($rawPhoto)) {
                            $avatarUrl = str_starts_with($rawPhoto, 'http') ? $rawPhoto : asset($rawPhoto);
                        } else {
                            $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($empName ?: 'Karyawan') . '&background=0D8ABC&color=fff&size=80';
                        }
                    @endphp
                    <div class="w-10 h-10 rounded-full overflow-hidden border border-slate-200 shrink-0 bg-slate-100 shadow-sm">
                        <img src="{{ $avatarUrl }}" alt="{{ $empName }}" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <div class="font-bold text-slate-800">{{ $empName }}</div>
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
                <div class="text-[11px] font-medium text-slate-500 mt-1.5">Bergabung: {{ \App\Helpers\DateHelper::format($employee['Join_Date'] ?? '', 'd M Y') }}</div>
            </td>
            <td class="px-6 py-4">
                <x-badge status="{{ ($employee['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                    {{ ($employee['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}
                </x-badge>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    @if(!empty($employee['Employee_ID']))
                        <x-universal.action-button action="detail" url="{{ route('employees.show', $employee['Employee_ID']) }}" />
                        <x-universal.action-button action="edit" url="{{ route('employees.edit', $employee['Employee_ID']) }}" />
                        @if(($employee['Is_Active'] ?? 'TRUE') === 'TRUE')
                            <x-universal.action-button action="delete" url="{{ route('employees.destroy', $employee['Employee_ID']) }}" />
                        @endif
                    @else
                        <span class="text-xs font-semibold text-slate-400">Tidak tersedia</span>
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
