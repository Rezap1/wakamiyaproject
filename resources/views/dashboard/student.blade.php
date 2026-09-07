@extends('layouts.app')

@section('header', 'Dashboard Siswa')

@section('content')

<section class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 p-5">
    <h2 class="text-xs font-bold uppercase tracking-widest text-blue-800">Biaya Pendidikan</h2>
    <p class="mt-1 text-2xl font-black text-slate-900">Rp {{ number_format($kpi['biaya_pendidikan'] ?? 0, 0, ',', '.') }}</p>
    <p class="mt-2 text-sm text-slate-600">Informasi biaya resmi. Tagihan muncul setelah diterbitkan oleh bagian keuangan.</p>
</section>

@php
    $formattedKpi = [
        ['title' => "Kelas Hari Ini", 'value' => $kpi['today_class'] ?? 0, 'icon' => 'calendar', 'color' => 'indigo', 'link' => route('student.schedule')],
        ['title' => 'Total Tagihan', 'value' => 'Rp '.number_format($kpi['total_tagihan'] ?? 0, 0, ',', '.'), 'icon' => 'document-text', 'color' => 'blue', 'link' => route('student.billing.index')],
        ['title' => 'Tagihan Dibayar', 'value' => 'Rp '.number_format($kpi['tagihan_dibayar'] ?? 0, 0, ',', '.'), 'icon' => 'check-circle', 'color' => 'emerald', 'link' => route('student.billing.index')],
        ['title' => 'Sisa Tagihan', 'value' => 'Rp '.number_format($kpi['sisa_tagihan'] ?? 0, 0, ',', '.') . ' (' . ($kpi['status_pembayaran'] ?? 'BELUM LUNAS') . ')', 'icon' => 'cash', 'color' => ($kpi['sisa_tagihan'] ?? 0) > 0 ? 'rose' : 'emerald', 'link' => route('student.billing.index')],
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
