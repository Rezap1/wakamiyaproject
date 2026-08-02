@extends('layouts.app')
@section('header', 'Statistik Audit')
@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <x-page-header title="Statistik Audit" description="Gambaran umum aktivitas sistem dan distribusi kejadian." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Jejak Audit' => route('audit.index'), 'Statistik' => '#']" />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-wider">Total Kejadian</p>
                    <p class="text-2xl font-black text-slate-800">{{ $stats['total'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-wider">Kejadian Hari Ini</p>
                    <p class="text-2xl font-black text-slate-800">{{ $stats['today'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Modul Paling Aktif</h3>
            <div class="space-y-4">
                @foreach($stats['top_modules'] as $module => $count)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-bold text-slate-700">{{ $module ?: 'Sistem' }}</span>
                        <span class="font-bold text-slate-500">{{ $count }} kejadian</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ min(($count / max(1, $stats['total'])) * 100, 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Pengguna Paling Aktif</h3>
            <div class="space-y-4">
                @foreach($stats['top_users'] as $user => $count)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-bold text-slate-700">{{ $user ?: 'Sistem' }}</span>
                        <span class="font-bold text-slate-500">{{ $count }} kejadian</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ min(($count / max(1, $stats['total'])) * 100, 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection



