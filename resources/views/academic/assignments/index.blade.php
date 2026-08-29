@extends('layouts.app')

@section('header', 'Tugas')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Daftar Tugas (Assignments)" 
        description="Kelola tugas siswa dan batas waktu."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Tugas' => route('assignments.index')]"
    >
        <x-slot:actions>
            <x-universal.multi-export route-prefix="assignments" />
            <x-button as="a" href="{{ route('assignments.index') }}" variant="secondary" title="Refresh Data">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </x-button>
            <x-button as="a" href="{{ route('assignments.create') }}" variant="primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Buat Tugas Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <x-input id="searchInput" placeholder="Cari Judul Tugas atau Pengajar..." icon="<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'></path></svg>" />
        </div>
        <div>
            <x-select id="statusFilter">
                <option value="ALL">Semua Status</option>
                <option value="Published">Published</option>
                <option value="PUBLISHED">PUBLISHED</option>
                <option value="Closed">Closed</option>
            </x-select>
        </div>
    </div>

    <x-table :empty="count($assignments) === 0">
        <x-slot:header>
            <th class="px-6 py-4">Informasi Tugas</th>
            <th class="px-6 py-4">Tenggat Waktu (Deadline)</th>
            <th class="px-6 py-4 text-center">Max Score</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($assignments as $a)
        <tr class="hover:bg-slate-50 transition-colors filter-row" 
            data-search="{{ strtolower(($a['Title'] ?? '').($a['Teacher_ID'] ?? '')) }}" 
            data-status="{{ $a['Status'] ?? 'PUBLISHED' }}">
            
            <td class="px-6 py-4">
                <div class="font-bold text-slate-800 text-sm mb-1">{{ $a['Title'] ?? 'No Title' }}</div>
                <div class="flex items-center text-[11px] text-slate-500 font-medium">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    By: {{ $a['Teacher_ID'] ?? '-' }}
                </div>
            </td>
            <td class="px-6 py-4">
                @php
                    $deadline = $a['Deadline'] ?? '';
                    $deadlineDate = $deadline ? strtotime($deadline) : null;
                    $now = time();
                    $daysLeft = $deadlineDate ? floor(($deadlineDate - $now) / (60 * 60 * 24)) : null;
                    
                    $deadlineColor = 'blue';
                    if ($daysLeft !== null) {
                        if ($daysLeft < 0) $deadlineColor = 'red';
                        elseif ($daysLeft <= 3) $deadlineColor = 'orange';
                        else $deadlineColor = 'blue';
                    }
                @endphp
                <x-badge color="{{ $deadlineColor }}" class="font-medium flex items-center w-max gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ $deadline ? date('d M Y, H:i', $deadlineDate) : 'No Deadline' }}
                </x-badge>
                @if($daysLeft !== null && $daysLeft >= 0)
                    <div class="text-[10px] font-bold text-slate-400 mt-1 ml-1">{{ $daysLeft }} hari lagi</div>
                @elseif($daysLeft !== null && $daysLeft < 0)
                    <div class="text-[10px] font-bold text-red-500 mt-1 ml-1">Terlewat {{ abs($daysLeft) }} hari</div>
                @endif
            </td>
            <td class="px-6 py-4 text-center">
                <div class="inline-flex flex-col items-center justify-center w-16 h-12 rounded-xl bg-slate-50 border border-slate-200">
                    <span class="text-[13px] font-bold text-slate-800 leading-none" title="Max Score">{{ $a['Max_Score'] ?? 100 }}</span>
                </div>
            </td>
            <td class="px-6 py-4">
                @php
                    $status = $a['Status'] ?? 'PUBLISHED';
                    $statusColor = match($status) {
                        'Published' => 'green',
                        'PUBLISHED' => 'gray',
                        'Closed' => 'red',
                        default => 'blue'
                    };
                @endphp
                <x-badge color="{{ $statusColor }}" class="uppercase text-[10px] font-bold">{{ $status }}</x-badge>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('assignments.edit', $a['Assignment_ID']) }}" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </a>
                    <form action="{{ route('assignments.destroy', $a['Assignment_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus tugas ini?');">
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



