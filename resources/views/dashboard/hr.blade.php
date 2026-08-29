@extends('layouts.app')

@section('header', 'Dashboard HR')

@section('content')

@if(isset($api_error) && $api_error)
    <div class="bg-red-50 border border-red-200 rounded-2xl p-6 m-4 md:m-8 lg:m-12 flex flex-col items-center justify-center text-center space-y-4">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h2 class="text-xl font-bold text-red-800">Data belum dapat dimuat</h2>
        <p class="text-red-600 max-w-md">{{ $error_message ?? 'Terjadi kesalahan komunikasi dengan database utama (Google Sheets). Silakan coba beberapa saat lagi.' }}</p>
        <button onclick="window.location.reload()" class="px-4 py-2 mt-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors shadow-sm">
            Coba Muat Ulang
        </button>
    </div>
@else

@php
    $formattedKpi = [
        ['title' => 'Pegawai Aktif', 'value' => $kpi['active_employees'] ?? 0, 'icon' => 'user-group', 'color' => 'blue', 'link' => route('employees.index')],
        ['title' => 'Draft Penggajian', 'value' => $kpi['payroll_draft'] ?? 0, 'icon' => 'document-text', 'color' => ($kpi['payroll_draft'] ?? 0) > 0 ? 'amber' : 'emerald', 'link' => route('payrolls.index')],
        ['title' => 'Cuti Hari Ini', 'value' => $kpi['on_leave'] ?? 0, 'icon' => 'calendar', 'color' => 'indigo'],
        ['title' => 'Kontrak Berakhir', 'value' => $kpi['contract_expired'] ?? 0, 'icon' => 'exclamation-circle', 'color' => ($kpi['contract_expired'] ?? 0) > 0 ? 'rose' : 'emerald'],
        ['title' => 'Departemen', 'value' => $kpi['total_departments'] ?? 0, 'icon' => 'office-building', 'color' => 'slate'],
    ];

    $quickActions = [
        ['title' => 'Tambah Pegawai', 'url' => route('employees.create'), 'icon' => 'user-add', 'color' => 'blue'],
        ['title' => 'Buat Penggajian', 'url' => route('payrolls.create'), 'icon' => 'cash', 'color' => 'emerald'],
        ['title' => 'Log Kehadiran', 'url' => route('attendances.index'), 'icon' => 'clock', 'color' => 'indigo'],
    ];
@endphp

<!-- MOBILE-FIRST DASHBOARD HERO (100% MATCHING MOCKUP IMAGE ON MOBILE) -->
<x-mobile-dashboard-hero user-role="HR" />

<!-- DESKTOP DASHBOARD VIEW -->
<div class="hidden lg:block">
    <x-dashboard-header />
    <x-dashboard.action-center 
        title="Dashboard HR" 
        description="Pusat pengelolaan SDM, absensi, dan payroll."
        :kpi="$formattedKpi"
        :quick-actions="$quickActions"
        :reminders="array_slice($reminders ?? [], 0, 5)"
        :recent-activities="$recentActivities ?? []"
    >
    </x-dashboard.action-center>
</div>

@endif

@endsection
