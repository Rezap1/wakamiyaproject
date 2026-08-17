@extends('layouts.app')
@section('header', 'Detail Rapor & Evaluasi Nilai')
@section('content')

@php
    $rawScore = $score['Score_Value'] ?? ($score['Score'] ?? 0);
    $result = \App\Helpers\GradeHelper::calculate($rawScore);
    $statusText = $result['pass'] ? 'PASS (LULUS)' : 'FAIL (TIDAK LULUS)';
    $statusColor = $result['pass'] ? 'green' : 'red';
    
    $category = strtoupper($score['Assessment_Category'] ?? 'GENERAL');
    $details = $score['Parsed_Details'] ?? [];
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="{{ $score['Student_Name'] ?? ($score['Student_ID'] ?? 'Siswa') }}" 
        description="Assessment: {{ $score['Assessment_Title'] ?? ($score['Assessment_ID'] ?? '-') }} | ID Nilai: {{ $score['Score_ID'] ?? '-' }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard'), 'Akademik' => '#', 'Nilai' => route('scores.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="edit" url="{{ route('scores.edit', $score['Score_ID']) }}" />
            <x-universal.action-button action="delete" url="{{ route('scores.destroy', $score['Score_ID']) }}" />
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <!-- SCORE HEADER CARD -->
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-md flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl {{ $category === 'SPORTS' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : ($category === 'LANGUAGE' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30') }} flex items-center justify-center font-black text-2xl shrink-0">
                            {{ $result['grade'] }}
                        </div>
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Kategori Assessment</span>
                            <h2 class="text-xl font-bold mt-0.5">
                                @if($category === 'SPORTS') 🏀 Evaluasi Olahraga (Sports)
                                @elseif($category === 'LANGUAGE') 🗣️ Evaluasi Bahasa (Language)
                                @else 📚 Akademik Umum (General)
                                @endif
                            </h2>
                            <p class="text-xs text-slate-400 mt-1">Siswa ID: {{ $score['Student_ID'] }} &bull; Tanggal: {{ !empty($score['Created_At']) ? \Carbon\Carbon::parse($score['Created_At'])->format('d M Y') : '-' }}</p>
                        </div>
                    </div>
                    <div class="text-center md:text-right">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Nilai Akhir Komposit</p>
                        <p class="text-4xl font-black mt-1">{{ $rawScore }} <span class="text-sm font-normal text-slate-400">/ 100</span></p>
                    </div>
                </div>

                <!-- HUMAN READABLE EVALUATION DETAILS (NO RAW JSON) -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">📊</span>
                        Rincian Metrik Evaluasi (Evaluation Details)
                    </h3>

                    <!-- SPORTS DETAILS DISPLAY -->
                    @if($category === 'SPORTS')
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
                                <p class="text-xs font-bold text-emerald-800 uppercase">Running (Lari)</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">
                                    {{ $details['running_distance'] ?? 0 }} km <span class="text-xs text-slate-500">/ {{ $details['running_time'] ?? 0 }} menit</span>
                                </p>
                            </div>
                            <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
                                <p class="text-xs font-bold text-emerald-800 uppercase">Push Up</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['push_up'] ?? 0 }} <span class="text-xs text-slate-500">kali</span></p>
                            </div>
                            <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
                                <p class="text-xs font-bold text-emerald-800 uppercase">Sit Up</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['sit_up'] ?? 0 }} <span class="text-xs text-slate-500">kali</span></p>
                            </div>
                            <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
                                <p class="text-xs font-bold text-emerald-800 uppercase">Status Fisik</p>
                                <p class="text-sm font-bold text-emerald-700 mt-1.5">TEREVALUASI</p>
                            </div>
                        </div>

                    <!-- LANGUAGE DETAILS DISPLAY -->
                    @elseif($category === 'LANGUAGE')
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100">
                                <p class="text-xs font-bold text-purple-800 uppercase">Speaking (Berbicara)</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['speaking'] ?? 0 }} <span class="text-xs text-slate-500">/ 100</span></p>
                            </div>
                            <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100">
                                <p class="text-xs font-bold text-purple-800 uppercase">Writing (Menulis)</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['writing'] ?? 0 }} <span class="text-xs text-slate-500">/ 100</span></p>
                            </div>
                            <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100">
                                <p class="text-xs font-bold text-purple-800 uppercase">Listening (Mendengar)</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['listening'] ?? 0 }} <span class="text-xs text-slate-500">/ 100</span></p>
                            </div>
                            <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100">
                                <p class="text-xs font-bold text-purple-800 uppercase">Reading (Membaca)</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['reading'] ?? 0 }} <span class="text-xs text-slate-500">/ 100</span></p>
                            </div>
                            <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100">
                                <p class="text-xs font-bold text-purple-800 uppercase">Ethics (Etika)</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['ethics'] ?? 0 }} <span class="text-xs text-slate-500">/ 100</span></p>
                            </div>
                            <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100">
                                <p class="text-xs font-bold text-purple-800 uppercase">Learning Motivation</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['motivation'] ?? 0 }} <span class="text-xs text-slate-500">/ 100</span></p>
                            </div>
                            <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100 sm:col-span-2">
                                <p class="text-xs font-bold text-purple-800 uppercase">Attendance (Kehadiran)</p>
                                <p class="text-lg font-bold text-slate-800 mt-1">{{ $details['attendance'] ?? 0 }}% <span class="text-xs text-slate-500">Tingkat Kehadiran</span></p>
                            </div>
                        </div>

                    <!-- GENERAL ACADEMIC DISPLAY -->
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                                <p class="text-xs font-bold text-slate-400 uppercase">Mata Pelajaran / Subject ID</p>
                                <p class="text-sm font-bold text-slate-800 mt-1">{{ $details['subject_id'] ?? ($score['Subject_ID'] ?? 'Umum') }}</p>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                                <p class="text-xs font-bold text-slate-400 uppercase">Grade Standar</p>
                                <p class="text-sm font-bold text-slate-800 mt-1">{{ $result['grade'] }} ({{ $statusText }})</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- NOTES / EVALUATOR FEEDBACK -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Catatan & Umpan Balik Evaluator</h3>
                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 text-sm text-slate-800 italic leading-relaxed">
                        "{{ $score['Remarks'] ?? ($score['Notes'] ?? ($details['notes'] ?? 'Tidak ada catatan evaluasi khusus.')) }}"
                    </div>
                </div>
            </div>
        </x-slot:information>

        <!-- SYSTEM METADATA AUDIT -->
        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">System Metadata & Audit Trail</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Rekaman Nilai (Primary Key)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $score['Score_ID'] ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Penilaian / Assessment</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $score['Assessment_ID'] ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Waktu Pencatatan</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($score['Created_At']) ? \Carbon\Carbon::parse($score['Created_At'])->format('d M Y, H:i:s') : '-' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Waktu Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($score['Updated_At']) ? \Carbon\Carbon::parse($score['Updated_At'])->format('d M Y, H:i:s') : '-' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
