@extends('layouts.app')
@section('header', 'Detail Profil Alumni')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('alumni.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
                    &larr; Kembali ke Daftar Alumni
                </a>
            </div>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight mt-1">
                Profil Alumni: {{ $student['Full_Name'] }}
            </h2>
            <p class="text-sm text-slate-500 font-mono">ID: {{ $student['Student_ID'] }} | NIS: {{ $student['Student_Number'] ?? '-' }}</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-sm flex items-center gap-1">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Status: {{ $student['Graduation_Status'] ?? 'LULUS' }}
            </span>
        </div>
    </div>
        <!-- Overview Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Main Info Card -->
            <div class="md:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
                <div>
                    <h3 class="text-base font-bold text-slate-800 border-b border-slate-200 pb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Informasi Pribadi & Kontak
                    </h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Nama Lengkap</dt>
                            <dd class="font-bold text-slate-800 mt-0.5">{{ $student['Full_Name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Email</dt>
                            <dd class="font-semibold text-slate-700 mt-0.5">{{ $student['Email'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Nomor Telepon</dt>
                            <dd class="font-semibold text-slate-700 mt-0.5">{{ $student['Phone_Number'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Jenis Kelamin</dt>
                            <dd class="font-medium text-slate-700 mt-0.5">{{ $student['Gender'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Tempat, Tanggal Lahir</dt>
                            <dd class="font-medium text-slate-700 mt-0.5">
                                {{ $student['Birth_Place'] ?? '-' }}, {{ $student['Birth_Date'] ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">NIK (KTP)</dt>
                            <dd class="font-mono text-slate-700 mt-0.5">{{ $student['National_ID'] ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-xs font-medium text-slate-400">Alamat</dt>
                            <dd class="font-medium text-slate-700 mt-0.5">{{ $student['Address'] ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h3 class="text-base font-bold text-slate-800 border-b border-slate-200 pb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        </svg>
                        Riwayat Program & Pendidikan
                    </h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Program Pelatihan</dt>
                            <dd class="font-bold text-indigo-700 mt-0.5">{{ $student['Program_Name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Angkatan (Batch)</dt>
                            <dd class="font-semibold text-slate-700 mt-0.5">{{ $student['Batch_Name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Kelas Terakhir</dt>
                            <dd class="font-semibold text-slate-700 mt-0.5">{{ $student['Class_Name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Pendidikan Terakhir</dt>
                            <dd class="font-medium text-slate-700 mt-0.5">{{ $student['Education'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Tanggal Masuk</dt>
                            <dd class="font-medium text-slate-700 mt-0.5">{{ $student['Registration_Date'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Status Pendaftaran</dt>
                            <dd class="font-bold text-emerald-600 mt-0.5">{{ $student['Enrollment_Status'] ?? 'Alumni' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Academic & Document Summary Side Card -->
            <div class="space-y-6">
                <!-- Academic Transcripts / Certificates Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <h4 class="font-bold text-slate-800 text-sm border-b border-slate-200 pb-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Dokumen Kelulusan
                    </h4>
                    @if(!empty($documents))
                        <div class="space-y-2">
                            @foreach($documents as $doc)
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">{{ $doc['Document_Type'] ?? 'Dokumen' }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $doc['Created_At'] ?? '' }}</div>
                                    </div>
                                    @if(!empty($doc['File_URL']))
                                        <a href="{{ asset($doc['File_URL']) }}" target="_blank" class="text-xs font-semibold text-indigo-600 hover:underline">
                                            Unduh
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-xs text-slate-400 text-center py-4 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                            Sertifikat & Transkrip telah diproses otomatis.
                        </div>
                    @endif
                </div>

                <!-- Academic Score Summary Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <h4 class="font-bold text-slate-800 text-sm border-b border-slate-200 pb-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Ringkasan Nilai Akademik
                    </h4>
                    @if(!empty($scores))
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($scores as $sc)
                                <div class="flex items-center justify-between p-2 text-xs bg-slate-50 rounded border border-slate-100">
                                    <span class="font-medium text-slate-700">{{ $sc['Subject_Name'] ?? $sc['Subject_ID'] ?? 'Mapel' }}</span>
                                    <span class="font-bold text-indigo-600">{{ $sc['Score'] ?? $sc['Grade'] ?? '-' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-xs text-slate-400 text-center py-4 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                            Riwayat nilai akademik tersimpan aman.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
