@extends('layouts.app')

@section('header', 'Detail Modul')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
        <div class="p-6 md:p-8 border-b border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('modules.index') }}" class="p-2 rounded-xl text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/80 dark:bg-slate-800 dark:hover:bg-slate-800 dark:bg-slate-800 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white">Detail Modul</h2>
                    <p class="text-sm font-medium text-gray-500 dark:text-slate-400 mt-1">Informasi lengkap data modul aplikasi.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('modules.edit', $module['Module_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-200 dark:border-slate-700 rounded-xl shadow-sm text-sm font-bold text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-800/80 dark:bg-slate-800 dark:hover:bg-slate-800 dark:bg-slate-800 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-gray-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Data
                </a>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <div class="flex items-center mb-8 pb-8 border-b border-gray-100 dark:border-slate-800">
                <div class="h-20 w-20 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 text-primary-700 flex items-center justify-center font-bold text-3xl shadow-inner border-4 border-white ring-4 ring-primary-50">
                    {{ substr($module['Module_Name'] ?? 'M', 0, 1) }}
                </div>
                <div class="ml-6">
                    <h3 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $module['Module_Name'] ?? '-' }}</h3>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 border border-gray-200 dark:border-slate-700">
                            {{ $module['Module_ID'] ?? '-' }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            {{ $module['Module_Code'] ?? '-' }}
                        </span>
                        @if(($module['Is_Active'] ?? 'TRUE') === 'TRUE')
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span>
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span>
                                Nonaktif
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                <!-- Data Modul -->
                <div class="bg-gray-50 dark:bg-slate-800 rounded-2xl p-6 border border-gray-100 dark:border-slate-800">
                    <h4 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 dark:border-slate-700 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Informasi Modul
                    </h4>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-bold text-gray-500 dark:text-slate-400 uppercase">Grup Modul</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $module['Module_Group'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-gray-500 dark:text-slate-400 uppercase">Urutan (Order)</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-800 dark:text-slate-200 font-bold text-sm">
                                    {{ $module['Module_Order'] ?? '0' }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Sistem -->
                <div class="bg-gray-50 dark:bg-slate-800 rounded-2xl p-6 border border-gray-100 dark:border-slate-800">
                    <h4 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 dark:border-slate-700 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Informasi Sistem
                    </h4>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-bold text-gray-500 dark:text-slate-400 uppercase">Dibuat Pada</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ !empty($module['Created_At']) ? \Carbon\Carbon::parse($module['Created_At'])->format('d F Y, H:i:s') : '-' }}</dd>
                            <dd class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Oleh: {{ $module['Created_By'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-gray-500 dark:text-slate-400 uppercase">Terakhir Diperbarui</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ !empty($module['Updated_At']) ? \Carbon\Carbon::parse($module['Updated_At'])->format('d F Y, H:i:s') : '-' }}</dd>
                            <dd class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Oleh: {{ $module['Updated_By'] ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Catatan -->
                <div class="md:col-span-2 bg-gray-50 dark:bg-slate-800 rounded-2xl p-6 border border-gray-100 dark:border-slate-800">
                    <h4 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider mb-4 pb-2 border-b border-gray-200 dark:border-slate-700 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Catatan
                    </h4>
                    <div class="text-sm text-gray-700 dark:text-slate-300 whitespace-pre-line bg-white dark:bg-slate-900 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm min-h-[100px]">{{ $module['Notes'] ?: 'Tidak ada catatan.' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



