@extends('layouts.app')

@section('header', 'Master Jadwal')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Jadwal Kelas (Schedules)" 
        description="Kelola jadwal pelajaran mingguan untuk setiap kelas."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Jadwal' => route('schedules.index')]"
    >
        <x-slot:actions>
            <x-universal.multi-export route-prefix="schedules" />
            <x-button as="a" href="{{ route('schedules.index') }}" variant="secondary" title="Refresh Data">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </x-button>
            <x-button as="a" href="{{ route('schedules.create') }}" variant="primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Tambah Jadwal
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <x-input id="searchInput" placeholder="Cari Kelas, Subject, atau Pengajar..." icon="<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'></path></svg>" />
        </div>
        <div>
            <x-select id="dayFilter">
                <option value="ALL">Semua Hari</option>
                <option value="Monday">Senin</option>
                <option value="Tuesday">Selasa</option>
                <option value="Wednesday">Rabu</option>
                <option value="Thursday">Kamis</option>
                <option value="Friday">Jumat</option>
                <option value="Saturday">Sabtu</option>
                <option value="Sunday">Minggu</option>
            </x-select>
        </div>
    </div>

    @php
        $dayMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
        ];
    @endphp

    @if(($scheduleGroups ?? collect())->count() > 0)
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
            @foreach($scheduleGroups as $group)
                <details class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden group">
                    <summary class="cursor-pointer list-none p-4 flex items-center justify-between gap-3 hover:bg-slate-50">
                        <div>
                            <h3 class="text-sm font-black text-slate-800">{{ $dayMap[$group['title']] ?? $group['title'] }}</h3>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $group['total'] }} jadwal kelas</p>
                        </div>
                        <span class="text-slate-400 group-open:rotate-180 transition-transform">v</span>
                    </summary>
                    <div class="border-t border-slate-100 divide-y divide-slate-100">
                        @foreach($group['items'] as $schedule)
                            <a href="{{ route('schedules.edit', $schedule['Schedule_ID']) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">{{ $schedule['Class_Name'] ?? $schedule['Class_ID'] ?? '-' }} | {{ $schedule['Subject_Name'] ?? $schedule['Subject_ID'] ?? '-' }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $schedule['Teacher_Name'] ?? $schedule['Teacher_ID'] ?? '-' }}</p>
                                </div>
                                <span class="text-xs font-black text-slate-700 shrink-0">{{ $schedule['Start_Time'] ?? '--:--' }} - {{ $schedule['End_Time'] ?? '--:--' }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    <x-table :empty="count($schedules) === 0">
        <x-slot:header>
            <th class="px-6 py-4">Mata Pelajaran & Kelas</th>
            <th class="px-6 py-4">Pengajar</th>
            <th class="px-6 py-4">Ruangan</th>
            <th class="px-6 py-4">Jadwal (Hari/Waktu)</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($schedules as $schedule)
        <tr class="hover:bg-slate-50 transition-colors filter-row" 
            data-search="{{ strtolower(($schedule['Class_Name'] ?? '').($schedule['Subject_Name'] ?? '').($schedule['Teacher_Name'] ?? '').($schedule['Class_ID'] ?? '').($schedule['Subject_ID'] ?? '').($schedule['Teacher_ID'] ?? '')) }}" 
            data-day="{{ $schedule['Day_Of_Week'] ?? '' }}">
            
            <td class="px-6 py-4">
                <x-badge color="purple" class="mb-1 block w-max">{{ $schedule['Subject_Name'] ?? $schedule['Subject_ID'] ?? 'Unknown Subject' }}</x-badge>
                <div class="font-bold text-slate-800">{{ $schedule['Class_Name'] ?? $schedule['Class_ID'] ?? 'Unknown Class' }}</div>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center">
                    <div class="h-8 w-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-600 font-bold text-xs mr-3">
                        {{ substr($schedule['Teacher_Name'] ?? $schedule['Teacher_ID'] ?? 'T', 0, 1) }}
                    </div>
                    <div class="text-sm font-bold text-slate-800">{{ $schedule['Teacher_Name'] ?? $schedule['Teacher_ID'] ?? '-' }}</div>
                </div>
            </td>
            <td class="px-6 py-4">
                <x-badge color="cyan">{{ $schedule['Room_Name'] ?? 'No Room' }}</x-badge>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2 mb-1">
                    <x-badge color="blue">{{ $dayMap[$schedule['Day_Of_Week'] ?? ''] ?? $schedule['Day_Of_Week'] ?? '-' }}</x-badge>
                </div>
                <div class="text-[13px] font-mono font-medium text-slate-500">
                    {{ $schedule['Start_Time'] ?? '--:--' }} - {{ $schedule['End_Time'] ?? '--:--' }}
                </div>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('schedules.edit', $schedule['Schedule_ID']) }}" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </a>
                    <form action="{{ route('schedules.destroy', $schedule['Schedule_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus jadwal ini?');">
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
        const dayFilter = document.getElementById('dayFilter');
        const rows = document.querySelectorAll('.filter-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const dayValue = dayFilter.value;

            rows.forEach(row => {
                const searchString = row.getAttribute('data-search');
                const rowDay = row.getAttribute('data-day');
                
                const matchesSearch = searchString.includes(searchTerm);
                const matchesDay = dayValue === 'ALL' || rowDay === dayValue;
                
                if (matchesSearch && matchesDay) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        if (searchInput) searchInput.addEventListener('input', filterTable);
        if (dayFilter) dayFilter.addEventListener('change', filterTable);
    });
</script>
@endsection



