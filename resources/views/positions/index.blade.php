@extends('layouts.app')

@section('header', 'Manajemen Posisi')

@section('content')
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-5 border-b border-gray-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Daftar Posisi (Jabatan)</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Kelola data posisi dan penugasannya ke departemen.</p>
        </div>
        <div>
            <div class="flex gap-2">
                <a href="{{ route('positions.index') }}" class="inline-flex items-center justify-center p-3 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" title="Refresh Data">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </a>
                <a href="{{ route('positions.create') }}" class="inline-flex items-center justify-center px-5 py-3 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Posisi Baru
                </a>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="relative w-full md:w-96">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm transition-colors" placeholder="Cari posisi (Realtime)...">
        </div>
        
        <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
            <!-- Filter Department -->
            <div class="flex items-center gap-2 w-full md:w-auto">
                <label for="deptFilter" class="text-sm font-bold text-gray-700 whitespace-nowrap">Departemen:</label>
                <select id="deptFilter" class="block w-full md:w-48 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-xl bg-white">
                    <option value="ALL">Semua Departemen</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept['Department_ID'] }}">{{ $dept['Department_Name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status -->
            <div class="flex items-center gap-2 w-full md:w-auto">
                <label for="statusFilter" class="text-sm font-bold text-gray-700">Status:</label>
                <select id="statusFilter" class="block w-full md:w-32 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-xl bg-white">
                    <option value="ALL">Semua</option>
                    <option value="TRUE">Aktif</option>
                    <option value="FALSE">Nonaktif</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-white">
                <tr>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">ID / Kode</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Nama Posisi</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Departemen</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Level</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Dibuat Pada</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Diperbarui Pada</th>
                    <th scope="col" class="px-8 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-8 py-5 text-right text-xs font-extrabold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100" id="tableBody">
                @forelse($positions as $position)
                <tr class="hover:bg-primary-50/30 transition-colors group {{ ($position['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }} filter-row" 
                    data-search="{{ strtolower($position['Position_ID'].$position['Position_Code'].$position['Position_Name'].$position['Department_Name']) }}" 
                    data-status="{{ $position['Is_Active'] ?? 'TRUE' }}"
                    data-dept="{{ $position['Department_ID'] }}">
                    
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="text-sm font-bold text-gray-900">{{ $position['Position_Code'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $position['Position_ID'] }}</div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="text-sm font-bold text-gray-900">{{ $position['Position_Name'] }}</div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="text-sm text-gray-700 bg-gray-100 px-3 py-1 rounded-lg inline-block">{{ $position['Department_Name'] }}</div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $position['Position_Level'] }}</div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ !empty($position['Created_At']) ? \Carbon\Carbon::parse($position['Created_At'])->format('d M Y, H:i') : '-' }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $position['Created_By'] ?? '-' }}</div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ !empty($position['Updated_At']) ? \Carbon\Carbon::parse($position['Updated_At'])->format('d M Y, H:i') : '-' }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $position['Updated_By'] ?? '-' }}</div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        @if(($position['Is_Active'] ?? 'TRUE') === 'TRUE')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                Nonaktif
                            </span>
                        @endif
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                        <a href="{{ route('positions.show', $position['Position_ID']) }}" class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-lg" title="Detail">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </a>
                        <a href="{{ route('positions.edit', $position['Position_ID']) }}" class="text-blue-500 hover:text-blue-700 transition-colors p-2 hover:bg-blue-50 rounded-lg" title="Edit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </a>
                        @if(($position['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <form action="{{ route('positions.destroy', $position['Position_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan posisi ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 transition-colors p-2 hover:bg-red-50 rounded-lg" title="Nonaktifkan">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-10 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            <p class="text-gray-500 text-sm font-medium">Belum ada data posisi.</p>
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
            Menampilkan <span class="font-bold text-gray-900">{{ $positions->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-gray-900">{{ $positions->lastItem() ?? 0 }}</span> dari <span class="font-bold text-gray-900">{{ $positions->total() }}</span> total data
        </div>
        <div>
            {{ $positions->links('pagination::tailwind') }}
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const deptFilter = document.getElementById('deptFilter');
        const rows = document.querySelectorAll('.filter-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;
            const deptValue = deptFilter.value;

            rows.forEach(row => {
                const searchString = row.getAttribute('data-search');
                const rowStatus = row.getAttribute('data-status');
                const rowDept = row.getAttribute('data-dept');
                
                const matchesSearch = searchString.includes(searchTerm);
                const matchesStatus = statusValue === 'ALL' || rowStatus === statusValue;
                const matchesDept = deptValue === 'ALL' || rowDept === deptValue;
                
                if (matchesSearch && matchesStatus && matchesDept) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
        deptFilter.addEventListener('change', filterTable);
    });
</script>
@endsection
