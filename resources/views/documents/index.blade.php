@extends('layouts.app')

@section('header', 'Manajemen Dokumen')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8 mt-2">
        <div>
            <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Dokumen Persyaratan</h2>
            <p class="text-sm font-medium text-gray-500 mt-2">Kelola berkas, paspor, visa, dan dokumen legal lainnya milik kandidat.</p>
        </div>
        <a href="{{ route('documents.create') }}" class="inline-flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 shadow-lg shadow-primary-500/30 transition-all hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Tambah Dokumen
        </a>
    </div>

    <!-- Stats & Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
        <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
            <div class="w-full lg:w-96 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="searchInput" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 focus:bg-white transition-colors sm:text-sm font-medium" placeholder="Cari nomor dokumen, siswa, nama file...">
            </div>
            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <select id="filterType" class="block w-full md:w-40 pl-3 pr-10 py-3 text-sm border-gray-200 rounded-xl focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-gray-50 font-medium">
                    <option value="">Semua Jenis</option>
                    <option value="PASSPORT">Paspor</option>
                    <option value="VISA">Visa</option>
                    <option value="CONTRACT">Kontrak</option>
                    <option value="MEDICAL">Medical</option>
                    <option value="CERTIFICATE">Sertifikat</option>
                    <option value="OTHER">Lainnya</option>
                </select>
                <select id="filterStatus" class="block w-full md:w-40 pl-3 pr-10 py-3 text-sm border-gray-200 rounded-xl focus:outline-none focus:ring-primary-500 focus:border-primary-500 bg-gray-50 font-medium">
                    <option value="">Semua Status</option>
                    <option value="PENDING">Pending</option>
                    <option value="VERIFIED">Terverifikasi</option>
                    <option value="REJECTED">Ditolak</option>
                    <option value="EXPIRED">Kedaluwarsa</option>
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
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Info Dokumen</th>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Pemilik (Siswa)</th>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tipe & Nama File</th>
                        <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status Validitas</th>
                        <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($documents as $doc)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold border border-indigo-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-gray-900">{{ $doc['Document_Number'] ?? $doc['Document_ID'] }}</div>
                                    <div class="text-xs font-medium text-gray-500 mt-0.5">
                                        {{ $doc['Document_Name'] }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-gray-900 search-student">{{ $doc['Student_Name'] ?? 'Siswa Tidak Diketahui' }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">NIS: {{ $doc['Student_Registration_Number'] ?? '-' }}</div>
                            @if(!empty($doc['Application_Number']))
                                <div class="text-[10px] text-blue-600 mt-0.5 font-semibold">Terkait APP: {{ $doc['Application_Number'] }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-bold border bg-gray-50 text-gray-700 border-gray-200 search-type">
                                {{ $doc['Document_Type'] ?? 'Lainnya' }}
                            </span>
                            <div class="text-xs text-blue-500 font-medium mt-1.5 truncate max-w-[200px]" title="{{ $doc['File_Name'] ?? 'Tanpa File' }}">
                                @if(!empty($doc['File_URL']))
                                    <a href="{{ $doc['File_URL'] }}" target="_blank" class="hover:underline flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                        {{ $doc['File_Name'] ?? 'Lihat Berkas' }}
                                    </a>
                                @else
                                    <span class="text-gray-400">Tidak ada lampiran</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="flex flex-col gap-1 items-center">
                                @php
                                    $status = $doc['Document_Status'] ?? '';
                                    $statusColor = match($status) {
                                        'VERIFIED' => 'bg-green-50 text-green-700 border-green-200',
                                        'PENDING' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'REJECTED' => 'bg-red-50 text-red-700 border-red-200',
                                        'EXPIRED' => 'bg-gray-100 text-gray-600 border-gray-300',
                                        default => 'bg-gray-50 text-gray-700 border-gray-200'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-bold border {{ $statusColor }} search-status">
                                    {{ $status ?: 'UNKNOWN' }}
                                </span>
                                
                                @if(!empty($doc['Expiry_Date']))
                                    @php
                                        $isExpired = \Carbon\Carbon::parse($doc['Expiry_Date'])->isPast();
                                        $expColor = $isExpired ? 'text-red-600' : 'text-gray-500';
                                    @endphp
                                    <div class="text-[10px] font-medium mt-1 {{ $expColor }}">
                                        Exp: {{ \Carbon\Carbon::parse($doc['Expiry_Date'])->format('d M Y') }}
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('documents.show', $doc['Document_ID']) }}" class="p-2 text-teal-600 hover:bg-teal-50 rounded-lg transition-colors" title="Detail">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                                <a href="{{ route('documents.edit', $doc['Document_ID']) }}" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('documents.destroy', $doc['Document_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?');">
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
                            <svg class="mx-auto h-12 w-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <p class="text-base font-bold text-gray-900">Belum Ada Dokumen</p>
                            <p class="text-sm text-gray-500 mt-1">Tambahkan dokumen baru untuk melengkapi berkas kandidat.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination Placeholder -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <span class="text-sm text-gray-500 font-medium">Menampilkan <span class="font-bold text-gray-900" id="visibleCount">{{ count($documents) }}</span> dari <span class="font-bold text-gray-900">{{ count($documents) }}</span> dokumen</span>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const filterType = document.getElementById('filterType');
        const filterStatus = document.getElementById('filterStatus');
        const table = document.getElementById('dataTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        const visibleCountEl = document.getElementById('visibleCount');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const typeTerm = filterType.value.toLowerCase();
            const statusTerm = filterStatus.value.toLowerCase();
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                if (rows[i].getElementsByTagName('td').length === 1) continue; // Skip empty row
                
                const textContent = rows[i].textContent.toLowerCase();
                const typeContent = rows[i].querySelector('.search-type').textContent.toLowerCase();
                const statusContent = rows[i].querySelector('.search-status').textContent.toLowerCase();
                
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

        searchInput.addEventListener('keyup', filterTable);
        filterType.addEventListener('change', filterTable);
        filterStatus.addEventListener('change', filterTable);
    });
</script>
@endsection
