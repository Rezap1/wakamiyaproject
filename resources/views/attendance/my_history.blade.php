@extends('layouts.app')
@section('header', 'Riwayat Absensi')
@section('content')

<div class="mx-auto max-w-5xl min-w-0 pb-2 sm:pb-4">
    <div class="mb-5 flex min-w-0 items-start justify-between gap-3 sm:mb-6">
        <div class="min-w-0">
            <h2 class="text-xl font-extrabold text-slate-900 sm:text-2xl">Riwayat Absensi</h2>
            <p class="mt-1 text-sm leading-relaxed text-slate-500">Menampilkan maksimal 5 catatan absensi terbaru Anda.</p>
        </div>
        <span class="shrink-0 rounded-full bg-sky-100 px-3 py-1.5 text-xs font-bold text-sky-700">5 Terbaru</span>
    </div>

    <div class="mb-5 grid grid-cols-3 gap-2 sm:mb-6 sm:gap-3">
        <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 text-center shadow-sm sm:p-4">
            <p class="text-[9px] font-bold uppercase leading-tight tracking-wide text-slate-500 sm:text-[10px]">Hadir Bulan Ini</p>
            <p class="mt-1 text-xl font-extrabold text-emerald-600">{{ $hadirBulanIni }}</p>
        </div>
        <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 text-center shadow-sm sm:p-4">
            <p class="text-[9px] font-bold uppercase leading-tight tracking-wide text-slate-500 sm:text-[10px]">Terlambat</p>
            <p class="mt-1 text-xl font-extrabold text-amber-600">{{ $terlambatBulanIni }}</p>
        </div>
        <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 text-center shadow-sm sm:p-4">
            <p class="text-[9px] font-bold uppercase leading-tight tracking-wide text-slate-500 sm:text-[10px]">Total Absensi</p>
            <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $totalPresensiSaya }}</p>
        </div>
    </div>

    <div class="grid min-w-0 gap-3 md:grid-cols-2">
        @forelse($latestAttendances as $item)
            @php
                $canonicalDate = trim((string) ($item['Attendance_Date'] ?? ''));
                $attendanceDate = $canonicalDate !== '' ? $canonicalDate : ($item['Date'] ?? null);
                $dateLabel = \App\Helpers\DateHelper::format($attendanceDate, 'l, j F Y');
                $checkIn = trim((string) ($item['Check_In_Time'] ?? ''));
                $checkOut = trim((string) ($item['Check_Out_Time'] ?? ''));
                $checkInLabel = $checkIn !== ''
                    ? \App\Helpers\DateHelper::format('2000-01-01 ' . $checkIn, 'H:i')
                    : '-';
                $checkOutLabel = $checkOut !== ''
                    ? \App\Helpers\DateHelper::format('2000-01-01 ' . $checkOut, 'H:i')
                    : null;
                $status = $item['Status'] ?? '';
                $statusLabel = \App\Helpers\AttendanceStatusHelper::label($status);
                $badgeColor = \App\Helpers\AttendanceStatusHelper::badgeColor($status);
            @endphp

            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex min-w-0 items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="break-words text-sm font-extrabold leading-snug text-slate-900 sm:text-base">{{ $dateLabel }}</p>
                            <p class="mt-1 text-xs font-medium text-slate-500">Jam masuk <span class="font-bold text-slate-700">{{ $checkInLabel }}</span></p>
                            @if($checkOutLabel)
                                <p class="mt-0.5 text-xs font-medium text-slate-500">Jam keluar <span class="font-bold text-slate-700">{{ $checkOutLabel }}</span></p>
                            @endif
                        </div>
                    </div>
                    <x-badge color="{{ $badgeColor }}" class="shrink-0">{{ $statusLabel }}</x-badge>
                </div>

                @if(!empty($item['Request_Status']))
                    <div class="mt-3 border-t border-slate-100 pt-3 text-xs font-semibold text-slate-500">
                        Pengajuan {{ \App\Support\Presentation\IndonesianPresentation::status($item['Request_Status']) }}
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-medium text-slate-500 md:col-span-2">
                Belum ada riwayat absensi.
            </div>
        @endforelse
    </div>
</div>

@endsection
