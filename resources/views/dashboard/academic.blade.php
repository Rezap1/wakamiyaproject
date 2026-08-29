@extends('layouts.app')

@section('header', 'Dashboard Akademik')

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
<x-mobile-dashboard-hero user-role="ACADEMIC" />

<!-- DESKTOP DASHBOARD VIEW -->
<div class="hidden lg:block">
    <x-dashboard-header />
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

@endif

@endsection
