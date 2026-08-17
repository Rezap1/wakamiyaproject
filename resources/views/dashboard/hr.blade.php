@extends('layouts.app')

@section('header', 'Dashboard HR')

@section('content')

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

@endsection
