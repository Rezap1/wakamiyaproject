@extends('layouts.app')

@section('header', 'Manajemen Tenaga Pendidik (Guru)')

@section('content')
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-5 border-b border-gray-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Daftar Guru / Tenaga Pendidik</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Kelola data tenaga pendidik, spesialisasi mengajar, dan status.</p>
        </div>
        <div>
            <div class="flex gap-2">
                <a href="{{ route('teachers.index') }}" class="inline-flex items-center justify-center p-3 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" title="Refresh Data">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </a>
                                <a href="{{ route('teachers.create') }}" class="inline-flex items-center justify-center px-5 py-3 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Guru
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
            <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm transition-colors" placeholder="Cari Nama, NIK, atau Spesialisasi...">
        </div>
        
        <div class="flex flex-col md:flex-row items-center gap-3 w-full xl:w-auto">
            <!-- Filter Status -->
            <select id="statusFilter" class="block w-full md:w-48 pl-3 pr-10 py-2 text-sm border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 rounded-xl bg-white font-medium">
                <option value="ALL">Semua Status Sistem</option>
                <option value="TRUE">Aktif</option>
                <option value="FALSE">Nonaktif</option>
            </select>
            
            <select id="teachingFilter" class="block w-full md:w-48 pl-3 pr-10 py-2 text-sm border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 rounded-xl bg-white font-medium">
                <option value="ALL">Semua Status Mengajar</option>
                <option value="Aktif Mengajar">Aktif Mengajar</option>
                <option value="Cuti Mengajar">Cuti Mengajar</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-white">
                <tr>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Pengajar</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Kontak</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Spesialisasi</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Status Mengajar</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Akses Sistem</th>
                    <th scope="col" class="px-6 py-5 text-right text-xs font-extrabold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100" id="tableBody">
                @forelse($teachers as $teacher)
                <tr class="hover:bg-primary-50/30 transition-colors group {{ ($teacher['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }} filter-row" 
                    data-search="{{ strtolower($teacher['Teacher_ID'].$teacher['Teacher_Code'].$teacher['Full_Name'].$teacher['Specialization'].$teacher['Employee_Number']) }}" 
                    data-status="{{ $teacher['Is_Active'] ?? 'TRUE' }}"
                    data-teaching="{{ $teacher['Teaching_Status'] ?? '' }}">
                    
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-100 to-indigo-200 text-indigo-600 flex items-center justify-center font-bold text-sm shadow-sm border border-white">
                                    {{ substr($teacher['Full_Name'] ?? 'U', 0, 1) }}
                                </div>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-bold text-gray-900">{{ $teacher['Full_Name'] }}</div>
                                <div class="text-xs font-medium text-gray-500 flex items-center gap-1 mt-0.5">
                                    <span class="text-indigo-600">{{ $teacher['Teacher_Code'] }}</span>
                                    <span class="text-gray-300">|</span>
                                    <span>{{ $teacher['Employee_Number'] }}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $teacher['Email'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $teacher['Phone_Number'] }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                            {{ $teacher['Specialization'] }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if(($teacher['Teaching_Status'] ?? '') === 'Aktif Mengajar')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                Aktif Mengajar
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-orange-50 text-orange-700 border border-orange-200">
                                {{ $teacher['Teaching_Status'] }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if(($teacher['Is_Active'] ?? 'TRUE') === 'TRUE')
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
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('teachers.show', $teacher['Teacher_ID']) }}" class="text-gray-500 hover:text-gray-900 bg-gray-50 hover:bg-gray-100 p-2 rounded-lg transition-colors" title="Lihat Detail">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </a>
                            
                                                        <a href="{{ route('teachers.edit', $teacher['Teacher_ID']) }}" class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-colors" title="Edit Data">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </a>
                                                        
                                                        @if(($teacher['Is_Active'] ?? 'TRUE') !== 'FALSE')
                                <form action="{{ route('teachers.destroy', $teacher['Teacher_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan pengajar ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors" title="Hapus Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            @endif
                                                    </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            <p class="text-gray-500 text-sm font-medium">Belum ada data tenaga pendidik.</p>
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
            Menampilkan <span class="font-bold text-gray-900">{{ $teachers->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-gray-900">{{ $teachers->lastItem() ?? 0 }}</span> dari <span class="font-bold text-gray-900">{{ $teachers->total() }}</span> total data
        </div>
        <div>
            {{ $teachers->links('pagination::tailwind') }}
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const teachingFilter = document.getElementById('teachingFilter');
        const rows = document.querySelectorAll('.filter-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;
            const teachingValue = teachingFilter.value;

            rows.forEach(row => {
                const searchString = row.getAttribute('data-search');
                const rowStatus = row.getAttribute('data-status');
                const rowTeaching = row.getAttribute('data-teaching');
                
                const matchesSearch = searchString.includes(searchTerm);
                const matchesStatus = statusValue === 'ALL' || rowStatus === statusValue;
                const matchesTeaching = teachingValue === 'ALL' || rowTeaching === teachingValue;
                
                if (matchesSearch && matchesStatus && matchesTeaching) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
        teachingFilter.addEventListener('change', filterTable);
    });
</script>
@endsection
