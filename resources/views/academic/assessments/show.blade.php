@extends('layouts.app')
@section('header', 'Detail Penilaian')
@section('content')

@php
    $status = $assessment['Status'] ?? 'Published';
    $statusColor = match($status) {
        'Published' => 'blue',
        'Closed' => 'green',
        'Archived' => 'slate',
        default => 'yellow',
    };
    $cat = $assessment['Category'] ?? 'Mid Test';
    $tab = request('tab', 'informasi');
@endphp

<x-universal.detail-layout 
    title="{{ $assessment['Assessment_Name'] ?? 'Penilaian' }}" 
    subtitle="Kode: {{ $assessment['Assessment_Code'] ?? '-' }} | {{ $cat }}"
    status="{{ $status }}"
    statusColor="{{ $statusColor }}"
    avatarInitials="{{ substr($assessment['Assessment_Name'] ?? 'A', 0, 1) }}"
    activeTab="{{ $tab }}"
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Penilaian' => route('assessments.index'), 'Detail' => '#']"
>
    
    <x-slot:headerActions>
        @if(!empty($assessment['Assessment_ID']))
            <x-universal.action-button action="edit" url="{{ route('assessments.edit', $assessment['Assessment_ID']) }}" />
        @endif
    </x-slot:headerActions>

    <x-slot:sidebarContent>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Kategori</p>
            <p class="text-sm font-bold text-slate-800 mt-0.5">{{ $cat }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Mata Pelajaran</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $assessment['Subject_Name'] ?? 'Mata pelajaran tidak ditemukan' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Pengajar</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $assessment['Teacher_Name'] ?? 'Pengajar tidak ditemukan' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Tanggal Ujian</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $assessment['Assessment_Date'] ?? '-' }}</p>
        </div>
    </x-slot:sidebarContent>

    @if($tab === 'informasi')
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Assessment</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Kelas / Batch</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $assessment['Class_Name'] ?? 'Kelas tidak ditemukan' }} / {{ $assessment['Batch_Name'] ?? $assessment['Batch_ID'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Program</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $assessment['Program_ID'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Max Score</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $assessment['Max_Score'] ?? 100 }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-bold text-slate-400 uppercase">Deskripsi / Guideline</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $assessment['Description'] ?? 'Tidak ada instruksi khusus.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    @elseif($tab === 'audit')
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">System Logs</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Kode Penilaian</p>
                    <p class="text-sm font-bold text-slate-800 mt-1">{{ $assessment['Assessment_Code'] ?? '-' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $assessment['Created_At'] ?? '-' }}</p>
                </div>
            </div>
        </div>
    @else
        <x-universal.empty-state title="Belum Ada Data" description="Data untuk tab ini belum tersedia atau sedang dikembangkan." />
    @endif

</x-universal.detail-layout>

@endsection



