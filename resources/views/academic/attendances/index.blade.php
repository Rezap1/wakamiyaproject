@extends('layouts.app')
@section('header', 'Manajemen Kehadiran')
@section('content')

<x-universal.index-layout 
    title="Dashboard Kehadiran" 
    description="Pantau dan kelola kehadiran karyawan dan siswa."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Kehadiran' => route('attendances.index')]"
    add-action="{{ route('attendances.create') }}"
    add-text="Catat Kehadiran"
>
    @php
        $attItems = method_exists($attendances, 'items') ? $attendances->items() : $attendances;
        $attColl = collect($attItems ?? [])->filter(fn($a) => !empty($a['Student_ID']));
        
        $totalHadir = $attColl->filter(fn($a) => in_array(strtolower($a['Status'] ?? ''), ['hadir', 'present']))->count();
        $totalTerlambat = $attColl->filter(fn($a) => in_array(strtolower($a['Status'] ?? ''), ['terlambat', 'late']))->count();
        $totalIzinSakit = $attColl->filter(fn($a) => in_array(strtolower($a['Status'] ?? ''), ['sakit', 'izin', 'sick', 'leave', 'permission']))->count();
        $totalAlpha = $attColl->filter(fn($a) => in_array(strtolower($a['Status'] ?? ''), ['alpha', 'absent']))->count();
        $totalAll = $attColl->count();
        $persentase = $totalAll > 0 ? round((($totalHadir + $totalTerlambat) / $totalAll) * 100, 1) : 0;
    @endphp
    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-6">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Total Hadir</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalHadir }}</h3>
                <span class="text-[10px] font-bold text-green-500 bg-green-50 px-2 py-0.5 rounded-full mt-2 inline-block">Siswa</span>
            </div>
            <div class="w-12 h-12 bg-green-50 text-green-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Terlambat</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalTerlambat }}</h3>
                <span class="text-[10px] font-bold text-yellow-500 bg-yellow-50 px-2 py-0.5 rounded-full mt-2 inline-block">Siswa</span>
            </div>
            <div class="w-12 h-12 bg-yellow-50 text-yellow-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Izin / Sakit</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalIzinSakit }}</h3>
                <span class="text-[10px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full mt-2 inline-block">Siswa</span>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Alpha</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalAlpha }}</h3>
                <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full mt-2 inline-block">Siswa</span>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Persentase</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $persentase }}%</h3>
                <span class="text-[10px] font-bold text-slate-500 mt-2 inline-block">Total Data: {{ $totalAll }}</span>
            </div>
            <div class="w-16 h-16 absolute -right-3 -bottom-3 text-green-100 opacity-50 group-hover:scale-110 transition-transform">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
            </div>
        </div>
    </div>

    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="attendances" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('attendances.index') }}" 
            refresh-url="{{ route('attendances.index') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto flex flex-col md:flex-row items-center gap-2">
                <select name="class_id" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 font-medium focus:ring-blue-500 focus:border-blue-500 min-w-[200px]" onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    @foreach($classOptions ?? [] as $id => $label)
                        <option value="{{ $id }}" {{ request('class_id') == $id ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 font-medium focus:ring-blue-500 focus:border-blue-500" value="{{ request('date', date('Y-m-d')) }}" onchange="this.form.submit()">
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="empty($attendances) && !request('search')" empty-title="Data Kehadiran Kosong" empty-description="Belum ada catatan kehadiran untuk tanggal yang dipilih.">
        <x-slot:header>
            <th class="px-6 py-4">ID</th>
            <th class="px-6 py-4">Siswa</th>
            <th class="px-6 py-4">Tanggal</th>
            <th class="px-6 py-4">Waktu Pencatatan</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @forelse($attendances ?? [] as $item)
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <span class="font-bold text-slate-800 block">{{ $item['Attendance_ID'] ?? 'ATD-'.rand(100,999) }}</span>
                </td>
                <td class="px-6 py-4 font-semibold text-slate-800">
                    <div class="flex items-center">
                        @php
                            $studentId = $item['Student_ID'] ?? 'Unknown';
                            $studentName = isset($students[$studentId]) ? ($students[$studentId]['Full_Name'] ?? $studentId) : $studentId;
                            $initial = substr($studentName, 0, 1);
                            
                            $classId = $item['Class_ID'] ?? $item['Schedule_ID'] ?? '-';
                            $className = isset($classOptions[$classId]) ? $classOptions[$classId] : $classId;
                        @endphp
                        <div class="w-8 h-8 rounded-full bg-slate-200 mr-3 flex-shrink-0 flex items-center justify-center text-xs font-bold text-slate-500">
                            {{ $initial }}
                        </div>
                        <div>
                            <p class="text-sm">{{ $studentName }}</p>
                            <p class="text-[11px] text-slate-500 font-medium">{{ $studentId }} &bull; {{ $className }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 font-bold text-slate-700">{{ !empty($item['Attendance_Date']) ? \Carbon\Carbon::parse($item['Attendance_Date'])->format('d M Y') : '-' }}</td>
                <td class="px-6 py-4 font-bold text-slate-700">{{ !empty($item['Created_At']) ? \Carbon\Carbon::parse($item['Created_At'])->format('H:i') : '-' }}</td>
                <td class="px-6 py-4">
                    @php
                        $status = strtolower($item['Status'] ?? 'hadir');
                        $badgeColor = match($status) {
                            'hadir', 'present' => 'green',
                            'terlambat', 'late' => 'yellow',
                            'sakit', 'izin', 'sick', 'leave', 'permission' => 'blue',
                            'alpha', 'absent' => 'red',
                            default => 'slate',
                        };
                        
                        $displayStatus = match($status) {
                            'present' => 'Hadir',
                            'late' => 'Terlambat',
                            'sick' => 'Sakit',
                            'leave', 'permission' => 'Izin',
                            'absent' => 'Alpha',
                            default => ucfirst($item['Status'] ?? 'Hadir'),
                        };
                    @endphp
                    <x-badge color="{{ $badgeColor }}">{{ $displayStatus }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex justify-end gap-2">
                        <x-universal.action-button action="detail" url="{{ route('attendances.show', $item['Attendance_ID'] ?? 1) }}" />
                        <x-universal.action-button action="edit" url="{{ route('attendances.edit', $item['Attendance_ID'] ?? 1) }}" />
                        <form action="{{ route('attendances.destroy', $item['Attendance_ID'] ?? 1) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');" class="inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-6 py-4 text-center text-slate-500">
                    <div class="flex flex-col items-center justify-center py-8">
                        <svg class="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <p class="text-slate-500 font-medium">Tidak ada data kehadiran ditemukan.</p>
                    </div>
                </td>
            </tr>
        @endforelse
        
        <x-slot:pagination>
            @if(is_object($attendances) && method_exists($attendances, 'links'))
                <x-universal.pagination :paginator="$attendances" />
            @else
                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Showing entries</span>
                </div>
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>

@endsection



