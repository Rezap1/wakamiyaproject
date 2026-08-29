@extends('layouts.app')
@section('header', 'HR Dynamic QR Attendance Engine')
@section('content')

<div class="max-w-6xl mx-auto space-y-6">
    <x-universal.index-layout 
        title="Dynamic QR Code Attendance Engine" 
        description="Kelola sesi presensi kehadiran pegawai secara real-time dengan rotasi token QR dinamis terenkripsi."
        :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'HR' => '#', 'Dynamic QR Presensi' => '#']"
    >
        <x-slot:headerActions>
            <a href="{{ route('hr.attendance.qr.scanner') }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors flex items-center gap-1.5">
                📷 Buka Scanner HP Pegawai
            </a>
        </x-slot:headerActions>

        <x-slot:toolbar>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs mb-6">
                <h3 class="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3 mb-4">Buka Sesi Presensi QR Baru</h3>
                <form action="{{ route('hr.attendance.qr.session.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Judul / Nama Sesi Presensi <span class="text-rose-500">*</span></label>
                        <input type="text" name="Title" class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 p-2.5" placeholder="Contoh: Presensi Pagi Kantor Kantor Utama..." value="Presensi Pagi Pegawai - {{ date('d M Y') }}" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Jam Mulai (Start Time) <span class="text-rose-500">*</span></label>
                        <input type="time" name="Start_Time" class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 p-2.5" value="08:00" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Jam Selesai (End Time) <span class="text-rose-500">*</span></label>
                        <input type="time" name="End_Time" class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 p-2.5" value="17:00" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Grace Period Terlambat</label>
                        <div class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-700 p-2.5 font-bold">
                            30 Menit
                        </div>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                        <input type="text" name="Notes" class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 p-2.5" placeholder="Catatan instruksi sesi...">
                    </div>
                    <div>
                        <button type="submit" class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                            🚀 Buka Layar QR Proyektor
                        </button>
                    </div>
                </form>
            </div>
        </x-slot:toolbar>

        <x-universal.data-table :empty="count($sessions) === 0" empty-title="Belum Ada Sesi Presensi QR" empty-description="Buat sesi presensi baru menggunakan form di atas.">
            <x-slot:header>
                <th class="px-6 py-4">ID Sesi & Judul</th>
                <th class="px-6 py-4 text-center">Tanggal & Jam</th>
                <th class="px-6 py-4 text-center">Toleransi (Grace Period)</th>
                <th class="px-6 py-4 text-center">Status Sesi</th>
                <th class="px-6 py-4 text-right">Aksi</th>
            </x-slot:header>

            @foreach($sessions as $s)
                @php
                    $isClosed = ($s['Status'] ?? '') === 'CLOSED';
                    $sessionDate = $s['Date'] ?? date('Y-m-d');
                    $startAt = \Carbon\Carbon::parse($sessionDate . ' ' . ($s['Start_Time'] ?? '08:00'));
                    $endAt = \Carbon\Carbon::parse($sessionDate . ' ' . ($s['End_Time'] ?? '17:00'));
                    $now = now();
                    $isScheduled = !$isClosed && $now->lt($startAt);
                    $isExpired = !$isClosed && $now->gt($endAt);
                    $isOpen = !$isClosed && !$isScheduled && !$isExpired;
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-mono font-bold text-slate-800 text-xs">{{ $s['Session_ID'] ?? '' }}</div>
                        <div class="font-bold text-slate-800 text-sm mt-0.5">{{ $s['Title'] ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="font-bold text-slate-800 text-xs">{{ !empty($s['Date']) ? \Carbon\Carbon::parse($s['Date'])->format('d M Y') : '-' }}</div>
                        <div class="text-[11px] text-slate-500 font-mono">{{ $s['Start_Time'] ?? '08:00' }} - {{ $s['End_Time'] ?? '17:00' }} WIB</div>
                        @if($isScheduled)
                            <div class="text-[10px] text-sky-600 font-black mt-1">QR aktif saat jam mulai.</div>
                        @elseif($isExpired)
                            <div class="text-[10px] text-amber-600 font-black mt-1">QR sudah melewati jam selesai.</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center font-bold text-xs text-slate-700">
                        {{ $s['Grace_Period'] ?? 30 }} Menit
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($isOpen)
                            <span class="px-3 py-1 text-xs font-black rounded-lg bg-emerald-100 text-emerald-800 inline-flex items-center gap-1 uppercase">
                                🟢 AKTIF (OPEN)
                            </span>
                        @else
                            <span class="px-3 py-1 text-xs font-bold rounded-lg bg-slate-200 text-slate-700 inline-flex items-center gap-1 uppercase">
                                🔒 DITUTUP (CLOSED)
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right space-x-1.5">
                        @if($isOpen || $isScheduled)
                            <a href="{{ route('hr.attendance.qr.display', $s['Session_ID']) }}" target="_blank" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg font-bold text-xs shadow-xs transition-colors inline-block">
                                📺 Tampilkan QR Proyektor
                            </a>
                            <form action="{{ route('hr.attendance.qr.close', $s['Session_ID']) }}" method="POST" class="inline" onsubmit="return confirm('Tutup sesi presensi ini?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold text-xs transition-colors">
                                    Tutup Sesi
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-slate-400 font-medium">Sesi Selesai</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-universal.data-table>

    </x-universal.index-layout>
</div>
@endsection
