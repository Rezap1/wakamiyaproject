@extends('layouts.app')

@section('header', 'Detail Profil Guru')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden relative">
        <div class="h-32 bg-gradient-to-r from-indigo-600 to-indigo-800"></div>
        <div class="px-6 sm:px-10 pb-8">
            <div class="relative flex justify-between items-start -mt-12">
                <div class="flex flex-col md:flex-row gap-6 md:items-end">
                    <div class="h-28 w-28 rounded-2xl bg-white p-1.5 shadow-lg border border-gray-100">
                        <div class="h-full w-full rounded-xl bg-gradient-to-br from-indigo-50 to-indigo-100 text-indigo-500 flex items-center justify-center font-extrabold text-4xl shadow-inner border border-white">
                            {{ substr($teacher['Full_Name'] ?? 'U', 0, 1) }}
                        </div>
                    </div>
                    <div class="pb-2">
                        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">{{ $teacher['Full_Name'] }}</h1>
                        <div class="flex flex-wrap items-center gap-3 mt-2 text-sm">
                            <span class="font-mono bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-md font-bold">{{ $teacher['Teacher_Code'] }}</span>
                            <span class="text-gray-500 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                {{ $teacher['Specialization'] }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col items-end gap-3 mt-14 md:mt-16">
                    @if(($teacher['Teaching_Status'] ?? '') === 'Aktif Mengajar')
                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold bg-blue-50 text-blue-700 border border-blue-200 shadow-sm">
                            Aktif Mengajar
                        </span>
                    @else
                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold bg-orange-50 text-orange-700 border border-orange-200 shadow-sm">
                            {{ $teacher['Teaching_Status'] }}
                        </span>
                    @endif
                    
                    <a href="{{ route('teachers.edit', $teacher['Teacher_ID']) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Edit Data
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Data Mengajar -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Informasi Pengajaran
                    </h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Mata Pelajaran (Spesialisasi)</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $teacher['Specialization'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Mulai Mengajar</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $teacher['Hire_Date'] ? \Carbon\Carbon::parse($teacher['Hire_Date'])->format('d F Y') : '-' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Organisasi / Departemen</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 flex items-center gap-2">
                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-md">{{ $teacher['Department_Name'] }}</span>
                                <span class="text-gray-400">/</span>
                                <span class="text-gray-700">{{ $teacher['Position_Name'] }}</span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Tautan Pegawai -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center">
                        <svg class="w-5 h-5 mr-2 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Informasi Pegawai (Read-only)
                    </h3>
                    <a href="{{ route('employees.show', $teacher['Employee_ID']) }}" class="text-xs font-bold text-primary-600 hover:text-primary-800 transition-colors">Lihat Detail Pegawai</a>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor Induk Pegawai (NIK)</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900 font-mono">{{ $teacher['Employee_Number'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Kelamin</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $teacher['Gender'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor Telepon</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $teacher['Phone_Number'] ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Email</dt>
                            <dd class="mt-1 text-sm font-bold text-blue-600">{{ $teacher['Email'] ?: '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Info Status -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Status Sistem
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Akses Sistem (Is_Active)</p>
                        <div class="mt-1">
                            @if(($teacher['Is_Active'] ?? 'TRUE') === 'TRUE')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">Aktif</span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">Nonaktif</span>
                            @endif
                        </div>
                    </div>
                    <div class="pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Teacher ID</p>
                        <p class="mt-1 text-sm font-mono text-gray-900">{{ $teacher['Teacher_ID'] }}</p>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            @if($teacher['Notes'])
            <div class="bg-amber-50 rounded-2xl shadow-sm border border-amber-100 overflow-hidden">
                <div class="p-5">
                    <h3 class="text-xs font-bold text-amber-800 uppercase tracking-wider mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Catatan
                    </h3>
                    <p class="text-sm text-amber-900 whitespace-pre-wrap">{{ $teacher['Notes'] }}</p>
                </div>
            </div>
            @endif

            <!-- Audit Log Summary -->
            <div class="bg-gray-50 rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-200 bg-gray-100 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-gray-600 uppercase tracking-wider">Audit Trail</h3>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="p-5">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Dibuat Pada</p>
                            <p class="text-sm font-bold text-gray-900">{{ $teacher['Created_At'] ? \Carbon\Carbon::parse($teacher['Created_At'])->format('d M Y, H:i:s') : '-' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Oleh: <span class="font-medium text-gray-700">{{ $teacher['Created_By'] ?? 'Sistem' }}</span></p>
                        </div>
                        <div class="pt-3 border-t border-gray-200">
                            <p class="text-xs text-gray-500 font-medium">Terakhir Diperbarui</p>
                            <p class="text-sm font-bold text-gray-900">{{ $teacher['Updated_At'] ? \Carbon\Carbon::parse($teacher['Updated_At'])->format('d M Y, H:i:s') : '-' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Oleh: <span class="font-medium text-gray-700">{{ $teacher['Updated_By'] ?? 'Sistem' }}</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
