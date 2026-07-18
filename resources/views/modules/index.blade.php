@extends('layouts.app')

@section('header', 'Manajemen Modul')

@section('content')
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-900">Modul Aplikasi</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Kelola data modul dan akses aplikasi WMS.</p>
        </div>
        <div>
            <div class="flex gap-2">
                <a href="{{ route('modules.index') }}" class="inline-flex items-center justify-center p-3 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" title="Refresh Data">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </a>
                <a href="{{ route('modules.create') }}" class="inline-flex items-center justify-center px-5 py-3 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Modul Baru
                </a>
            </div>
        </div>
    </div>
    
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="relative w-full md:w-96">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm transition-colors" placeholder="Cari modul (Realtime)...">
        </div>
        
        <div class="flex items-center gap-2 w-full md:w-auto">
            <label for="statusFilter" class="text-sm font-bold text-gray-700">Filter:</label>
            <select id="statusFilter" class="block w-full md:w-48 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-xl bg-white">
                <option value="ALL">Semua Status</option>
                <option value="TRUE">Aktif</option>
                <option value="FALSE">Nonaktif</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Module ID / Kode</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Nama Modul</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Grup Modul</th>
                    <th scope="col" class="px-8 py-5 text-center text-xs font-extrabold text-gray-500 uppercase tracking-wider">Urutan</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-8 py-5 text-right text-xs font-extrabold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100" id="tableBody">
                @forelse($modules as $module)
                <tr class="hover:bg-primary-50/30 transition-colors group {{ ($module['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }} filter-row" data-search="{{ strtolower($module['Module_ID'].$module['Module_Code'].$module['Module_Name'].$module['Module_Group']) }}" data-status="{{ $module['Is_Active'] ?? 'TRUE' }}">
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="ml-2">
                                <div class="text-sm font-bold text-gray-900">{{ $module['Module_ID'] ?? '-' }}</div>
                                <div class="text-sm font-medium text-gray-500 mt-0.5">{{ $module['Module_Code'] ?? '-' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="h-11 w-11 flex-shrink-0">
                                <div class="h-11 w-11 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 text-primary-700 flex items-center justify-center font-bold text-base shadow-sm border border-white ring-2 ring-primary-50">
                                    {{ substr($module['Module_Name'] ?? 'M', 0, 1) }}
                                </div>
                            </div>
                            <div class="ml-5">
                                <div class="text-sm font-bold text-gray-900">{{ $module['Module_Name'] ?? '-' }}</div>
                                <div class="text-xs font-medium text-gray-500 mt-0.5">{{ Str::limit($module['Notes'] ?? '-', 30) }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            {{ $module['Module_Group'] ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap text-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-800 font-bold text-sm">
                            {{ $module['Module_Order'] ?? '0' }}
                        </span>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        @if(($module['Is_Active'] ?? 'TRUE') === 'TRUE')
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                                Nonaktif
                            </span>
                        @endif
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                        <a href="{{ route('modules.show', $module['Module_ID']) }}" class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-lg" title="Detail">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </a>
                        <a href="{{ route('modules.edit', $module['Module_ID']) }}" class="text-blue-500 hover:text-blue-700 transition-colors p-2 hover:bg-blue-50 rounded-lg" title="Edit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </a>
                        <form action="{{ route('modules.destroy', $module['Module_ID']) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan modul ini?');" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-600 transition-colors p-2 hover:bg-red-50 rounded-lg" title="Nonaktifkan">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-8 py-16 text-center text-gray-500 bg-gray-50/50">
                        <div class="flex flex-col items-center justify-center">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            </div>
                            <p class="font-bold text-gray-600">Tidak ada modul ditemukan.</p>
                            <p class="text-sm mt-1">Data dari Google Sheets masih kosong.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
        <div class="text-sm text-gray-500 font-medium">
            Menampilkan <span class="font-bold text-gray-900">{{ $modules->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-gray-900">{{ $modules->lastItem() ?? 0 }}</span> dari <span class="font-bold text-gray-900">{{ $modules->total() }}</span> total data
        </div>
        <div>
            {{ $modules->links('pagination::tailwind') }}
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const rows = document.querySelectorAll('.filter-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;

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

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
    });
</script>
@endsection
