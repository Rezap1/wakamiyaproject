@extends('layouts.app')
@section('header', 'Manajemen Tenaga Pendidik (Guru)')
@section('content')

<x-universal.index-layout 
    title="Daftar Tenaga Pendidik" 
    description="Kelola data tenaga pendidik, spesialisasi mengajar, dan status."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Guru' => route('teachers.index')]"
    add-action="{{ route('teachers.create') }}"
    add-text="Tambah Guru"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="teachers" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('teachers.index') }}" 
            refresh-url="{{ route('teachers.index') }}"
            export-url="{{ route('teachers.export-pdf') }}"
        >
            <div class="w-full md:w-auto">
                <select name="teaching" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="all" {{ request('teaching') === 'all' ? 'selected' : '' }}>Semua Status Mengajar</option>
                    <option value="Aktif Mengajar" {{ request('teaching') === 'Aktif Mengajar' ? 'selected' : '' }}>Aktif Mengajar</option>
                    <option value="Cuti Mengajar" {{ request('teaching') === 'Cuti Mengajar' ? 'selected' : '' }}>Cuti Mengajar</option>
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($teachers) === 0" empty-title="Data Guru Kosong" empty-description="Belum ada data tenaga pendidik.">
        <x-slot:header>
            <th class="px-6 py-4">Pengajar</th>
            <th class="px-6 py-4">Kontak</th>
            <th class="px-6 py-4">Spesialisasi</th>
            <th class="px-6 py-4">Status Mengajar</th>
            <th class="px-6 py-4">Akses Sistem</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($teachers as $teacher)
        <tr class="hover:bg-slate-50 transition-colors {{ ($teacher['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }}">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-sm border border-slate-200 shrink-0">
                        {{ substr($teacher['Full_Name'] ?? 'U', 0, 1) }}
                    </div>
                    <div>
                        <div class="font-bold text-slate-800">{{ $teacher['Full_Name'] }}</div>
                        <div class="text-[11px] font-bold text-slate-500 mt-0.5">
                            <span class="text-blue-600">{{ $teacher['Teacher_Code'] }}</span> | {{ $teacher['Employee_Number'] }}
                        </div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="font-medium text-slate-800">{{ $teacher['Email'] }}</div>
                <div class="text-xs font-medium text-slate-500 mt-0.5">{{ $teacher['Phone_Number'] }}</div>
            </td>
            <td class="px-6 py-4">
                <x-badge color="slate">{{ $teacher['Specialization'] }}</x-badge>
            </td>
            <td class="px-6 py-4">
                @if(($teacher['Teaching_Status'] ?? '') === 'Aktif Mengajar')
                    <x-badge color="blue">Aktif Mengajar</x-badge>
                @else
                    <x-badge color="orange">{{ $teacher['Teaching_Status'] }}</x-badge>
                @endif
            </td>
            <td class="px-6 py-4">
                <x-badge status="{{ ($teacher['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                    {{ ($teacher['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}
                </x-badge>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="detail" url="{{ route('teachers.show', $teacher['Teacher_ID']) }}" />
                    <x-universal.action-button action="edit" url="{{ route('teachers.edit', $teacher['Teacher_ID']) }}" />
                    @if(($teacher['Is_Active'] ?? 'TRUE') !== 'FALSE')
                        <x-universal.action-button action="delete" url="{{ route('teachers.destroy', $teacher['Teacher_ID']) }}" />
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        
        <x-slot:pagination>
            <x-universal.pagination :paginator="$teachers" />
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection



