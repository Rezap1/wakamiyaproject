@extends('layouts.app')
@section('header', 'Riwayat Presensi Saya')
@section('content')

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-slate-800">Riwayat Presensi Saya</h2>
        <p class="text-sm text-slate-500 mt-1">Pantau rekap kehadiran Anda selama di LPK WAKAMIYA.</p>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 text-center">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Hadir Bln Ini</p>
            <h3 class="text-xl font-extrabold text-green-600 mt-1">{{ $hadirBulanIni }}</h3>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 text-center">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Terlambat</p>
            <h3 class="text-xl font-extrabold text-yellow-600 mt-1">{{ $terlambatBulanIni }}</h3>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 text-center">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Total Presensi</p>
            <h3 class="text-xl font-extrabold text-slate-800 mt-1">{{ $totalPresensiSaya }}</h3>
        </div>
    </div>

    <!-- CARDS LIST (Mobile First) -->
    <div class="space-y-4">
        @forelse($paginated ?? [] as $item)
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
                
                $dateFormatted = !empty($item['Attendance_Date']) ? \Carbon\Carbon::parse($item['Attendance_Date'])->format('d M Y') : '-';
                $timeFormatted = !empty($item['Created_At']) ? \Carbon\Carbon::parse($item['Created_At'])->format('H:i') : '-';
            @endphp
            
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div class="flex justify-between items-center mb-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800">{{ $dateFormatted }}</p>
                        </div>
                    </div>
                    <x-badge color="{{ $badgeColor }}">{{ $displayStatus }}</x-badge>
                </div>
                
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-0.5">Waktu Pencatatan</p>
                        <p class="text-lg font-black text-slate-700 tracking-tight">{{ $timeFormatted }}</p>
                    </div>
                    <div class="text-right">
                        @if(!empty($item['Request_Status']))
                            @if($item['Request_Status'] === 'PENDING')
                                <p class="text-xs font-bold text-amber-600">Pengajuan {{ ucfirst(strtolower($item['Request_Type'])) }} — Menunggu Review</p>
                            @elseif($item['Request_Status'] === 'APPROVED')
                                <p class="text-xs font-bold text-emerald-600">Pengajuan {{ ucfirst(strtolower($item['Request_Type'])) }} — Disetujui</p>
                            @elseif($item['Request_Status'] === 'REJECTED')
                                <p class="text-xs font-bold text-rose-600">Pengajuan {{ ucfirst(strtolower($item['Request_Type'])) }} — Ditolak / ALPA</p>
                            @endif
                        @elseif(!empty($item['Attendance_ID']))
                            <p class="text-[10px] text-slate-400 font-medium">Ref: {{ substr($item['Attendance_ID'], 0, 12) }}...</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <x-universal.empty-state title="Riwayat Kosong" description="Anda belum memiliki catatan presensi." />
        @endforelse
    </div>

    @if(is_object($paginated) && method_exists($paginated, 'links'))
        <div class="mt-6">
            <x-universal.pagination :paginator="$paginated" />
        </div>
    @endif
</div>

@endsection
