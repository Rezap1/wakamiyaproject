@extends('layouts.app')

@section('header', 'Panel Pengembang')

@section('content')
<div class="space-y-6">
    <!-- Header Summary -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 p-6 md:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white">Sistem Internal & Metrik</h2>
            <p class="text-sm font-medium text-gray-500 dark:text-slate-400 mt-1">Pantau status arsitektur WMS dan kemajuan setiap modul.</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right">
                <p class="text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mb-1">Total Progres</p>
                <p class="text-3xl font-extrabold text-primary-600">{{ $metrics['progress'] }}</p>
            </div>
            <div class="h-14 w-14 rounded-full bg-primary-50 flex items-center justify-center text-primary-600 border border-primary-100">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Metrik Sistem -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                        Status Sistem
                    </h3>
                </div>
                <div class="p-6">
                    <ul class="space-y-4">
                        <li class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-slate-400">Versi WMS</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white bg-gray-100 dark:bg-slate-800 px-3 py-1 rounded-lg">{{ $metrics['version'] }}</span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-slate-400">Status Cache</span>
                            <span class="text-sm font-bold text-green-700 bg-green-50 border border-green-200 px-3 py-1 rounded-lg flex items-center">
                                <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span> {{ $metrics['cache_status'] }}
                            </span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-slate-400">Google Sheets DB</span>
                            <span class="text-sm font-bold text-green-700 bg-green-50 border border-green-200 px-3 py-1 rounded-lg flex items-center">
                                <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span> {{ $metrics['google_sheets_status'] }}
                            </span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-slate-400">Total Log Audit</span>
                            <span class="text-sm font-bold text-blue-700 bg-blue-50 border border-blue-200 px-3 py-1 rounded-lg">{{ $metrics['audit_log_count'] }}</span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-600 dark:text-slate-400">Bug Diketahui</span>
                            <span class="text-sm font-bold text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 px-3 py-1 rounded-lg">{{ $metrics['bug_count'] }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl shadow-lg border border-gray-700 overflow-hidden text-white p-6 relative">
                <div class="absolute -right-10 -top-10 opacity-10">
                    <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                </div>
                <h3 class="text-lg font-bold mb-2">Catatan Pengembangan</h3>
                <p class="text-sm text-gray-300 mb-4 leading-relaxed">
                    Arsitektur menggunakan integrasi Google Sheets eksklusif (Tanpa SQL). Pastikan Anda tidak mengubah struktur kolom (Header) secara manual di Spreadsheet agar aplikasi tidak *crash*.
                </p>
                <div class="flex gap-4">
                    <div>
                        <p class="text-2xl font-black">{{ $metrics['total_apis'] }}</p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 font-medium uppercase tracking-widest">APIs</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black">{{ $metrics['total_tables'] }}</p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 font-medium uppercase tracking-widest">Tabel</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black">{{ $metrics['total_modules'] }}</p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 font-medium uppercase tracking-widest">Modul</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Roadmap & Module Status -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden h-full flex flex-col">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Roadmap Modul Aplikasi
                    </h3>
                </div>
                <div class="p-0 flex-1 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-white dark:bg-slate-900">
                            <tr>
                                <th scope="col" class="px-8 py-4 text-left text-xs font-extrabold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Nama Modul</th>
                                <th scope="col" class="px-8 py-4 text-right text-xs font-extrabold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status Implementasi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-100 dark:divide-slate-700/50">
                            @foreach($modules as $module)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/80 dark:bg-slate-800 dark:hover:bg-slate-800 dark:bg-slate-800 transition-colors">
                                <td class="px-8 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white font-mono tracking-tight">{{ $module['name'] }}</div>
                                </td>
                                <td class="px-8 py-4 whitespace-nowrap text-right">
                                    @if($module['status'] === 'LOCK')
                                        <span class="inline-flex items-center text-sm font-bold text-green-700">
                                            <svg class="w-5 h-5 mr-1.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            LOCK
                                        </span>
                                    @elseif($module['status'] === 'IN_PROGRESS')
                                        <span class="inline-flex items-center text-sm font-bold text-blue-600">
                                            <svg class="w-5 h-5 mr-1.5 text-blue-500 animate-spin-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation: spin 3s linear infinite;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                            IN PROGRESS
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-sm font-bold text-gray-400 dark:text-slate-500">
                                            <svg class="w-5 h-5 mr-1.5 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            PENDING
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800 text-xs text-center font-medium text-gray-500 dark:text-slate-400">
                    Modul yang sudah berstatus <strong>LOCK</strong> berarti selesai secara arsitektur, UI, validasi, dan API Google Sheets.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



