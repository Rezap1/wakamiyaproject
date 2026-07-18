@extends('layouts.app')

@section('header', 'Manajemen Visa')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8 mt-2">
        <div>
            <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Manajemen Visa</h2>
            <p class="text-sm font-medium text-gray-500 mt-2">Kelola pendaftaran dan status Visa kandidat di kedutaan.</p>
        </div>
        <a href="{{ route('visas.create') }}" class="inline-flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 shadow-lg shadow-primary-500/30 transition-all hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Tambah Visa Baru
        </a>
    </div>

    <!-- Stats & Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
        <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
            <div class="w-full lg:w-96 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="searchInput" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 focus:bg-white transition-colors sm:text-sm font-medium" placeholder="Cari No. Visa, Paspor, atau Siswa...">
            </div>
            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <select id="filterStatus" class="block w-full md:w-48 pl-3 pr-10 py-3 text-sm border-gray-200 rounded-xl focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-gray-50 font-medium">
                    <option value="">Semua Status</option>
                    <option value="PREPARING">Preparing (Persiapan)</option>
                    <option value="SUBMITTED">Submitted (Diajukan)</option>
                    <option value="APPROVED">Approved (Disetujui)</option>
                    <option value="REJECTED">Rejected (Ditolak)</option>
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
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No. Visa & Tipe</th>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Kandidat & Paspor</th>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Relasi</th>
                        <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status / Timeline</th>
                        <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($visas as $visa)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 flex-shrink-0 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-bold border border-teal-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-gray-900">{{ $visa['Visa_Number'] }}</div>
                                    <div class="text-xs font-medium text-gray-500 mt-0.5">{{ $visa['Visa_Type'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-gray-900 search-student">{{ $visa['Student_Name'] ?? 'Data Siswa Dihapus' }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">NIS: {{ $visa['Student_Registration_Number'] ?? '-' }}</div>
                            <div class="text-[10px] text-gray-600 font-semibold mt-1 bg-gray-100 inline-block px-2 py-0.5 rounded">Paspor: {{ $visa['Passport_Number'] ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if(!empty($visa['COE_Number']))
                                <div class="text-sm font-medium text-gray-900">COE: {{ $visa['COE_Number'] }}</div>
                            @else
                                <div class="text-sm text-gray-400 italic">Tanpa COE</div>
                            @endif
                            @if(!empty($visa['Application_Number']))
                                <div class="text-xs text-blue-600 mt-1 font-semibold">APP: {{ $visa['Application_Number'] }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="flex flex-col gap-1.5 items-center">
                                @php
                                    $status = $visa['Visa_Status'] ?? '';
                                    $statusColor = match($status) {
                                        'APPROVED' => 'bg-green-50 text-green-700 border-green-200',
                                        'SUBMITTED' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'PREPARING' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'REJECTED' => 'bg-red-50 text-red-700 border-red-200',
                                        default => 'bg-gray-50 text-gray-700 border-gray-200'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-bold border {{ $statusColor }} search-status">
                                    {{ $status ?: 'UNKNOWN' }}
                                </span>
                                
                                @if($status === 'APPROVED' && !empty($visa['Issue_Date']))
                                    <div class="text-[10px] font-medium text-green-600 mt-0.5 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        {{ \Carbon\Carbon::parse($visa['Issue_Date'])->format('d/m/Y') }}
                                    </div>
                                @elseif($status === 'SUBMITTED' && !empty($visa['Submission_Date']))
                                    <div class="text-[10px] font-medium text-blue-600 mt-0.5">
                                        {{ \Carbon\Carbon::parse($visa['Submission_Date'])->format('d/m/Y') }}
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('visas.show', $visa['Visa_ID']) }}" class="p-2 text-teal-600 hover:bg-teal-50 rounded-lg transition-colors" title="Detail">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                                <a href="{{ route('visas.edit', $visa['Visa_ID']) }}" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('visas.destroy', $visa['Visa_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data Visa ini?');">
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
                            <svg class="mx-auto h-12 w-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            <p class="text-base font-bold text-gray-900">Belum Ada Data Visa</p>
                            <p class="text-sm text-gray-500 mt-1">Tambahkan pengajuan Visa baru.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination Placeholder -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <span class="text-sm text-gray-500 font-medium">Menampilkan <span class="font-bold text-gray-900" id="visibleCount">{{ count($visas) }}</span> dari <span class="font-bold text-gray-900">{{ count($visas) }}</span> Visa</span>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const filterStatus = document.getElementById('filterStatus');
        const table = document.getElementById('dataTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        const visibleCountEl = document.getElementById('visibleCount');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusTerm = filterStatus.value.toLowerCase();
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                if (rows[i].getElementsByTagName('td').length === 1) continue; // Skip empty row
                
                const textContent = rows[i].textContent.toLowerCase();
                const statusContent = rows[i].querySelector('.search-status').textContent.toLowerCase();
                
                const matchSearch = textContent.includes(searchTerm);
                const matchStatus = statusTerm === '' || statusContent.includes(statusTerm);

                if (matchSearch && matchStatus) {
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
        filterStatus.addEventListener('change', filterTable);
    });
</script>
@endsection
