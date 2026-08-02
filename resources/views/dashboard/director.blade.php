@extends('layouts.app')

@section('header', 'Dashboard Direktur')

@section('content')

@php
    $formattedKpi = [
        ['title' => 'Menunggu Persetujuan', 'value' => $summary['pending_applications'] ?? 0, 'icon' => 'clock', 'color' => 'amber'],
        ['title' => 'Pendapatan', 'value' => 'N/A', 'icon' => 'trending-up', 'color' => 'emerald'],
        ['title' => 'Pengeluaran', 'value' => 'N/A', 'icon' => 'trending-down', 'color' => 'rose'],
        ['title' => 'Siswa Aktif', 'value' => $summary['active_students'] ?? 0, 'icon' => 'academic-cap', 'color' => 'blue'],
    ];

    $quickActions = [
        ['title' => 'Kotak Persetujuan', 'url' => route('approvals.index'), 'icon' => 'inbox-in', 'color' => 'blue'],
    ];

    $reminders = [];
    foreach(($notifications['pendingApplications'] ?? []) as $app) {
        $reminders[] = [
            'title' => 'Butuh Persetujuan',
            'description' => 'Aplikasi ' . ($app['Application_ID'] ?? 'Unknown') . ' menunggu persetujuan Anda.',
            'action_url' => route('approvals.index')
        ];
    }
@endphp

<x-dashboard.action-center 
    title="Dashboard Direktur" 
    description="Pusat pemantauan eksekutif dan persetujuan (approval) WMS."
    :kpi="$formattedKpi"
    :quick-actions="$quickActions"
    :reminders="array_slice($reminders, 0, 5)"
    :recent-activities="$recentActivities ?? []"
>
</x-dashboard.action-center>

@endsection
