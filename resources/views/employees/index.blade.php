@extends('layouts.app')

@section('header', 'Manajemen Karyawan')

@section('content')
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-5 border-b border-gray-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Daftar Karyawan</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Kelola data seluruh karyawan, informasi pribadi, dan jabatan.</p>
        </div>
        <div>
            <div class="flex gap-2">
                <a href="{{ route('employees.index') }}" class="inline-flex items-center justify-center p-3 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" title="Refresh Data">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </a>
                <button type="button" class="inline-flex items-center justify-center px-4 py-3 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all opacity-50 cursor-not-allowed" title="Fitur Export PDF sedang dikembangkan">
                    <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Export PDF
                </button>
                <a href="{{ route('employees.create') }}" class="inline-flex items-center justify-center px-5 py-3 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Karyawan
                </a>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col xl:flex-row gap-4 items-center justify-between">
        <div class="relative w-full xl:w-96">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm transition-colors" placeholder="Cari NIK, Nama, atau Email...">
        </div>
        
        <div class="flex flex-col md:flex-row items-center gap-3 w-full xl:w-auto">
            <!-- Filter Department -->
            <select id="deptFilter" class="block w-full md:w-48 pl-3 pr-10 py-2 text-sm border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 rounded-xl bg-white font-medium">
                <option value="ALL">Semua Departemen</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept['Department_ID'] }}">{{ $dept['Department_Name'] }}</option>
                @endforeach
            </select>

            <!-- Filter Position -->
            <select id="posFilter" class="block w-full md:w-48 pl-3 pr-10 py-2 text-sm border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 rounded-xl bg-white font-medium">
                <option value="ALL">Semua Posisi</option>
                @foreach($positions as $pos)
                    <option value="{{ $pos['Position_ID'] }}" data-dept="{{ $pos['Department_ID'] }}">{{ $pos['Position_Name'] }}</option>
                @endforeach
            </select>

            <!-- Filter Status -->
            <select id="statusFilter" class="block w-full md:w-32 pl-3 pr-10 py-2 text-sm border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 rounded-xl bg-white font-medium">
                <option value="ALL">Semua Status</option>
                <option value="TRUE">Aktif</option>
                <option value="FALSE">Nonaktif</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-white">
                <tr>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Karyawan</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Kontak</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Departemen & Posisi</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Status Pegawai</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-5 text-right text-xs font-extrabold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100" id="tableBody">
                @forelse($employees as $employee)
                <tr class="hover:bg-primary-50/30 transition-colors group {{ ($employee['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }} filter-row" 
                    data-search="{{ strtolower($employee['Employee_ID'].$employee['Employee_Number'].$employee['Full_Name'].$employee['Email']) }}" 
                    data-status="{{ $employee['Is_Active'] ?? 'TRUE' }}"
                    data-dept="{{ $employee['Department_ID'] }}"
                    data-pos="{{ $employee['Position_ID'] }}">
                    
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 text-gray-600 flex items-center justify-center font-bold text-sm shadow-sm border border-white">
                                    {{ substr($employee['Full_Name'] ?? 'U', 0, 1) }}
                                </div>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-bold text-gray-900">{{ $employee['Full_Name'] }}</div>
                                <div class="text-xs font-medium text-gray-500 flex items-center gap-1 mt-0.5">
                                    <span class="text-primary-600">{{ $employee['Employee_Number'] }}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $employee['Email'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $employee['Phone_Number'] }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-bold text-gray-900">{{ $employee['Position_Name'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $employee['Department_Name'] }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                            {{ $employee['Employment_Status'] }}
                        </span>
                        <div class="text-xs text-gray-500 mt-1">Join: {{ \Carbon\Carbon::parse($employee['Join_Date'])->format('d M Y') }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if(($employee['Is_Active'] ?? 'TRUE') === 'TRUE')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                Nonaktif
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                        <a href="{{ route('employees.show', $employee['Employee_ID']) }}" class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-lg" title="Detail">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </a>
                        <a href="{{ route('employees.edit', $employee['Employee_ID']) }}" class="text-blue-500 hover:text-blue-700 transition-colors p-2 hover:bg-blue-50 rounded-lg" title="Edit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </a>
                        @if(($employee['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <form action="{{ route('employees.destroy', $employee['Employee_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan karyawan ini?');">
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
                    <td colspan="6" class="px-6 py-10 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <p class="text-gray-500 text-sm font-medium">Belum ada data karyawan.</p>
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
            Menampilkan <span class="font-bold text-gray-900">{{ $employees->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-gray-900">{{ $employees->lastItem() ?? 0 }}</span> dari <span class="font-bold text-gray-900">{{ $employees->total() }}</span> total data
        </div>
        <div>
            {{ $employees->links('pagination::tailwind') }}
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const deptFilter = document.getElementById('deptFilter');
        const posFilter = document.getElementById('posFilter');
        const rows = document.querySelectorAll('.filter-row');
        const posOptions = posFilter.querySelectorAll('option:not([value="ALL"])');

        // Dependent Dropdown for Index Filter
        deptFilter.addEventListener('change', function() {
            const selectedDept = this.value;
            
            // Filter Position options
            posFilter.value = 'ALL';
            posOptions.forEach(option => {
                if (selectedDept === 'ALL' || option.getAttribute('data-dept') === selectedDept) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
            
            filterTable();
        });

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;
            const deptValue = deptFilter.value;
            const posValue = posFilter.value;

            rows.forEach(row => {
                const searchString = row.getAttribute('data-search');
                const rowStatus = row.getAttribute('data-status');
                const rowDept = row.getAttribute('data-dept');
                const rowPos = row.getAttribute('data-pos');
                
                const matchesSearch = searchString.includes(searchTerm);
                const matchesStatus = statusValue === 'ALL' || rowStatus === statusValue;
                const matchesDept = deptValue === 'ALL' || rowDept === deptValue;
                const matchesPos = posValue === 'ALL' || rowPos === posValue;
                
                if (matchesSearch && matchesStatus && matchesDept && matchesPos) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
        posFilter.addEventListener('change', filterTable);
    });
</script>
@endsection
