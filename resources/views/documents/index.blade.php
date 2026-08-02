@extends('layouts.app')
@section('header', 'Manajemen Dokumen')
@section('content')

<x-universal.index-layout 
    title="Dokumen Persyaratan" 
    description="Kelola berkas, paspor, visa, dan dokumen legal lainnya milik kandidat."
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Pemasaran' => '#', 'Dokumen' => route('documents.index')]"
    add-action="{{ route('documents.create') }}"
    add-text="Tambah Dokumen"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="documents" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex flex-col lg:flex-row gap-4 items-center justify-between">
            <div class="relative w-full lg:w-96">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-slate-200 rounded-xl leading-5 bg-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-colors shadow-sm" placeholder="Cari nomor dokumen, siswa, nama file...">
            </div>
            
            <div class="flex items-center gap-4 w-full lg:w-auto">
                <select id="filterType" class="block w-full md:w-40 pl-3 pr-10 py-2 text-sm border-slate-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-xl bg-white shadow-sm font-medium">
                    <option value="">Semua Jenis</option>
                    <option value="CONTRACT">Kontrak Kerja</option>
                    <option value="MEDICAL">Medical</option>
                    <option value="CERTIFICATE">Sertifikat</option>
                    <option value="OTHER">Lainnya</option>
                </select>
                <select id="filterStatus" class="block w-full md:w-40 pl-3 pr-10 py-2 text-sm border-slate-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-xl bg-white shadow-sm font-medium">
                    <option value="">Semua Status</option>
                    <option value="PENDING">Menunggu</option>
                    <option value="VERIFIED">Terverifikasi</option>
                    <option value="REJECTED">Ditolak</option>
                    <option value="EXPIRED">Kedaluwarsa</option>
                </select>
            </div>
        </div>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($documents) === 0" empty-title="Belum Ada Dokumen" empty-description="Tambahkan dokumen baru untuk melengkapi berkas kandidat.">
        <x-slot:header>
            <th class="px-6 py-4">Info Dokumen</th>
            <th class="px-6 py-4">Pemilik (Siswa)</th>
            <th class="px-6 py-4">Tipe & Nama File</th>
            <th class="px-6 py-4 text-center">Status Validitas</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($documents as $doc)
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="h-10 w-10 flex-shrink-0 rounded-full bg-blue-50 flex items-center justify-center text-blue-700 font-bold border border-blue-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-bold text-slate-800">{{ $doc['Document_Number'] ?? $doc['Document_ID'] }}</div>
                        <div class="text-[11px] font-medium text-slate-500 mt-0.5">
                            {{ $doc['Document_Name'] }}
                        </div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-bold text-slate-800 search-student">{{ $doc['Student_Name'] ?? 'Siswa Tidak Diketahui' }}</div>
                <div class="text-[11px] font-medium text-slate-500 mt-0.5">NIS: {{ $doc['Student_Registration_Number'] ?? '-' }}</div>

            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border bg-slate-100 text-slate-600 border-slate-200 search-type uppercase">
                    {{ $doc['Document_Type'] ?? 'Lainnya' }}
                </span>
                <div class="text-[11px] text-blue-600 font-medium mt-1.5 truncate max-w-[200px]" title="{{ $doc['File_Name'] ?? 'Tanpa File' }}">
                    @if(!empty($doc['File_URL']))
                        <a href="{{ $doc['File_URL'] }}" target="_blank" class="hover:underline flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            {{ $doc['File_Name'] ?? 'Lihat Berkas' }}
                        </a>
                    @else
                        <span class="text-slate-400">Tidak ada lampiran</span>
                    @endif
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex flex-col gap-1 items-center">
                    @php
                        $status = $doc['Document_Status'] ?? '';
                        $statusColor = match($status) {
                            'VERIFIED' => 'green',
                            'PENDING' => 'amber',
                            'REJECTED' => 'red',
                            'EXPIRED' => 'slate',
                            default => 'slate'
                        };
                    @endphp
                    <span class="search-status inline-block">
                        <x-badge color="{{ $statusColor }}">{{ $status ?: 'TIDAK DIKETAHUI' }}</x-badge>
                    </span>
                    
                    @if(!empty($doc['Expiry_Date']))
                        @php
                            $isExpired = \Carbon\Carbon::parse($doc['Expiry_Date'])->isPast();
                            $expColor = $isExpired ? 'text-rose-600 font-bold' : 'text-slate-500 font-medium';
                        @endphp
                        <div class="text-[10px] mt-1 {{ $expColor }}">
                            Exp: {{ \Carbon\Carbon::parse($doc['Expiry_Date'])->format('d M Y') }}
                        </div>
                    @endif
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="detail" url="{{ route('documents.show', $doc['Document_ID']) }}" />
                    <x-universal.action-button action="edit" url="{{ route('documents.edit', $doc['Document_ID']) }}" />
                    <x-universal.action-button action="delete" url="{{ route('documents.destroy', $doc['Document_ID']) }}" />
                </div>
            </td>
        </tr>
        @endforeach
        
        <x-slot:pagination>
            <!-- Data is passed directly, simple count displayed -->
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                <span class="text-sm text-slate-500 font-medium">Menampilkan <span class="font-bold text-slate-800" id="visibleCount">{{ count($documents) }}</span> dari <span class="font-bold text-slate-800">{{ count($documents) }}</span> dokumen</span>
            </div>
        </x-slot:pagination>
    </x-universal.data-table>
</x-universal.index-layout>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const filterType = document.getElementById('filterType');
        const filterStatus = document.getElementById('filterStatus');
        const table = document.getElementById('dataTable');
        // Because of the universal table component, rows are in tbody
        const rows = document.querySelectorAll('tbody tr');
        const visibleCountEl = document.getElementById('visibleCount');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const typeTerm = filterType.value.toLowerCase();
            const statusTerm = filterStatus.value.toLowerCase();
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                if (rows[i].getElementsByTagName('td').length === 1) continue; // Skip empty row
                
                const textContent = rows[i].textContent.toLowerCase();
                const typeContent = rows[i].querySelector('.search-type') ? rows[i].querySelector('.search-type').textContent.toLowerCase() : '';
                const statusContent = rows[i].querySelector('.search-status') ? rows[i].querySelector('.search-status').textContent.toLowerCase() : '';
                
                const matchSearch = textContent.includes(searchTerm);
                const matchType = typeTerm === '' || typeContent.includes(typeTerm);
                const matchStatus = statusTerm === '' || statusContent.includes(statusTerm);

                if (matchSearch && matchType && matchStatus) {
                    rows[i].style.display = '';
                    visibleCount++;
                } else {
                    rows[i].style.display = 'none';
                }
            }
            
            if (visibleCountEl) {
                visibleCountEl.textContent = visibleCount;
            }
        }

        if(searchInput) searchInput.addEventListener('keyup', filterTable);
        if(filterType) filterType.addEventListener('change', filterTable);
        if(filterStatus) filterStatus.addEventListener('change', filterTable);
    });
</script>
@endsection
