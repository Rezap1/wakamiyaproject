@extends('layouts.app')
@section('header', 'Detail Kehadiran')
@section('content')

@php
    $status = strtolower($attendance['Status'] ?? 'present');
    $badgeColor = match($status) {
        'present' => 'green',
        'late' => 'yellow',
        'sick', 'leave', 'permission' => 'blue',
        'absent' => 'red',
        default => 'slate',
    };
    $statusText = $attendance['Status'] ?? 'Present';
    
    $tab = request('tab', 'informasi');
@endphp

<x-universal.detail-layout 
    title="{{ $attendance['Student_ID'] ?? 'Pengguna Tidak Diketahui' }}" 
    subtitle="ID Kehadiran: {{ $attendance['Attendance_ID'] ?? 'ATD-10294' }} | {{ isset($attendance['Student_ID']) ? 'Siswa' : 'Karyawan' }}"
    status="{{ $statusText }}"
    statusColor="{{ $badgeColor }}"
    avatarInitials="{{ substr($attendance['Student_ID'] ?? 'U', 0, 1) }}"
    activeTab="{{ $tab }}"
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Kehadiran' => route('attendances.index'), 'Detail' => '#']"
>
    
    <x-slot:headerActions>
        <x-universal.action-button action="edit" url="{{ route('attendances.edit', $attendance['Attendance_ID'] ?? 1) }}" />
    </x-slot:headerActions>

    <x-slot:sidebarContent>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Tanggal</p>
            <p class="text-sm font-bold text-slate-800 mt-0.5">{{ $attendance['Attendance_Date'] ?? date('d M Y') }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Schedule ID</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $attendance['Schedule_ID'] ?? '-' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Status</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">
                <x-badge color="{{ $badgeColor }}">{{ $statusText }}</x-badge>
            </p>
        </div>
    </x-slot:sidebarContent>

    @if($tab === 'informasi')
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Waktu Kehadiran</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Check In (Time In)</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">08:00 AM</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Check Out (Time Out)</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">05:00 PM</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-bold text-slate-400 uppercase">Catatan</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $attendance['Notes'] ?? 'Tidak ada catatan.' }}</p>
                    </div>
                </div>
            </div>
            
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Akademik / Pekerjaan</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Departemen / Program</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">Software Engineering / Full Stack</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Batch / Kelas</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">Batch 5 - Class A</p>
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
                    <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $attendance['Attendance_ID'] ?? '-' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $attendance['Created_At'] ?? '-' }}</p>
                </div>
            </div>
        </div>
    @else
        <x-universal.empty-state title="Belum Ada Data" description="Data untuk tab ini belum tersedia atau sedang dikembangkan." />
    @endif

</x-universal.detail-layout>

@endsection



