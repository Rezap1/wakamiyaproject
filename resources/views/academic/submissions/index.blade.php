@extends('layouts.app')

@section('header', 'Pengumpulan Tugas')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Pengumpulan Tugas (Submissions)" 
        description="Review tugas yang dikumpulkan siswa dan berikan penilaian."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Pengumpulan' => route('submissions.index')]"
    >
        <x-slot:actions>
            <x-button as="a" href="{{ route('submissions.index') }}" variant="secondary" title="Refresh Data">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </x-button>
            <x-button as="a" href="{{ route('submissions.create') }}" variant="primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Input Pengumpulan Manual
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <x-input id="searchInput" placeholder="Cari Student ID atau Assignment ID..." icon="<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'></path></svg>" />
        </div>
        <div>
            <x-select id="statusFilter">
                <option value="ALL">Semua Status</option>
                <option value="Submitted">Submitted (Menunggu Penilaian)</option>
                <option value="Graded">Graded (Sudah Dinilai)</option>
                <option value="Returned">Returned (Dikembalikan)</option>
                <option value="Late">Late (Terlambat)</option>
            </x-select>
        </div>
    </div>

    <x-table :empty="count($submissions) === 0">
        <x-slot:header>
            <th class="px-6 py-4">Siswa</th>
            <th class="px-6 py-4">Tugas / Tanggal</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-center">Nilai</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($submissions as $s)
        <tr class="hover:bg-slate-50 transition-colors filter-row" 
            data-search="{{ strtolower(($s['Student_ID'] ?? '').($s['Assignment_ID'] ?? '')) }}" 
            data-status="{{ $s['Status'] ?? '' }}">
            
            <td class="px-6 py-4">
                <div class="flex items-center">
                    <div class="h-8 w-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-600 font-bold text-xs mr-3">
                        {{ substr($s['Student_ID'] ?? 'S', 0, 1) }}
                    </div>
                    <div class="font-bold text-slate-800">{{ $s['Student_ID'] ?? '-' }}</div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm font-bold text-indigo-700 mb-1">{{ $s['Assignment_ID'] ?? '-' }}</div>
                <div class="text-[11px] font-medium text-slate-500 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ $s['Submission_Date'] ?? '-' }}
                </div>
            </td>
            <td class="px-6 py-4">
                @php
                    $status = $s['Status'] ?? '';
                    $statusColor = match($status) {
                        'Graded' => 'green',
                        'Submitted' => 'blue',
                        'Late' => 'orange',
                        'Returned' => 'red',
                        default => 'gray'
                    };
                @endphp
                <x-badge color="{{ $statusColor }}" class="uppercase text-[10px] font-bold">{{ $status ?: '-' }}</x-badge>
            </td>
            <td class="px-6 py-4 text-center">
                @if(($s['Status'] ?? '') == 'Graded' || isset($s['Grade_Received']))
                    <div class="inline-flex flex-col items-center justify-center w-12 h-10 rounded-xl bg-green-50 border border-green-100">
                        <span class="text-[13px] font-bold text-green-700 leading-none">{{ $s['Grade_Received'] ?? '-' }}</span>
                    </div>
                @else
                    <span class="text-xs font-medium text-slate-400 italic">Belum Dinilai</span>
                @endif
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('submissions.edit', $s['Submission_ID']) }}" class="px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg text-[11px] font-bold transition-colors" title="Review">
                        Review / Nilai
                    </a>
                    <form action="{{ route('submissions.destroy', $s['Submission_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus submission ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Hapus">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        @endforeach
    </x-table>
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

        if (searchInput) searchInput.addEventListener('input', filterTable);
        if (statusFilter) statusFilter.addEventListener('change', filterTable);
    });
</script>
@endsection



