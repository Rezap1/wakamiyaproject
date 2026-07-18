@extends('layouts.app')

@section('header', 'Detail Posisi')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-6 md:p-8 border-b border-gray-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white">
            <div class="flex items-center gap-4">
                <a href="{{ route('positions.index') }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900">{{ $position['Position_Name'] ?? '-' }}</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">ID: {{ $position['Position_ID'] ?? '-' }} | Kode: {{ $position['Position_Code'] ?? '-' }}</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                @if(($position['Is_Active'] ?? 'TRUE') === 'TRUE')
                    <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Nonaktif
                    </span>
                @endif
                <a href="{{ route('positions.edit', $position['Position_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Edit Posisi
                </a>
            </div>
        </div>

        <!-- Body -->
        <div class="p-6 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Kolom 1 -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Informasi Dasar</h3>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">ID Posisi</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Position_ID'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Kode Posisi</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Position_Code'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nama Posisi</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Position_Name'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Level Jabatan</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Position_Level'] ?? '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Struktur Organisasi</h3>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <dl>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Departemen Induk</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Department_Name'] ?? 'Tidak Diketahui' }}</dd>
                                    <dd class="mt-0.5 text-xs text-gray-500">ID: {{ $position['Department_ID'] ?? '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Kolom 2 -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Jejak Audit (Audit Trail)</h3>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Dibuat Pada</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Created_At'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Dibuat Oleh</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Created_By'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Terakhir Diperbarui</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Updated_At'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Diperbarui Oleh</dt>
                                    <dd class="mt-1 text-sm font-bold text-gray-900">{{ $position['Updated_By'] ?? '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Catatan (Notes)</h3>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 min-h-[100px]">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $position['Notes'] ?: 'Tidak ada catatan.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
