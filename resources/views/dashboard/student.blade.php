@extends('layouts.app')

@section('header', 'Dashboard Siswa')

@section('content')

@php
    $formattedKpi = [
        ['title' => "Kelas Hari Ini", 'value' => $kpi['today_class'] ?? 0, 'icon' => 'calendar', 'color' => 'indigo', 'link' => route('student.schedule')],
        ['title' => 'Tagihan Belum Lunas', 'value' => 'Rp '.number_format($kpi['outstanding_bills'] ?? 0, 0, ',', '.'), 'icon' => 'cash', 'color' => ($kpi['outstanding_bills'] ?? 0) > 0 ? 'rose' : 'emerald', 'link' => route('student.billing.index')],
        ['title' => 'Nilai Terakhir', 'value' => $kpi['latest_score'] ?? 0, 'icon' => 'academic-cap', 'color' => 'blue'],
        ['title' => 'Kehadiran', 'value' => $kpi['attendance_percentage'] ?? '0%', 'icon' => 'clock', 'color' => 'emerald'],
        ['title' => 'Sertifikat', 'value' => $kpi['certificate_status'] ?? 'Belum Ada Data', 'icon' => 'badge-check', 'color' => ($kpi['certificate_status'] ?? '') === 'Memenuhi Syarat' ? 'emerald' : 'amber'],
    ];

    $quickActions = [
        ['title' => 'Lihat Jadwal', 'url' => route('student.schedule'), 'icon' => 'calendar', 'color' => 'indigo'],
        ['title' => 'Unggah Pembayaran', 'url' => route('student.billing.index'), 'icon' => 'cash', 'color' => 'blue'],
        ['title' => 'Nilai Saya', 'url' => route('student.progress'), 'icon' => 'clipboard-check', 'color' => 'emerald'],
    ];
@endphp

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

@endsection
