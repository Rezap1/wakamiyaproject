@extends('layouts.app')
@section('header', 'Manajemen Kelas (Rombel)')
@section('content')

<x-universal.index-layout 
    title="Daftar Kelas Pembelajaran" 
    description="Kelola data kelas yang sedang atau telah berjalan."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Kelas' => route('classes.index')]"
    add-action="{{ route('classes.create') }}"
    add-text="Tambah Kelas"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="classes" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('classes.index') }}" 
            refresh-url="{{ route('classes.index') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto">
                <select name="program_id" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="all" {{ request('program_id') === 'all' ? 'selected' : '' }}>Semua Program</option>
                    @foreach($programs as $program)
                        <option value="{{ $program['Program_ID'] }}" {{ request('program_id') == $program['Program_ID'] ? 'selected' : '' }}>{{ $program['Program_Code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-auto">
                <select name="batch_id" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="all" {{ request('batch_id') === 'all' ? 'selected' : '' }}>Semua Angkatan</option>
                    @foreach($batches as $batch)
                        <option value="{{ $batch['Batch_ID'] }}" {{ request('batch_id') == $batch['Batch_ID'] ? 'selected' : '' }}>{{ $batch['Batch_Code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-auto">
                <select name="status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Semua Status Aktif</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($classes) === 0" empty-title="Data Kelas Kosong" empty-description="Belum ada kelas yang ditambahkan.">
        <x-slot:header>
            <th class="px-6 py-4">Identitas Kelas</th>
            <th class="px-6 py-4">Program / Angkatan</th>
            <th class="px-6 py-4">Wali Kelas</th>
            <th class="px-6 py-4 text-center">Kapasitas</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($classes as $class)
        <tr class="hover:bg-slate-50 transition-colors {{ ($class['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }}">
            <td class="px-6 py-4">
                <div class="font-bold text-slate-800">{{ $class['Class_Name'] }}</div>
                <div class="text-[11px] font-mono text-slate-500 mt-0.5">{{ $class['Class_Code'] }}</div>
            </td>
            <td class="px-6 py-4">
                <div class="font-bold text-indigo-700 truncate max-w-[150px]">{{ $class['Program_Name'] }}</div>
                <div class="text-xs text-slate-500 mt-0.5">{{ $class['Batch_Name'] }}</div>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center">
                    <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-xs mr-3">
                        {{ substr($class['Teacher_Name'], 0, 1) }}
                    </div>
                    <div class="text-sm font-medium text-slate-800">{{ $class['Teacher_Name'] }}</div>
                </div>
            </td>
            <td class="px-6 py-4 text-center">
                <div class="inline-flex flex-col items-center justify-center w-12 h-10 rounded-xl {{ ($class['Current_Student'] == $class['Capacity']) ? 'bg-red-50 border border-red-100' : 'bg-slate-50 border border-slate-200' }}">
                    <span class="text-[13px] font-bold {{ ($class['Current_Student'] == $class['Capacity']) ? 'text-red-700' : 'text-slate-800' }} leading-none">{{ $class['Current_Student'] }}</span>
                    <span class="text-[9px] text-slate-400 font-medium leading-none mt-1">/ {{ $class['Capacity'] }}</span>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="flex flex-col gap-1.5 items-start">
                    <x-badge status="{{ ($class['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                        {{ ($class['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Sistem Aktif' : 'Sistem Nonaktif' }}
                    </x-badge>
                    <x-badge color="blue">{{ $class['Class_Status'] }}</x-badge>
                </div>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="detail" url="{{ route('classes.show', $class['Class_ID']) }}" />
                    <x-universal.action-button action="edit" url="{{ route('classes.edit', $class['Class_ID']) }}" />
                    @if(($class['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <x-universal.action-button action="delete" url="{{ route('classes.destroy', $class['Class_ID']) }}" />
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        
        <x-slot:pagination>
            <x-universal.pagination :paginator="$classes" />
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection
