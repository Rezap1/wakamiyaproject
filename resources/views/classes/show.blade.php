@extends('layouts.app')

@section('header', 'Detail Kelas (Rombel)')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Action Bar -->
    <div class="flex justify-between items-center">
        <a href="{{ route('classes.index') }}" class="inline-flex items-center text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Kelas
        </a>
        <div class="flex gap-2">
            <a href="{{ route('classes.edit', $class['Class_ID']) }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit Data
            </a>
        </div>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-6 border-b border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gradient-to-r from-gray-50 to-white">
            <div class="flex items-center gap-4">
                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-indigo-100 to-indigo-200 flex items-center justify-center text-indigo-700 shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900">{{ $class['Class_Name'] }}</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono">
                            {{ $class['Class_Code'] }}
                        </span>
                        <span class="text-sm font-medium text-gray-500 flex items-center">
                            <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            {{ $class['Program_Name'] }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-end gap-2">
                @if(($class['Is_Active'] ?? 'TRUE') === 'TRUE')
                    <span class="inline-flex items-center px-4 py-1.5 rounded-xl text-xs font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-2"></span>
                        Sistem Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-1.5 rounded-xl text-xs font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-2"></span>
                        Sistem Nonaktif
                    </span>
                @endif
                
                <span class="inline-flex items-center px-4 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 shadow-sm">
                    {{ $class['Class_Status'] }}
                </span>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                
                <!-- Batch Info -->
                <div class="sm:col-span-1 bg-gray-50 p-4 rounded-xl border border-gray-100 flex items-start gap-4">
                    <div class="p-3 bg-white rounded-lg shadow-sm text-gray-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Angkatan (Batch)</dt>
                        <dd class="text-sm font-extrabold text-gray-900">{{ $class['Batch_Name'] }}</dd>
                    </div>
                </div>
                
                <!-- Homeroom Teacher -->
                <div class="sm:col-span-1 bg-gray-50 p-4 rounded-xl border border-gray-100 flex items-start gap-4">
                    <div class="p-3 bg-white rounded-lg shadow-sm text-gray-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Wali Kelas</dt>
                        <dd class="text-sm font-extrabold text-gray-900">{{ $class['Teacher_Name'] }}</dd>
                    </div>
                </div>

                <!-- Capacity Metric -->
                <div class="sm:col-span-1 bg-blue-50 p-4 rounded-xl border border-blue-100 flex flex-col justify-center items-center text-center">
                    <dt class="text-xs font-bold text-blue-500 uppercase tracking-wider mb-1">Daya Tampung Siswa</dt>
                    <dd class="mt-1 flex items-baseline justify-center gap-1">
                        <span class="text-3xl font-extrabold {{ ($class['Current_Student'] == $class['Capacity']) ? 'text-red-600' : 'text-blue-900' }}">{{ $class['Current_Student'] }}</span>
                        <span class="text-sm font-bold text-blue-400">/ {{ $class['Capacity'] }}</span>
                    </dd>
                    @if($class['Current_Student'] == $class['Capacity'])
                        <span class="mt-1 text-[10px] font-bold text-red-500 uppercase">KELAS PENUH</span>
                    @endif
                </div>

                <div class="sm:col-span-3">
                    <dt class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Deskripsi / Ruang Kelas</dt>
                    <dd class="text-sm font-medium text-gray-900 bg-gray-50 p-4 rounded-xl border border-gray-100 whitespace-pre-wrap leading-relaxed">{{ $class['Description'] ?: 'Tidak ada deskripsi yang ditambahkan.' }}</dd>
                </div>

                @if($class['Notes'])
                <div class="sm:col-span-3">
                    <dt class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Catatan Internal</dt>
                    <dd class="text-sm font-medium text-amber-900 bg-amber-50 p-4 rounded-xl border border-amber-100 whitespace-pre-wrap">{{ $class['Notes'] }}</dd>
                </div>
                @endif
            </dl>
        </div>

        <!-- Audit Trail -->
        <div class="bg-gray-50 px-6 py-5 border-t border-gray-100">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Audit Trail (System Logs)
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium">Record ID</p>
                    <p class="text-sm font-bold text-gray-900 font-mono mt-0.5">{{ $class['Class_ID'] }}</p>
                </div>
                <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium">Data Dibuat</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $class['Created_At'] ? \Carbon\Carbon::parse($class['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs text-gray-400 mt-1">Oleh: {{ $class['Created_By'] ?? 'Sistem' }}</p>
                </div>
                <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium">Terakhir Diperbarui</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $class['Updated_At'] ? \Carbon\Carbon::parse($class['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs text-gray-400 mt-1">Oleh: {{ $class['Updated_By'] ?? 'Sistem' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
