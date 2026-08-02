@extends('layouts.app')
@section('header', 'Master Mata Pelajaran')
@section('content')

<x-universal.index-layout 
    title="Daftar Mata Pelajaran" 
    description="Kelola data mata pelajaran dan penugasan per program."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Mata Pelajaran' => route('subjects.index')]"
    add-action="{{ route('subjects.create') }}"
    add-text="Tambah Mata Pelajaran"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="subjects" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('subjects.index') }}" 
            refresh-url="{{ route('subjects.index') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto">
                <select id="statusFilter" name="status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors">
                    <option value="ALL">Semua Status</option>
                    <option value="TRUE">Aktif</option>
                    <option value="FALSE">Nonaktif</option>
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($subjects) === 0" empty-title="Data Mata Pelajaran Kosong" empty-description="Belum ada mata pelajaran yang ditambahkan.">
        <x-slot:header>
            <th class="px-6 py-4">Kode / Nama Mata Pelajaran</th>
            <th class="px-6 py-4">Program</th>
            <th class="px-6 py-4">SKS / Durasi</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($subjects as $subject)
        <tr class="hover:bg-slate-50 transition-colors {{ ($subject['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }} filter-row" 
            data-search="{{ strtolower(($subject['Subject_Code'] ?? '').($subject['Subject_Name'] ?? '')) }}" 
            data-status="{{ $subject['Is_Active'] ?? 'TRUE' }}">
            
            <td class="px-6 py-4">
                <div class="font-bold text-slate-800">{{ $subject['Subject_Name'] ?? '-' }}</div>
                <div class="text-[11px] font-mono text-slate-500 mt-0.5">{{ $subject['Subject_Code'] ?? '-' }}</div>
            </td>
            <td class="px-6 py-4">
                <x-badge color="purple" class="font-medium">{{ $subject['Program_ID'] ?? '-' }}</x-badge>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <x-badge color="blue">{{ $subject['Credit'] ?? '0' }} SKS</x-badge>
                    <span class="text-[11px] text-slate-500 font-medium">{{ $subject['Duration'] ?? '0' }} Menit</span>
                </div>
            </td>
            <td class="px-6 py-4">
                <x-badge status="{{ ($subject['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                    {{ ($subject['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}
                </x-badge>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="edit" url="{{ route('subjects.edit', $subject['Subject_ID']) }}" />
                    <x-universal.action-button action="delete" url="{{ route('subjects.destroy', $subject['Subject_ID']) }}" />
                </div>
            </td>
        </tr>
        @endforeach
    </x-universal.data-table>

</x-universal.index-layout>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // the global search from index-layout triggers 'search-url' automatically if implemented
        // Here we can re-implement the client-side filter since the form isn't using GET parameters
        const searchInput = document.querySelector('input[name="search"]');
        const statusFilter = document.getElementById('statusFilter');
        const rows = document.querySelectorAll('.filter-row');

        function filterTable() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const statusValue = statusFilter ? statusFilter.value : 'ALL';

            rows.forEach(row => {
                const searchString = row.getAttribute('data-search');
                const rowStatus = row.getAttribute('data-status');
                
                const matchesSearch = searchString.includes(searchTerm);
                const matchesStatus = statusValue === 'ALL' || rowStatus === statusValue;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                // Prevent form submission if x-universal.toolbar does it
                e.preventDefault();
                filterTable();
            });
        }
        if (statusFilter) statusFilter.addEventListener('change', filterTable);
    });
</script>
@endsection
