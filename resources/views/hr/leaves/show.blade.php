@extends('layouts.app')
@section('header', 'Detail Pengajuan Cuti')
@section('content')

@php
    $status = $leave['Status'] ?? 'SUBMITTED';
    $badgeColor = match($status) {
        'APPROVED', 'COMPLETED' => 'green',
        'SUBMITTED', 'UNDER_REVIEW' => 'yellow',
        'REJECTED' => 'red',
        'CANCELLED' => 'slate',
        default => 'slate',
    };
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="Pengajuan Cuti #{{ $leave['Leave_ID'] }}" 
        description="Pemohon: {{ $docData['employee']['Full_Name'] ?? $leave['Employee_Name'] }} ({{ $leave['Employee_ID'] }})"
        status="{{ $status }}"
        badgeColor="{{ $badgeColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'HR' => '#', 'Pengajuan Cuti' => route('hr.leaves.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('leaves.pdf', $leave['Leave_ID']) }}" target="_blank" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-md transition-colors flex items-center gap-1.5">
                📄 PDF Surat Cuti Resmi
            </a>

            @if(in_array(strtoupper(auth()->user()->Role ?? ''), ['ADMINISTRATOR', 'HR', 'DIRECTOR', 'MASTER']) && in_array(strtoupper($status), ['SUBMITTED', 'UNDER_REVIEW']))
                <form action="{{ route('hr.leaves.approve', $leave['Leave_ID']) }}" method="POST" onsubmit="return confirm('Setujui pengajuan cuti ini?');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        ✅ Setujui Cuti
                    </button>
                </form>

                <div x-data="{ openReject: false }" class="inline">
                    <button @click="openReject = true" type="button" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        ❌ Tolak Cuti
                    </button>

                    <div x-show="openReject" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" style="display: none;">
                        <div @click.away="openReject = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4 text-left">
                            <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-2">Tolak Pengajuan Cuti</h3>
                            <form action="{{ route('hr.leaves.reject', $leave['Leave_ID']) }}" method="POST" class="space-y-4">
                                @csrf
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Alasan Penolakan</label>
                                    <textarea name="reason" rows="3" class="w-full text-xs border border-slate-200 rounded-xl p-3" placeholder="Alasan penolakan..." required></textarea>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openReject = false" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                                    <button type="submit" class="px-4 py-2 bg-rose-600 text-white text-xs font-bold rounded-xl">Tolak Pengajuan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if(!in_array(strtoupper($status), ['COMPLETED', 'CANCELLED']))
                <form action="{{ route('hr.leaves.cancel', $leave['Leave_ID']) }}" method="POST" onsubmit="return confirm('Batalkan pengajuan cuti ini?');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl font-bold text-xs transition-colors">
                        🚫 Batalkan
                    </button>
                </form>
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tipe Cuti</p>
                        <p class="text-base font-black text-slate-800">{{ $leave['Leave_Type'] }}</p>
                    </div>

                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Durasi Terhitung</p>
                        <p class="text-base font-black text-blue-600">{{ $leave['Duration_Days'] ?? 1 }} Hari Kerja</p>
                    </div>
                </div>

                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Rentang Tanggal</p>
                    <p class="text-sm font-bold text-slate-800">{{ $leave['Start_Date'] }} s/d {{ $leave['End_Date'] }}</p>
                </div>

                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Alasan Pengajuan</p>
                    <p class="text-xs text-slate-700 leading-relaxed font-medium">"{{ $leave['Reason'] }}"</p>
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">Metadata & Audit Trail</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">No. Dokumen Cuti</p>
                        <p class="text-sm font-mono font-bold text-blue-600 mt-1">{{ $leave['Document_Number'] ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Disetujui Oleh</p>
                        <p class="text-sm font-bold text-emerald-600 mt-1">{{ $leave['Approved_By'] ?? 'Belum Disetujui' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
