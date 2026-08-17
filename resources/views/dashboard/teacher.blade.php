@extends('layouts.app')

@section('header', 'Dashboard Guru')

@section('content')

@php
    $formattedKpi = [
        ['title' => "Kelas Hari Ini", 'value' => $kpi['today_classes'] ?? 0, 'icon' => 'view-boards', 'color' => 'blue', 'link' => route('schedules.index')],
        ['title' => 'Siswa Saya', 'value' => $kpi['my_students'] ?? 0, 'icon' => 'user-group', 'color' => 'emerald', 'link' => route('students.index')],
        ['title' => 'Kehadiran Tertunda', 'value' => $kpi['attendance_pending'] ?? 0, 'icon' => 'clock', 'color' => ($kpi['attendance_pending'] ?? 0) > 0 ? 'amber' : 'emerald', 'link' => route('attendances.index')],
        ['title' => 'Penilaian Tertunda', 'value' => $kpi['assessment_pending'] ?? 0, 'icon' => 'document-text', 'color' => ($kpi['assessment_pending'] ?? 0) > 0 ? 'rose' : 'emerald', 'link' => route('assessments.index')],
        ['title' => 'Nilai Tertunda', 'value' => $kpi['score_pending'] ?? 0, 'icon' => 'clipboard-check', 'color' => ($kpi['score_pending'] ?? 0) > 0 ? 'amber' : 'emerald', 'link' => route('scores.index')],
        ['title' => 'Gaji Bulan Ini', 'value' => \App\Services\Core\DashboardHelperService::getSalaryStatus(auth()->id()), 'icon' => 'cash', 'color' => \App\Services\Core\DashboardHelperService::getSalaryStatus(auth()->id()) == 'Diterima' ? 'emerald' : 'rose', 'link' => '#'],
    ];

    $quickActions = [
        ['title' => 'Mulai Kelas', 'url' => route('attendances.create'), 'icon' => 'play', 'color' => 'blue'],
        ['title' => 'Isi Kehadiran', 'url' => route('attendances.index'), 'icon' => 'clock', 'color' => 'emerald'],
        ['title' => 'Input Nilai', 'url' => route('scores.create'), 'icon' => 'clipboard-check', 'color' => 'indigo'],
    ];
@endphp

<!-- MOBILE-FIRST DASHBOARD HERO (100% MATCHING MOCKUP IMAGE ON MOBILE) -->
<x-mobile-dashboard-hero user-role="TEACHER" />

<!-- DESKTOP DASHBOARD VIEW -->
<div class="hidden lg:block">
    <x-dashboard.action-center 
        title="Dashboard Guru" 
        description="Pusat pengelolaan kelas, absensi, dan penilaian harian."
        :kpi="$formattedKpi"
        :quick-actions="$quickActions"
        :reminders="array_slice($reminders ?? [], 0, 5)"
        :recent-activities="$recentActivities ?? []"
    >
    </x-dashboard.action-center>
</div>

@endsection
