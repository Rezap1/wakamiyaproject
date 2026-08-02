@extends('layouts.app')

@section('header', 'Dashboard Marketing')

@section('content')

@php
    $formattedKpi = [
        ['title' => 'Mitra Perusahaan', 'value' => $kpi['companies'] ?? 0, 'icon' => 'office-building', 'color' => 'blue', 'link' => route('companies.index')],
        ['title' => 'Arsip Dokumen', 'value' => $kpi['documents'] ?? 0, 'icon' => 'folder-open', 'color' => 'emerald', 'link' => route('documents.index')],
    ];

    $quickActions = [
        ['title' => 'Tambah Perusahaan', 'url' => route('companies.create'), 'icon' => 'plus-circle', 'color' => 'blue'],
        ['title' => 'Unggah Dokumen', 'url' => route('documents.create'), 'icon' => 'upload', 'color' => 'emerald'],
    ];

    $reminders = [];
    $incomplete = count($notifications['incompleteDocuments'] ?? []);
    if($incomplete > 0) {
        $reminders[] = [
            'title' => 'Dokumen Tertunda',
            'description' => "Ada $incomplete dokumen dengan status PENDING yang perlu ditinjau.",
            'color' => 'amber',
            'action' => 'Lihat Dokumen',
            'url' => route('documents.index')
        ];
    }
@endphp

<x-dashboard.action-center 
    title="Dashboard Marketing" 
    description="Pusat pengelolaan kemitraan perusahaan dan arsip dokumen legal."
    :kpi="$formattedKpi"
    :quick-actions="$quickActions"
    :reminders="$reminders"
    :recent-activities="$recentActivities ?? []"
>
</x-dashboard.action-center>

@endsection
