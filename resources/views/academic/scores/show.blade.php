@extends('layouts.app')
@section('header', 'Detail Nilai')
@section('content')

@php
    $rawScore = $score['Score_Value'] ?? 92;
    $result = \App\Helpers\GradeHelper::calculate($rawScore);
    $statusText = $result['pass'] ? 'PASS' : 'FAIL';
    $statusColor = $result['pass'] ? 'green' : 'red';
    $tab = request('tab', 'informasi');
@endphp

<x-universal.detail-layout 
    title="{{ $score['Student_ID'] ?? 'Siswa Tidak Diketahui' }}" 
    subtitle="ID Nilai: {{ $score['Score_ID'] ?? '-' }}"
    status="{{ $statusText }}"
    statusColor="{{ $statusColor }}"
    avatarInitials="{{ substr($score['Student_ID'] ?? 'S', 0, 1) }}"
    activeTab="{{ $tab }}"
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Nilai' => route('scores.index'), 'Detail' => '#']"
>
    
    <x-slot:headerActions>
        <x-universal.action-button action="edit" url="{{ route('scores.edit', $score['Score_ID'] ?? 1) }}" />
    </x-slot:headerActions>

    <x-slot:sidebarContent>
        <div class="text-center py-4 border-b border-slate-100 mb-4">
            <p class="text-[11px] font-bold text-slate-400 uppercase">Nilai</p>
            <p class="text-5xl font-black text-slate-800 mt-2">{{ $rawScore }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Grade</p>
            <p class="text-lg font-black text-slate-800 mt-0.5">{{ $result['grade'] }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Status</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">
                <x-badge color="{{ $statusColor }}">{{ $statusText }}</x-badge>
            </p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Tanggal Ujian</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $score['Exam_Date'] ?? '-' }}</p>
        </div>
    </x-slot:sidebarContent>

    @if($tab === 'informasi')
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Assessment</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Nama Penilaian</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $score['Assessment_ID'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Evaluator / Pengajar</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $score['Teacher_ID'] ?? '-' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-bold text-slate-400 uppercase">Catatan Evaluator</p>
                        <p class="text-sm font-medium text-slate-800 mt-1 bg-slate-50 p-4 rounded-xl border border-slate-200 italic">"{{ $score['Notes'] ?? 'Tidak ada catatan khusus.' }}"</p>
                    </div>
                </div>
            </div>
        </div>
    @elseif($tab === 'audit')
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">System Logs</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Record ID</p>
                    <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $score['Score_ID'] ?? '-' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $score['Created_At'] ?? '-' }}</p>
                </div>
            </div>
        </div>
    @else
        <x-universal.empty-state title="Belum Ada Data" description="Data untuk tab ini belum tersedia atau sedang dikembangkan." />
    @endif

</x-universal.detail-layout>

@endsection



