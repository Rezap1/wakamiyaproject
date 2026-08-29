@extends('layouts.app')

@section('header', 'Dashboard Marketing')

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

<!-- MOBILE DASHBOARD HERO (100% UNIFIED WMS DESIGN SYSTEM ON MOBILE) -->
<x-mobile-dashboard-hero user-role="MARKETING" />

<!-- DESKTOP DASHBOARD VIEW -->
<div class="hidden lg:block">
    <x-dashboard-header />
    <x-dashboard.action-center 
        title="Dashboard Marketing" 
        description="Pusat pengelolaan kemitraan perusahaan dan arsip dokumen legal."
        :kpi="$formattedKpi"
        :quick-actions="$quickActions"
        :reminders="$reminders"
        :recent-activities="$recentActivities ?? []"
    >
    </x-dashboard.action-center>
</div>

@endif

@endsection
