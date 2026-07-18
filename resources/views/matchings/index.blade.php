@extends('layouts.app')

@section('header', 'Data Matching')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8 mt-2">
        <div>
            <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Manajemen Matching</h2>
            <p class="text-sm font-medium text-gray-500 mt-2">Kelola kecocokan kandidat dengan lowongan kerja dan persetujuan dari kedua belah pihak.</p>
        </div>
        <a href="{{ route('matchings.create') }}" class="inline-flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 shadow-lg shadow-primary-500/30 transition-all hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Buat Matching Baru
        </a>
    </div>

    <!-- Stats & Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
        <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="w-full md:w-96 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="searchInput" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 focus:bg-white transition-colors sm:text-sm font-medium" placeholder="Cari berdasarkan kandidat, perusahaan...">
            </div>
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <select id="filterCompany" class="block w-full md:w-48 pl-3 pr-10 py-3 text-sm border-gray-200 rounded-xl focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-gray-50 font-medium">
                    <option value="">Semua Perusahaan</option>
                    @foreach($jobOrders as $jo)
                        @if(isset($jo['Company_Name']) && $jo['Company_Name'])
                            <option value="{{ $jo['Company_Name'] }}">{{ $jo['Company_Name'] }}</option>
                        @endif
                    @endforeach
                </select>
                <select id="filterStatus" class="block w-full md:w-40 pl-3 pr-10 py-3 text-sm border-gray-200 rounded-xl focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-gray-50 font-medium">
                    <option value="">Semua Status</option>
                    <option value="PROPOSED">Proposed</option>
                    <option value="REVIEWING">Reviewing</option>
                    <option value="ACCEPTED">Accepted</option>
                    <option value="REJECTED">Rejected</option>
                    <option value="WITHDRAWN">Withdrawn</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="dataTable">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Info Matching</th>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Kandidat</th>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Perusahaan & Lowongan</th>
                        <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($matchings as $matching)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 flex-shrink-0 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-bold border border-teal-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-gray-900">{{ $matching['Matching_Number'] ?? $matching['Matching_ID'] }}</div>
                                    <div class="text-xs font-medium text-gray-500 mt-0.5">
                                        {{ !empty($matching['Matching_Date']) ? \Carbon\Carbon::parse($matching['Matching_Date'])->format('d M Y') : '-' }}
                                    </div>
                                    @if(!empty($matching['Interview_ID']))
                                        <div class="text-[10px] text-teal-600 mt-0.5 font-semibold">Terkait Interview</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-gray-900 search-student">{{ $matching['Student_Name'] ?? 'Siswa Tidak Diketahui' }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $matching['Student_Registration_Number'] ?? '-' }}</div>
                            @if(!empty($matching['Student_Approval_Date']))
                                <div class="text-[10px] bg-green-100 text-green-700 inline-block px-1.5 py-0.5 rounded mt-1 font-medium">Disetujui: {{ \Carbon\Carbon::parse($matching['Student_Approval_Date'])->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-gray-900 search-company">{{ $matching['Company_Name'] ?? 'Perusahaan Tidak Diketahui' }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $matching['Job_Title'] ?? 'Pekerjaan Tidak Diketahui' }}</div>
                            @if(!empty($matching['Company_Approval_Date']))
                                <div class="text-[10px] bg-blue-100 text-blue-700 inline-block px-1.5 py-0.5 rounded mt-1 font-medium">Disetujui: {{ \Carbon\Carbon::parse($matching['Company_Approval_Date'])->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @php
                                $status = $matching['Matching_Status'] ?? '';
                                $statusColor = match($status) {
                                    'ACCEPTED' => 'bg-green-50 text-green-700 border-green-200',
                                    'PROPOSED' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'REVIEWING' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'REJECTED' => 'bg-red-50 text-red-700 border-red-200',
                                    'WITHDRAWN' => 'bg-gray-100 text-gray-600 border-gray-300',
                                    default => 'bg-gray-50 text-gray-700 border-gray-200'
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold border {{ $statusColor }} search-status">
                                {{ $status ?: 'UNKNOWN' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('matchings.show', $matching['Matching_ID']) }}" class="p-2 text-teal-600 hover:bg-teal-50 rounded-lg transition-colors" title="Detail">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                                <a href="{{ route('matchings.edit', $matching['Matching_ID']) }}" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('matchings.destroy', $matching['Matching_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data matching ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                            <p class="text-base font-bold text-gray-900">Belum Ada Data Matching</p>
                            <p class="text-sm text-gray-500 mt-1">Buat kecocokan baru antara kandidat dan perusahaan.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination Placeholder -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <span class="text-sm text-gray-500 font-medium">Menampilkan <span class="font-bold text-gray-900" id="visibleCount">{{ count($matchings) }}</span> dari <span class="font-bold text-gray-900">{{ count($matchings) }}</span> data</span>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const filterCompany = document.getElementById('filterCompany');
        const filterStatus = document.getElementById('filterStatus');
        const table = document.getElementById('dataTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        const visibleCountEl = document.getElementById('visibleCount');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const companyTerm = filterCompany.value.toLowerCase();
            const statusTerm = filterStatus.value.toLowerCase();
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                if (rows[i].getElementsByTagName('td').length === 1) continue; // Skip empty row
                
                const textContent = rows[i].textContent.toLowerCase();
                const companyContent = rows[i].querySelector('.search-company').textContent.toLowerCase();
                const statusContent = rows[i].querySelector('.search-status').textContent.toLowerCase();
                
                const matchSearch = textContent.includes(searchTerm);
                const matchCompany = companyTerm === '' || companyContent.includes(companyTerm);
                const matchStatus = statusTerm === '' || statusContent.includes(statusTerm);

                if (matchSearch && matchCompany && matchStatus) {
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

        searchInput.addEventListener('keyup', filterTable);
        filterCompany.addEventListener('change', filterTable);
        filterStatus.addEventListener('change', filterTable);
    });
</script>
@endsection
