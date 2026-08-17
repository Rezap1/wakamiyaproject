@extends('layouts.app')

@section('header', 'Dashboard Akademik')

@section('content')

@php
    $formattedKpi = [
        ['title' => 'Program Aktif', 'value' => $kpi['programs'] ?? 0, 'icon' => 'collection', 'color' => 'indigo', 'link' => route('programs.index')],
        ['title' => 'Batch Aktif', 'value' => $kpi['batches'] ?? 0, 'icon' => 'view-boards', 'color' => 'purple', 'link' => route('batches.index')],
        ['title' => 'Kelas Aktif', 'value' => $kpi['classes'] ?? 0, 'icon' => 'view-boards', 'color' => 'blue', 'link' => route('classes.index')],
        ['title' => 'Siswa Aktif', 'value' => $kpi['students'] ?? 0, 'icon' => 'academic-cap', 'color' => 'emerald', 'link' => route('students.index')],
        ['title' => 'Tingkat Kehadiran', 'value' => $kpi['attendance_rate'] ?? '0%', 'icon' => 'clock', 'color' => ($kpi['attendance_rate'] ?? '0%') !== '0%' ? 'emerald' : 'amber'],
        ['title' => 'Nilai Tertunda', 'value' => $kpi['score_pending'] ?? 0, 'icon' => 'document-text', 'color' => 'rose', 'link' => route('scores.index')],
    ];

    $quickActions = [
        ['title' => 'Buat Kelas', 'url' => route('classes.create'), 'icon' => 'view-boards', 'color' => 'blue'],
        ['title' => 'Jadwal', 'url' => route('schedules.index'), 'icon' => 'calendar', 'color' => 'indigo'],
        ['title' => 'Penilaian', 'url' => route('assessments.index'), 'icon' => 'clipboard-check', 'color' => 'emerald'],
        ['title' => 'Nilai', 'url' => route('scores.index'), 'icon' => 'document-text', 'color' => 'rose'],
    ];
@endphp

<!-- MOBILE DASHBOARD HERO (100% UNIFIED WMS DESIGN SYSTEM ON MOBILE) -->
<x-mobile-dashboard-hero user-role="ADMINISTRATOR" />

<!-- DESKTOP DASHBOARD VIEW -->
<div class="hidden lg:block">
    <x-dashboard.action-center 
        title="Dashboard Akademik" 
        description="Pusat kontrol kurikulum, jadwal, dan nilai akademik."
        :kpi="$formattedKpi"
        :quick-actions="$quickActions"
        :reminders="array_slice($reminders ?? [], 0, 5)"
        :recent-activities="$recentActivities ?? []"
    >
    </x-dashboard.action-center>
</div>

@endsection
