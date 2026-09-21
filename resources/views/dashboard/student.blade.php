@extends('layouts.app')

@section('header', 'Dashboard Siswa')

@section('content')

<!-- Pengumuman aktif: sumber data yang sama untuk desktop dan mobile -->
<section aria-labelledby="student-announcements-heading" class="mb-6 rounded-2xl border border-amber-200 bg-white p-4 shadow-sm sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-amber-700">Informasi terbaru</p>
            <h2 id="student-announcements-heading" class="mt-1 text-xl font-black text-slate-900">Pengumuman</h2>
        </div>
        @if(Route::has('student.portal.announcements'))
            <a href="{{ route('student.portal.announcements') }}" class="text-sm font-bold text-emerald-700 hover:underline">Lihat Semua Pengumuman</a>
        @endif
    </div>
    @if(($announcements ?? collect())->isEmpty())
        <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Belum ada pengumuman aktif untuk Anda.</p>
    @else
        <div class="mt-4 grid gap-3 lg:grid-cols-3">
            @foreach($announcements as $announcement)
                @php
                    $priority = $announcement['Priority_Label'] ?? 'Informasi';
                    $tone = $priority === 'Mendesak' ? 'border-rose-300 bg-rose-50' : ($priority === 'Penting' ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50');
                    $badge = $priority === 'Mendesak' ? 'bg-rose-600 text-white' : ($priority === 'Penting' ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-700');
                @endphp
                <article class="min-w-0 rounded-xl border p-4 {{ $tone }}">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-extrabold {{ $badge }}">{{ $priority }}</span>
                    <h3 class="mt-3 break-words text-base font-black leading-snug text-slate-900">{{ $announcement['Title'] ?? 'Pengumuman' }}</h3>
                    <p class="mt-2 max-h-20 overflow-hidden whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $announcement['Message'] ?? $announcement['Content'] ?? '' }}</p>
                    <p class="mt-3 text-xs font-semibold text-slate-600">Berakhir: {{ $announcement['Expires_At_Label'] ?? '-' }}</p>
                    @if(Route::has('student.portal.announcements.show'))
                        <a href="{{ route('student.portal.announcements.show', $announcement['Announcement_ID']) }}" class="mt-3 inline-flex text-xs font-bold text-emerald-700 hover:underline">Lihat Detail</a>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>

<section class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 p-4 shadow-sm sm:p-5" aria-labelledby="education-payment-summary-heading">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="education-payment-summary-heading" class="text-xs font-bold uppercase tracking-widest text-blue-800">Ringkasan Biaya Pendidikan</h2>
            <p class="mt-1 text-sm text-slate-600">Berdasarkan pembayaran Biaya Pendidikan yang sudah diverifikasi.</p>
        </div>
        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ ($kpi['status_biaya_pendidikan'] ?? '') === 'LUNAS' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
            {{ $kpi['status_biaya_pendidikan'] ?? 'BELUM BAYAR' }}
        </span>
    </div>
    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="min-w-0 rounded-xl bg-white p-3 shadow-sm">
            <dt class="text-[11px] font-bold text-slate-500">Biaya Pendidikan</dt>
            <dd class="mt-1 whitespace-nowrap text-lg font-black text-slate-900">Rp {{ number_format($kpi['biaya_pendidikan'] ?? 0, 0, ',', '.') }}</dd>
        </div>
        <div class="min-w-0 rounded-xl bg-emerald-50 p-3">
            <dt class="text-[11px] font-bold text-emerald-700">Sudah Dibayar</dt>
            <dd class="mt-1 whitespace-nowrap text-lg font-black text-emerald-900">Rp {{ number_format($kpi['sudah_dibayar'] ?? 0, 0, ',', '.') }}</dd>
        </div>
        <div class="min-w-0 rounded-xl bg-amber-50 p-3">
            <dt class="text-[11px] font-bold text-amber-700">Sisa Biaya Pendidikan</dt>
            <dd class="mt-1 whitespace-nowrap text-lg font-black text-amber-900">Rp {{ number_format($kpi['sisa_biaya_pendidikan'] ?? 0, 0, ',', '.') }}</dd>
        </div>
    </dl>
</section>

@php
    $formattedKpi = [
        ['title' => "Kelas Hari Ini", 'value' => $kpi['today_class'] ?? 0, 'icon' => 'calendar', 'color' => 'indigo', 'link' => route('student.schedule')],
        ['title' => 'Tagihan dari Master', 'value' => 'Rp '.number_format($kpi['tagihan_master'] ?? 0, 0, ',', '.'), 'icon' => 'document-text', 'color' => 'blue', 'link' => route('student.billing.index')],
        ['title' => 'Sisa Biaya Pendidikan', 'value' => 'Rp '.number_format($kpi['sisa_biaya_pendidikan'] ?? 0, 0, ',', '.') . ' (' . ($kpi['status_biaya_pendidikan'] ?? 'BELUM BAYAR') . ')', 'icon' => 'cash', 'color' => ($kpi['sisa_biaya_pendidikan'] ?? 0) > 0 ? 'rose' : 'emerald', 'link' => route('student.billing.index')],
        ['title' => 'Pengajuan Presensi', 'value' => ($kpi['request_pending'] ?? 0) . ' Pending / ' . ($kpi['request_approved'] ?? 0) . ' Setuju', 'icon' => 'document-text', 'color' => ($kpi['request_pending'] ?? 0) > 0 ? 'amber' : 'emerald', 'link' => route('student.attendance.requests.index')],
    ];

    $quickActions = [
        ['title' => 'Lihat Jadwal', 'url' => route('student.schedule'), 'icon' => 'calendar', 'color' => 'indigo'],
        ['title' => 'Unggah Pembayaran', 'url' => route('student.billing.index'), 'icon' => 'cash', 'color' => 'blue'],
        ['title' => 'Pengajuan Sakit/Izin', 'url' => route('student.attendance.requests.index'), 'icon' => 'document-text', 'color' => 'amber'],
        ['title' => 'Tugas Saya', 'url' => route('student.portal.assignments'), 'icon' => 'clipboard-list', 'color' => 'indigo'],
    ];
@endphp

<!-- MOBILE HERO (VISIBLE ON MOBILE ONLY) -->
<div class="block md:hidden">
    <x-mobile-dashboard-hero user-role="STUDENT" :kpi-data="$kpi ?? []" />
</div>

<!-- MAIN UNIFIED DASHBOARD VIEW (HIDDEN ON MOBILE, VISIBLE ON DESKTOP) -->
<div class="hidden md:block w-full">
    <x-dashboard-header />
    <x-dashboard.action-center 
        title="Dashboard Siswa" 
        description="Pusat informasi akademik dan administrasi siswa LPK."
        :kpi="$formattedKpi"
        :quick-actions="$quickActions"
        :reminders="array_slice($reminders ?? [], 0, 5)"
        :recent-activities="$recentActivities ?? []"
    >
        <!-- Language Progress -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 mt-6">
            <h4 class="text-lg font-extrabold text-slate-800 mb-5">Kemajuan Bahasa</h4>
            <div class="w-full bg-slate-100 rounded-full h-2.5 mb-2">
                <div class="bg-blue-500 h-2.5 rounded-full transition-all duration-500" style="width: {{ $langProgress ?? 0 }}%"></div>
            </div>
            <p class="text-xs text-slate-500 text-right">{{ $langProgress ?? 0 }}%</p>
        </div>
    </x-dashboard.action-center>
</div>

@endsection
