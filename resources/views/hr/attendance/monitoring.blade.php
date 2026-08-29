@extends('layouts.app')
@section('header', 'Monitoring Kehadiran Pegawai')
@section('content')

<x-universal.index-layout 
    title="Monitoring Kehadiran Pegawai" 
    description="Pantau daftar riwayat kehadiran pegawai dari Master Attendance."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Kehadiran Pegawai' => '#']"
>
    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-6">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Hadir</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalHadir }}</h3>
                <span class="text-[10px] font-bold text-green-500 bg-green-50 px-2 py-0.5 rounded-full mt-2 inline-block">Pegawai</span>
            </div>
            <div class="w-12 h-12 bg-green-50 text-green-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Terlambat</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalTerlambat }}</h3>
                <span class="text-[10px] font-bold text-yellow-500 bg-yellow-50 px-2 py-0.5 rounded-full mt-2 inline-block">Pegawai</span>
            </div>
            <div class="w-12 h-12 bg-yellow-50 text-yellow-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Izin / Sakit</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalIzinSakit }}</h3>
                <span class="text-[10px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full mt-2 inline-block">Pegawai</span>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Alpha</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalAlpha }}</h3>
                <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full mt-2 inline-block">Pegawai</span>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Total Data</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalAll }}</h3>
                <span class="text-[10px] font-bold text-slate-500 mt-2 inline-block">Hasil Filter</span>
            </div>
            <div class="w-16 h-16 absolute -right-3 -bottom-3 text-slate-100 opacity-50 group-hover:scale-110 transition-transform">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
            </div>
        </div>
    </div>

    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('hr.attendance.monitoring') }}" 
            refresh-url="{{ route('hr.attendance.monitoring') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto flex flex-col md:flex-row items-center gap-2">
                <select name="status" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 font-medium focus:ring-blue-500 focus:border-blue-500 min-w-[150px]" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="PRESENT" {{ request('status') == 'PRESENT' ? 'selected' : '' }}>Hadir</option>
                    <option value="LATE" {{ request('status') == 'LATE' ? 'selected' : '' }}>Terlambat</option>
                    <option value="SICK" {{ request('status') == 'SICK' ? 'selected' : '' }}>Sakit</option>
                    <option value="PERMISSION" {{ request('status') == 'PERMISSION' ? 'selected' : '' }}>Izin</option>
                    <option value="ABSENT" {{ request('status') == 'ABSENT' ? 'selected' : '' }}>Alpha</option>
                </select>
                <input type="text" name="employee_id" placeholder="Cari ID Pegawai..." class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 font-medium focus:ring-blue-500 focus:border-blue-500" value="{{ request('employee_id') }}">
                <div class="flex items-center gap-2">
                    <input type="date" name="start_date" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 font-medium focus:ring-blue-500 focus:border-blue-500" value="{{ request('start_date', request('date', date('Y-m-d'))) }}" onchange="this.form.submit()">
                    <span class="text-slate-500 font-bold">-</span>
                    <input type="date" name="end_date" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 font-medium focus:ring-blue-500 focus:border-blue-500" value="{{ request('end_date') }}" onchange="this.form.submit()">
                </div>
                <a href="{{ route('hr.attendance.monitoring') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-xl text-slate-700 font-bold text-sm whitespace-nowrap">Reset Filter</a>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <!-- DESKTOP TABLE VIEW -->
    <div class="hidden md:block">
        <x-universal.data-table :empty="empty($paginated->items()) && !request('search')" empty-title="Data Kehadiran Kosong" empty-description="Belum ada catatan kehadiran pegawai untuk tanggal yang dipilih.">
            <x-slot:header>
                <th class="px-6 py-4">Tanggal</th>
                <th class="px-6 py-4">Pegawai</th>
                <th class="px-6 py-4">Jam Masuk</th>
                <th class="px-6 py-4">Jam Pulang</th>
                <th class="px-6 py-4">Status</th>
            </x-slot:header>

            @forelse($paginated ?? [] as $item)
                @php
                    $employeeId = $item['Employee_ID'] ?? 'Unknown';
                    $employeeName = isset($employeesMap[$employeeId]) ? ($employeesMap[$employeeId]['Full_Name'] ?? $employeeId) : $employeeId;
                    $initial = substr($employeeName, 0, 1);
                    
                    $normalizedStatus = \App\Helpers\AttendanceStatusHelper::normalize($item['Status'] ?? '');
                    $badgeColor = \App\Helpers\AttendanceStatusHelper::badgeColor($item['Status'] ?? '');
                    $displayStatus = \App\Helpers\AttendanceStatusHelper::label($item['Status'] ?? '');

                    $checkIn = !empty($item['Check_In_Time']) ? \Carbon\Carbon::parse($item['Check_In_Time'])->format('H:i') : '-';
                    $checkOut = !empty($item['Check_Out_Time']) ? \Carbon\Carbon::parse($item['Check_Out_Time'])->format('H:i') : 'Belum Check-Out';
                    $dateFormatted = !empty($item['Attendance_Date']) ? \Carbon\Carbon::parse($item['Attendance_Date'])->format('d M Y') : '-';
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700">{{ $dateFormatted }}</td>
                    <td class="px-6 py-4 font-semibold text-slate-800">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-slate-200 mr-3 flex-shrink-0 flex items-center justify-center text-xs font-bold text-slate-500">
                                {{ $initial }}
                            </div>
                            <div>
                                <p class="text-sm">{{ $employeeName }}</p>
                                <p class="text-[11px] text-slate-500 font-medium">{{ $employeeId }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-bold text-slate-700">{{ $checkIn }}</td>
                    <td class="px-6 py-4 font-bold text-slate-500">{{ $checkOut }}</td>
                    <td class="px-6 py-4">
                        <x-badge color="{{ $badgeColor }}">{{ $displayStatus }}</x-badge>
                        @if($normalizedStatus === 'LATE' && !empty($item['Late_Minutes']))
                            <span class="text-[10px] text-red-500 block mt-1 font-bold">{{ $item['Late_Minutes'] }} Menit</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-slate-500">
                        <div class="flex flex-col items-center justify-center py-8">
                            <svg class="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <p class="text-slate-500 font-medium">Tidak ada data kehadiran ditemukan.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
            
            <x-slot:pagination>
                @if(is_object($paginated) && method_exists($paginated, 'links'))
                    <x-universal.pagination :paginator="$paginated" />
                @endif
            </x-slot:pagination>
        </x-universal.data-table>
    </div>

    <!-- MOBILE CARD VIEW -->
    <div class="md:hidden space-y-4 mt-6">
        @forelse($paginated ?? [] as $item)
            @php
                $employeeId = $item['Employee_ID'] ?? 'Unknown';
                $employeeName = isset($employeesMap[$employeeId]) ? ($employeesMap[$employeeId]['Full_Name'] ?? $employeeId) : $employeeId;
                
                $normalizedStatus = \App\Helpers\AttendanceStatusHelper::normalize($item['Status'] ?? '');
                $badgeColor = \App\Helpers\AttendanceStatusHelper::badgeColor($item['Status'] ?? '');
                $displayStatus = \App\Helpers\AttendanceStatusHelper::label($item['Status'] ?? '');
                
                $dateFormatted = !empty($item['Attendance_Date']) ? \Carbon\Carbon::parse($item['Attendance_Date'])->format('d M Y') : '-';
                $checkIn = !empty($item['Check_In_Time']) ? \Carbon\Carbon::parse($item['Check_In_Time'])->format('H:i') : '-';
                $checkOut = !empty($item['Check_Out_Time']) ? \Carbon\Carbon::parse($item['Check_Out_Time'])->format('H:i') : 'Belum Check-Out';
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase">{{ $dateFormatted }}</p>
                        <h4 class="text-base font-bold text-slate-800 mt-1">{{ $employeeName }}</h4>
                        <p class="text-xs text-slate-500">{{ $employeeId }}</p>
                    </div>
                    <div class="text-right">
                        <x-badge color="{{ $badgeColor }}">{{ $displayStatus }}</x-badge>
                        @if($normalizedStatus === 'LATE' && !empty($item['Late_Minutes']))
                            <span class="text-[10px] text-red-500 block mt-1 font-bold">{{ $item['Late_Minutes'] }} Mnt</span>
                        @endif
                    </div>
                </div>
                
                <div class="bg-slate-50 rounded-lg p-3 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Jam Masuk</p>
                        <p class="text-sm font-bold text-slate-800">{{ $checkIn }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Jam Pulang</p>
                        <p class="text-sm font-bold text-slate-600">{{ $checkOut }}</p>
                    </div>
                </div>
            </div>
        @empty
            <x-universal.empty-state title="Data Kehadiran Kosong" description="Belum ada catatan kehadiran untuk tanggal yang dipilih." />
        @endforelse
        
        @if(is_object($paginated) && method_exists($paginated, 'links'))
            <div class="pt-4 pb-2">
                <x-universal.pagination :paginator="$paginated" />
            </div>
        @endif
    </div>

</x-universal.index-layout>
@endsection
