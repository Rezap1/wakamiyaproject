@extends('layouts.app')
@section('header', 'Manajemen Kehadiran')
@section('content')

<x-universal.index-layout 
    title="Dashboard Kehadiran" 
    description="Pantau dan kelola kehadiran per kelas dan daftar siswa."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Kehadiran' => route('attendances.index')]"
    add-action="{{ route('attendances.create') }}"
    add-text="Catat Kehadiran"
>
    @php
        $classesItems = method_exists($paginatedClasses, 'items') ? $paginatedClasses->items() : $paginatedClasses;
        $classesColl = collect($classesItems ?? []);
        
        $totalHadir = $classesColl->sum('Hadir');
        $totalTerlambat = $classesColl->sum('Terlambat');
        $totalIzinSakit = $classesColl->sum('Izin') + $classesColl->sum('Sakit');
        $totalAlpha = $classesColl->sum('Alpha');
        $totalAll = $classesColl->sum('Total');
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
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Sakit</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $classesColl->sum('Sakit') }}</h3>
                <span class="text-[10px] font-bold text-yellow-500 bg-yellow-50 px-2 py-0.5 rounded-full mt-2 inline-block">Siswa</span>
            </div>
            <div class="w-12 h-12 bg-yellow-50 text-yellow-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Izin</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $classesColl->sum('Izin') }}</h3>
                <span class="text-[10px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full mt-2 inline-block">Siswa</span>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Alpa</p>
                <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalAlpha }}</h3>
                <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full mt-2 inline-block">Siswa</span>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl p-5 shadow-sm border border-indigo-200 flex items-center justify-between text-white relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-10 transform translate-x-4 -translate-y-4">
                <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <div class="relative z-10">
                <p class="text-[11px] font-bold text-indigo-100 uppercase tracking-wide">Persentase Kehadiran</p>
                <h3 class="text-2xl font-extrabold mt-1">{{ $persentase }}%</h3>
                <span class="text-[10px] font-medium text-indigo-100 mt-2 inline-block">Total Data: {{ $totalAll }}</span>
            </div>
        </div>
    </div>

    <x-slot:actions>
        <x-universal.multi-export route-prefix="attendances" />
    </x-slot:actions>
        <x-slot:toolbar>
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <form action="{{ route('attendances.index') }}" method="GET" class="flex flex-col lg:flex-row gap-4 items-end lg:items-center justify-between">
                <div class="flex-1 flex flex-col md:flex-row gap-3 w-full flex-wrap items-center">
                    
                    <!-- Search -->
                    <div class="w-full md:max-w-[200px] relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari siswa/ID..." class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block pl-9 p-2 transition-colors">
                    </div>
                    
                    <!-- Class -->
                    <div class="w-full md:w-auto">
                        <select name="class_id" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2 pr-8 transition-colors" onchange="this.form.submit()">
                            <option value="">Semua Kelas</option>
                            @foreach($classOptions ?? [] as $id => $label)
                                <option value="{{ $id }}" {{ request('class_id') == $id ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Date Range -->
                    <div class="flex items-center gap-1.5 w-full md:w-auto">
                        <input type="date" name="date" value="{{ request('date', date('Y-m-d')) }}" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2 transition-colors" onchange="this.form.submit()">
                        <span class="text-slate-400 font-bold">—</span>
                        <input type="date" name="date_end" value="{{ request('date_end') }}" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2 transition-colors" onchange="this.form.submit()">
                    </div>
                    
                    <!-- Status -->
                    <div class="w-full md:w-auto">
                        <select name="status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2 pr-8 transition-colors" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="Hadir" {{ request('status') == 'Hadir' ? 'selected' : '' }}>Hadir</option>
                            <option value="Terlambat" {{ request('status') == 'Terlambat' ? 'selected' : '' }}>Terlambat</option>
                            <option value="Izin" {{ request('status') == 'Izin' ? 'selected' : '' }}>Izin</option>
                            <option value="Sakit" {{ request('status') == 'Sakit' ? 'selected' : '' }}>Sakit</option>
                            <option value="Alpha" {{ request('status') == 'Alpha' ? 'selected' : '' }}>Alpa</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="hidden">Filter</button>
                </div>
                
                <!-- Action Tools -->
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('attendances.index') }}" class="flex items-center justify-center p-2.5 text-slate-500 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 hover:text-blue-600 focus:ring-2 focus:ring-blue-500 transition-colors shadow-sm" title="Segarkan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </a>
                    
                    @if(request()->anyFilled(['search', 'status', 'date', 'date_end', 'class_id']))
                        <a href="{{ route('attendances.index') }}" class="flex items-center justify-center p-2.5 text-red-500 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 focus:ring-2 focus:ring-red-500 transition-colors shadow-sm" title="Reset Filter">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </x-slot:toolbar>

    <!-- Desktop Table (Hidden on Mobile) -->
    <div class="hidden md:block">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-6">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-black text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4 w-1/4">KELAS</th>
                        <th class="px-6 py-4 w-1/3">NAMA KELAS</th>
                        <th class="px-6 py-4">TANGGAL</th>
                        <th class="px-6 py-4 text-center">STATISTIK KEHADIRAN</th>
                        <th class="px-6 py-4 text-right">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($paginatedClasses ?? [] as $item)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-600">
                                {{ $item['Class_Code'] ?? $item['Class_Name'] ?? 'Kelas tidak ditemukan' }}
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-800">
                                {{ $item['Class_Name'] }}
                                <div class="text-xs text-slate-500 font-medium mt-0.5">{{ $item['Total'] }} Siswa Terdaftar</div>
                            </td>
                            <td class="px-6 py-4 text-slate-600 font-medium">
                                {{ $item['Date_Display'] }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="px-2 py-1 bg-green-50 text-green-600 rounded-md text-xs font-bold" title="Hadir">H: {{ $item['Hadir'] }}</span>
                                    <span class="px-2 py-1 bg-blue-50 text-blue-600 rounded-md text-xs font-bold" title="Izin">I: {{ $item['Izin'] }}</span>
                                    <span class="px-2 py-1 bg-yellow-50 text-yellow-600 rounded-md text-xs font-bold" title="Sakit">S: {{ $item['Sakit'] }}</span>
                                    <span class="px-2 py-1 bg-red-50 text-red-600 rounded-md text-xs font-bold" title="Alpa">A: {{ $item['Alpha'] }}</span>
                                    <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded-md text-xs font-bold" title="Belum Absen">B: {{ $item['Belum_Absen'] ?? 0 }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button" onclick="toggleDetails('details-{{ $item['Class_ID'] }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50 hover:text-indigo-600 transition-colors">
                                    <span>Detail Kehadiran</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </td>
                        </tr>
                        <!-- Expandable Details Row -->
                        <tr id="details-{{ $item['Class_ID'] }}" class="hidden bg-slate-50/50">
                            <td colspan="5" class="px-6 py-4 border-b border-slate-200">
                                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                                        <h4 class="text-xs font-black text-slate-700 uppercase">Daftar Siswa</h4>
                                        <span class="text-xs font-bold text-slate-500">{{ count($item['Students']) }} Siswa Tampil</span>
                                    </div>
                                    <table class="w-full text-left text-sm">
                                        <thead>
                                            <tr class="bg-white border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase">
                                                <th class="px-4 py-2">No. Siswa</th>
                                                <th class="px-4 py-2">Nama Siswa</th>
                                                <th class="px-4 py-2">Status</th>
                                                <th class="px-4 py-2">Check-In</th>
                                                <th class="px-4 py-2">Check-Out</th>
                                                <th class="px-4 py-2">Notes</th>
                                                <th class="px-4 py-2 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @forelse($item['Students'] as $student)
                                                @php
                                                    $badgeColor = $student['Badge_Color'] ?? 'slate';
                                                    $displayStatus = $student['Display_Status'] ?? 'Belum Absen';
                                                @endphp
                                                <tr class="hover:bg-slate-50/80 transition-colors">
                                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $student['Student_Number'] ?? '-' }}</td>
                                                    <td class="px-4 py-3 font-bold text-slate-700">{{ $student['Student_Name'] }}</td>
                                                    <td class="px-4 py-3"><x-badge color="{{ $badgeColor }}">{{ $displayStatus }}</x-badge></td>
                                                    <td class="px-4 py-3 text-slate-600 text-xs">{{ $student['Check_In_Time'] ?? '-' }}</td>
                                                    <td class="px-4 py-3 text-slate-600 text-xs">{{ $student['Check_Out_Time'] ?? '-' }}</td>
                                                    <td class="px-4 py-3 text-slate-500 text-xs truncate max-w-[150px]">{{ $student['Notes'] ?? '-' }}</td>
                                                    <td class="px-4 py-3 text-right">
                                                        <div class="flex justify-end gap-1">
                                                            @if(!empty($student['Attendance_ID']))
                                                                <a href="{{ route('attendances.show', $student['Attendance_ID']) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-2 py-1 rounded">Detail</a>
                                                                <a href="{{ route('attendances.edit', $student['Attendance_ID']) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded">Edit</a>
                                                            @else
                                                                <span class="text-xs font-semibold text-slate-400">-</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="px-4 py-6 text-center text-slate-500 text-xs">
                                                        Tidak ada data siswa untuk filter ini.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center py-8">
                                    <svg class="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <p class="text-slate-500 font-medium">Tidak ada data kelas ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(is_object($paginatedClasses) && method_exists($paginatedClasses, 'links'))
            <div class="mt-4">
                {{ $paginatedClasses->links() }}
            </div>
        @endif
    </div>

    <!-- Mobile Card View (Hidden on Desktop) -->
    <div class="md:hidden space-y-4 mt-6">
        @forelse($paginatedClasses ?? [] as $item)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <span class="text-[10px] font-black text-indigo-500 uppercase tracking-wider">Kelas</span>
                            <h4 class="text-base font-bold text-slate-800 leading-tight">{{ $item['Class_Name'] }}</h4>
                        </div>
                        <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1 rounded-md">{{ $item['Date_Display'] }}</span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium mt-1">{{ $item['Total'] }} Siswa Terdaftar</p>
                </div>
                
                <div class="bg-slate-50 px-4 py-3 grid grid-cols-5 gap-2 text-center">
                    <div class="bg-white border border-green-100 rounded-lg p-2 shadow-sm">
                        <span class="block text-[10px] font-bold text-green-600 uppercase mb-0.5">Hadir</span>
                        <span class="block text-sm font-black text-slate-800">{{ $item['Hadir'] }}</span>
                    </div>
                    <div class="bg-white border border-blue-100 rounded-lg p-2 shadow-sm">
                        <span class="block text-[10px] font-bold text-blue-600 uppercase mb-0.5">Izin</span>
                        <span class="block text-sm font-black text-slate-800">{{ $item['Izin'] }}</span>
                    </div>
                    <div class="bg-white border border-yellow-100 rounded-lg p-2 shadow-sm">
                        <span class="block text-[10px] font-bold text-yellow-600 uppercase mb-0.5">Sakit</span>
                        <span class="block text-sm font-black text-slate-800">{{ $item['Sakit'] }}</span>
                    </div>
                    <div class="bg-white border border-red-100 rounded-lg p-2 shadow-sm">
                        <span class="block text-[10px] font-bold text-red-600 uppercase mb-0.5">Alpa</span>
                        <span class="block text-sm font-black text-slate-800">{{ $item['Alpha'] }}</span>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-lg p-2 shadow-sm">
                        <span class="block text-[10px] font-bold text-slate-600 uppercase mb-0.5">Belum</span>
                        <span class="block text-sm font-black text-slate-800">{{ $item['Belum_Absen'] ?? 0 }}</span>
                    </div>
                </div>
                
                <div class="p-4 bg-white">
                    <button type="button" onclick="toggleDetails('mobile-details-{{ $item['Class_ID'] }}')" class="w-full flex items-center justify-center gap-2 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-sm rounded-xl transition-colors">
                        <span>Lihat Siswa</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
                
                <!-- Expandable Mobile List -->
                <div id="mobile-details-{{ $item['Class_ID'] }}" class="hidden bg-slate-50 border-t border-slate-200 p-4">
                    <h5 class="text-xs font-black text-slate-500 uppercase mb-3">Daftar Siswa</h5>
                    <div class="space-y-3">
                        @forelse($item['Students'] as $student)
                            @php
                                $badgeColor = $student['Badge_Color'] ?? 'slate';
                                $displayStatus = $student['Display_Status'] ?? 'Belum Absen';
                            @endphp
                            <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h6 class="text-sm font-bold text-slate-800 leading-tight">{{ $student['Student_Name'] }}</h6>
                                        <p class="text-[10px] text-slate-500 mt-0.5">{{ $student['Student_Number'] ?? '-' }}</p>
                                    </div>
                                    <x-badge color="{{ $badgeColor }}">{{ $displayStatus }}</x-badge>
                                </div>
                                <div class="flex items-center gap-4 mt-3">
                                    <div>
                                        <p class="text-[9px] font-black text-slate-400 uppercase">Check-In</p>
                                        <p class="text-xs font-bold text-slate-700">{{ $student['Check_In_Time'] ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-slate-400 uppercase">Check-Out</p>
                                        <p class="text-xs font-bold text-slate-700">{{ $student['Check_Out_Time'] ?? '-' }}</p>
                                    </div>
                                    <div class="ml-auto">
                                        @if(!empty($student['Attendance_ID']))
                                            <a href="{{ route('attendances.show', $student['Attendance_ID']) }}" class="inline-flex items-center justify-center p-1.5 bg-indigo-50 text-indigo-600 rounded-lg border border-indigo-100">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                            </a>
                                        @else
                                            <span class="inline-flex items-center justify-center p-1.5 text-xs font-semibold text-slate-400">-</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-xs text-slate-500 py-4">Tidak ada data siswa.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
                <p class="text-sm text-slate-500 font-medium">Tidak ada data kelas ditemukan.</p>
            </div>
        @endforelse
        
        @if(is_object($paginatedClasses) && method_exists($paginatedClasses, 'links'))
            <div class="mt-4">
                {{ $paginatedClasses->links() }}
            </div>
        @endif
    </div>

</x-universal.index-layout>

<script>
    function toggleDetails(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.toggle('hidden');
        }
    }
</script>

@endsection
