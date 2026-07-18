@extends('layouts.app')

@section('header', 'Profil Siswa')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Action Bar -->
    <div class="flex justify-between items-center">
        <a href="{{ route('students.index') }}" class="inline-flex items-center text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Siswa
        </a>
        <div class="flex gap-2">
            <a href="{{ route('students.edit', $student['Student_ID']) }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit Profil
            </a>
        </div>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        
        <!-- Header Banner -->
        <div class="relative h-32 bg-gradient-to-r from-primary-600 to-indigo-700">
            <div class="absolute inset-0 bg-white/10 opacity-30 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCI+PGNpcmNsZSBjeD0iMTAiIGN5PSIxMCIgcj0iMiIgZmlsbD0iI2ZmZiIvPjwvc3ZnPg==')]"></div>
        </div>

        <!-- Profile Info Overlapping Header -->
        <div class="px-6 md:px-8 pb-8">
            <div class="relative flex flex-col md:flex-row justify-between items-start md:items-end -mt-12 mb-6">
                <div class="flex flex-col md:flex-row items-start md:items-end gap-5">
                    <div class="h-28 w-28 rounded-2xl {{ $student['Gender'] == 'Laki-laki' ? 'bg-blue-100 text-blue-600' : 'bg-pink-100 text-pink-600' }} border-4 border-white shadow-lg flex items-center justify-center font-extrabold text-5xl z-10 relative">
                        {{ substr($student['Full_Name'], 0, 1) }}
                        <div class="absolute -bottom-2 -right-2 h-8 w-8 rounded-full border-4 border-white flex items-center justify-center
                            {{ ($student['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'bg-green-500' : 'bg-red-500' }}" title="{{ ($student['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Sistem Aktif' : 'Nonaktif' }}">
                            @if(($student['Is_Active'] ?? 'TRUE') === 'TRUE')
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            @else
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0 pt-2">
                        <h1 class="text-3xl font-extrabold text-gray-900 leading-tight uppercase">{{ $student['Full_Name'] }}</h1>
                        <div class="flex items-center gap-3 mt-1.5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-800 border border-gray-200 font-mono">
                                NIS: {{ $student['Student_Number'] }}
                            </span>
                            <span class="text-sm font-medium text-gray-500 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                {{ $student['Gender'] }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 md:mt-0 flex flex-col items-start md:items-end gap-2 w-full md:w-auto">
                    <span class="inline-flex items-center px-4 py-1.5 rounded-xl text-sm font-bold bg-primary-50 text-primary-700 border border-primary-200 shadow-sm w-full md:w-auto justify-center">
                        {{ $student['Program_Name'] }}
                    </span>
                    <div class="flex gap-2 w-full justify-start md:justify-end">
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            {{ $student['Enrollment_Status'] }}
                        </span>
                        @if($student['Graduation_Status'] === 'Lulus')
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                            🎓 Alumni
                        </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Left Column: Akademik & Kontak -->
                <div class="md:col-span-1 space-y-6">
                    <!-- Academic Panel -->
                    <div class="bg-gray-50 rounded-xl border border-gray-100 p-5">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-200 pb-2">Penempatan Akademik</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <p class="text-[11px] font-bold text-gray-500 uppercase">Kelas Belajar</p>
                                <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $student['Class_Name'] }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-gray-500 uppercase">Angkatan (Batch)</p>
                                <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $student['Batch_Name'] }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-gray-500 uppercase">Tgl Pendaftaran</p>
                                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ \Carbon\Carbon::parse($student['Registration_Date'])->translatedFormat('d F Y') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Panel -->
                    <div class="bg-gray-50 rounded-xl border border-gray-100 p-5">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-200 pb-2">Kontak Aktif</h3>
                        
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                <div>
                                    <p class="text-[11px] font-bold text-gray-500 uppercase">No. Telepon / WA</p>
                                    <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $student['Phone_Number'] ?: '-' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                <div>
                                    <p class="text-[11px] font-bold text-gray-500 uppercase">Email</p>
                                    <p class="text-sm font-medium text-gray-900 mt-0.5 truncate max-w-[200px]" title="{{ $student['Email'] }}">{{ $student['Email'] ?: '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Personal Data -->
                <div class="md:col-span-2 space-y-6">
                    <div class="border border-gray-100 rounded-xl p-6">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                            Data Kependudukan & Latar Belakang
                        </h3>
                        
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                            <div>
                                <dt class="text-[11px] font-bold text-gray-500 uppercase">Nomor Induk Kependudukan (KTP)</dt>
                                <dd class="text-sm font-bold text-gray-900 mt-1 font-mono">{{ $student['National_ID'] ?: 'Belum diisi' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold text-gray-500 uppercase">Tempat, Tanggal Lahir</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1">
                                    {{ $student['Birth_Place'] ?: '-' }}, 
                                    {{ $student['Birth_Date'] ? \Carbon\Carbon::parse($student['Birth_Date'])->translatedFormat('d F Y') : '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold text-gray-500 uppercase">Pendidikan Terakhir</dt>
                                <dd class="text-sm font-bold text-gray-900 mt-1">{{ $student['Education'] }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-[11px] font-bold text-gray-500 uppercase mb-1">Alamat Tinggal</dt>
                                <dd class="text-sm font-medium text-gray-900 bg-gray-50 p-3 rounded-lg border border-gray-100">{{ $student['Address'] ?: 'Tidak ada data alamat.' }}</dd>
                            </div>
                        </dl>
                    </div>

                    @if($student['Notes'])
                    <div class="border border-amber-100 bg-amber-50 rounded-xl p-5">
                        <h3 class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-2 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Catatan Khusus / Internal
                        </h3>
                        <p class="text-sm text-amber-900 whitespace-pre-wrap">{{ $student['Notes'] }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Audit Trail -->
        <div class="bg-gray-50 px-6 md:px-8 py-5 border-t border-gray-100">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Audit Trail (System Logs)
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium">Record ID</p>
                    <p class="text-sm font-bold text-gray-900 font-mono mt-0.5">{{ $student['Student_ID'] }}</p>
                </div>
                <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium">Data Dibuat</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $student['Created_At'] ? \Carbon\Carbon::parse($student['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs text-gray-400 mt-1">Oleh: {{ $student['Created_By'] ?? 'Sistem' }}</p>
                </div>
                <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium">Terakhir Diperbarui</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $student['Updated_At'] ? \Carbon\Carbon::parse($student['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs text-gray-400 mt-1">Oleh: {{ $student['Updated_By'] ?? 'Sistem' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
