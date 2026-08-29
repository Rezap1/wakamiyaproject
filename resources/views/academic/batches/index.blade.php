@extends('layouts.app')
@section('header', 'Manajemen Angkatan / Batch')
@section('content')

<x-universal.index-layout 
    title="Daftar Angkatan (Batch)" 
    description="Kelola data angkatan untuk setiap program yang tersedia."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Angkatan' => route('batches.index')]"
    add-action="{{ route('batches.create') }}"
    add-text="Tambah Angkatan"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="batches" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('batches.index') }}" 
            refresh-url="{{ route('batches.index') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto">
                <select name="program_id" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="all" {{ request('program_id') === 'all' ? 'selected' : '' }}>Semua Program</option>
                    @foreach($programs as $program)
                        <option value="{{ $program['Program_ID'] }}" {{ request('program_id') == $program['Program_ID'] ? 'selected' : '' }}>{{ $program['Program_Code'] }} - {{ $program['Program_Name'] }}</option>
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

    <x-universal.data-table :empty="count($batches) === 0" empty-title="Data Angkatan Kosong" empty-description="Belum ada angkatan yang ditambahkan.">
        <x-slot:header>
            <th class="px-6 py-4">Identitas Angkatan</th>
            <th class="px-6 py-4">Program / Mata Diklat</th>
            <th class="px-6 py-4">Periode (Durasi)</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($batches as $batch)
        <tr class="hover:bg-slate-50 transition-colors {{ ($batch['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }}">
            <td class="px-6 py-4">
                <div class="font-bold text-slate-800">{{ $batch['Batch_Name'] }}</div>
                <div class="text-[11px] font-mono text-slate-500 mt-0.5">{{ $batch['Batch_Code'] }}</div>
            </td>
            <td class="px-6 py-4">
                <x-badge color="indigo" class="mb-1">{{ $batch['Program_Code'] }}</x-badge>
                <div class="text-xs font-medium text-slate-700 truncate max-w-xs" title="{{ $batch['Program_Name'] }}">
                    {{ $batch['Program_Name'] }}
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="font-bold text-slate-800">{{ \Carbon\Carbon::parse($batch['Start_Date'])->format('d M Y') }}</div>
                <div class="text-[11px] text-slate-500 mt-0.5">s/d {{ \Carbon\Carbon::parse($batch['End_Date'])->format('d M Y') }}</div>
            </td>
            <td class="px-6 py-4">
                <div class="flex flex-col gap-1.5 items-start">
                    <x-badge status="{{ ($batch['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                        {{ ($batch['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Sistem Aktif' : 'Sistem Nonaktif' }}
                    </x-badge>
                    <x-badge color="amber">{{ $batch['Batch_Status'] }}</x-badge>
                </div>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="detail" url="{{ route('batches.show', $batch['Batch_ID']) }}" />
                    <x-universal.action-button action="edit" url="{{ route('batches.edit', $batch['Batch_ID']) }}" />
                    @if(($batch['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <x-universal.action-button action="delete" url="{{ route('batches.destroy', $batch['Batch_ID']) }}" />
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        
        <x-slot:pagination>
            <x-universal.pagination :paginator="$batches" />
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection
