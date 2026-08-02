@extends('layouts.app')
@section('header', 'Manajemen Program Studi / Pelatihan')
@section('content')

<x-universal.index-layout 
    title="Daftar Program" 
    description="Kelola data program studi atau pelatihan yang tersedia di instansi."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Program' => route('programs.index')]"
    add-action="{{ route('programs.create') }}"
    add-text="Tambah Program"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="programs" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('programs.index') }}" 
            refresh-url="{{ route('programs.index') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto">
                <select name="status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" onchange="this.form.submit()">
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($programs) === 0" empty-title="Data Program Kosong" empty-description="Belum ada program studi atau pelatihan yang ditambahkan.">
        <x-slot:header>
            <th class="px-6 py-4">Kode Program</th>
            <th class="px-6 py-4">Nama Program</th>
            <th class="px-6 py-4">Kategori</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($programs as $program)
        <tr class="hover:bg-slate-50 transition-colors {{ ($program['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }}">
            <td class="px-6 py-4">
                <x-badge color="indigo" class="font-mono">{{ $program['Program_Code'] }}</x-badge>
            </td>
            <td class="px-6 py-4">
                <div class="font-bold text-slate-800">{{ $program['Program_Name'] }}</div>
                <div class="text-[11px] font-medium text-slate-500 mt-0.5 truncate max-w-xs">{{ $program['Description'] }}</div>
            </td>
            <td class="px-6 py-4">
                <x-badge color="slate">{{ $program['Program_Category'] }}</x-badge>
            </td>
            <td class="px-6 py-4">
                <x-badge status="{{ ($program['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                    {{ ($program['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}
                </x-badge>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="detail" url="{{ route('programs.show', $program['Program_ID']) }}" />
                    <x-universal.action-button action="edit" url="{{ route('programs.edit', $program['Program_ID']) }}" />
                    @if(($program['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <x-universal.action-button action="delete" url="{{ route('programs.destroy', $program['Program_ID']) }}" />
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        
        <x-slot:pagination>
            <x-universal.pagination :paginator="$programs" />
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection
