@extends('layouts.app')

@section('header', 'Manajemen Job Order')

@section('content')
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-900">Job Order (Lowongan)</h2>
            <p class="text-sm font-medium text-gray-500 mt-1">Kelola data permintaan pekerjaan dari perusahaan mitra.</p>
        </div>
        <div>
            <div class="flex gap-2">
                <a href="{{ route('job-orders.index') }}" class="inline-flex items-center justify-center p-3 border border-gray-200 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" title="Refresh Data">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </a>
                <a href="{{ route('job-orders.create') }}" class="inline-flex items-center justify-center px-5 py-3 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Job Order Baru
                </a>
            </div>
        </div>
    </div>
    
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="relative w-full md:w-96">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm transition-colors" placeholder="Cari job order (Realtime)...">
        </div>
        
        <div class="flex items-center gap-4 w-full md:w-auto">
            <div class="flex items-center gap-2">
                <label for="companyFilter" class="text-sm font-bold text-gray-700">Perusahaan:</label>
                <select id="companyFilter" class="block w-full md:w-48 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-xl bg-white">
                    <option value="ALL">Semua Perusahaan</option>
                    @foreach($companies as $company)
                        <option value="{{ $company['Company_ID'] }}">{{ $company['Company_Name'] ?? $company['Company_Code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label for="statusFilter" class="text-sm font-bold text-gray-700">Status:</label>
                <select id="statusFilter" class="block w-full md:w-48 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-xl bg-white">
                    <option value="ALL">Semua Status</option>
                    <option value="OPEN">Open</option>
                    <option value="CLOSED">Closed</option>
                    <option value="DRAFT">Draft</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">No. / ID</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Perusahaan</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Posisi / Kategori</th>
                    <th scope="col" class="px-6 py-5 text-center text-xs font-extrabold text-gray-500 uppercase tracking-wider">Kuota</th>
                    <th scope="col" class="px-6 py-5 text-left text-xs font-extrabold text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-5 text-right text-xs font-extrabold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100" id="tableBody">
                @forelse($jobOrders as $jobOrder)
                <tr class="hover:bg-primary-50/30 transition-colors group {{ ($jobOrder['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }} filter-row" data-search="{{ strtolower($jobOrder['Job_Order_ID'].$jobOrder['Job_Order_Number'].$jobOrder['Job_Title'].$jobOrder['Company_Name']) }}" data-status="{{ $jobOrder['Job_Order_Status'] ?? 'OPEN' }}" data-company="{{ $jobOrder['Company_ID'] ?? '' }}">
                    <td class="px-6 py-5 whitespace-nowrap">
                        <div class="flex items-center">
                            <div>
                                <div class="text-sm font-bold text-gray-900">{{ $jobOrder['Job_Order_Number'] ?: '-' }}</div>
                                <div class="text-xs font-medium text-gray-500 mt-0.5">{{ $jobOrder['Job_Order_ID'] ?? '-' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="h-10 w-10 flex-shrink-0">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-100 to-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-base shadow-sm border border-white ring-2 ring-indigo-50">
                                    {{ substr($jobOrder['Company_Name'] ?? 'C', 0, 1) }}
                                </div>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-bold text-gray-900">{{ Str::limit($jobOrder['Company_Name'] ?? '-', 20) }}</div>
                                <div class="text-xs font-medium text-gray-500 mt-0.5">{{ $jobOrder['Work_Location'] ?? '-' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        <div class="text-sm font-bold text-gray-900">{{ Str::limit($jobOrder['Job_Title'] ?? '-', 25) }}</div>
                        <div class="text-xs font-medium text-gray-500 mt-0.5">{{ $jobOrder['Job_Category'] ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap text-center">
                        <div class="flex flex-col items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 mb-1">
                                Total: {{ $jobOrder['Recruitment_Quantity'] ?? '0' }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold {{ ($jobOrder['Remaining_Quota'] ?? 0) > 0 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                Sisa: {{ $jobOrder['Remaining_Quota'] ?? '0' }}
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        @if(($jobOrder['Is_Active'] ?? 'TRUE') === 'FALSE')
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200 shadow-sm">
                                INACTIVE
                            </span>
                        @else
                            @if(($jobOrder['Job_Order_Status'] ?? 'OPEN') === 'OPEN')
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span>
                                    OPEN
                                </span>
                            @elseif(($jobOrder['Job_Order_Status'] ?? '') === 'CLOSED')
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span>
                                    CLOSED
                                </span>
                            @elseif(($jobOrder['Job_Order_Status'] ?? '') === 'DRAFT')
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-orange-50 text-orange-700 border border-orange-200 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500 mr-1.5"></span>
                                    DRAFT
                                </span>
                            @elseif(($jobOrder['Job_Order_Status'] ?? '') === 'CANCELLED')
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-gray-50 text-gray-700 border border-gray-200 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-500 mr-1.5"></span>
                                    CANCELLED
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 shadow-sm">
                                    {{ $jobOrder['Job_Order_Status'] ?? 'OPEN' }}
                                </span>
                            @endif
                        @endif
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                        <a href="{{ route('job-orders.show', $jobOrder['Job_Order_ID']) }}" class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-lg" title="Detail">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </a>
                        <a href="{{ route('job-orders.edit', $jobOrder['Job_Order_ID']) }}" class="text-blue-500 hover:text-blue-700 transition-colors p-2 hover:bg-blue-50 rounded-lg" title="Edit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </a>
                        <form action="{{ route('job-orders.destroy', $jobOrder['Job_Order_ID']) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan Job Order ini?');" class="inline">
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
                    <td colspan="6" class="px-6 py-16 text-center text-gray-500 bg-gray-50/50">
                        <div class="flex flex-col items-center justify-center">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <p class="font-bold text-gray-600">Tidak ada Job Order ditemukan.</p>
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
            Menampilkan <span class="font-bold text-gray-900">{{ $jobOrders->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-gray-900">{{ $jobOrders->lastItem() ?? 0 }}</span> dari <span class="font-bold text-gray-900">{{ $jobOrders->total() }}</span> total data
        </div>
        <div>
            {{ $jobOrders->links('pagination::tailwind') }}
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const companyFilter = document.getElementById('companyFilter');
        const rows = document.querySelectorAll('.filter-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;
            const companyValue = companyFilter.value;

            rows.forEach(row => {
                const searchString = row.getAttribute('data-search');
                const rowStatus = row.getAttribute('data-status');
                const rowCompany = row.getAttribute('data-company');
                
                const matchesSearch = searchString.includes(searchTerm);
                const matchesStatus = statusValue === 'ALL' || rowStatus === statusValue;
                const matchesCompany = companyValue === 'ALL' || rowCompany === companyValue;
                
                if (matchesSearch && matchesStatus && matchesCompany) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
        companyFilter.addEventListener('change', filterTable);
    });
</script>
@endsection
