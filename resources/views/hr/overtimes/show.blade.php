@extends('layouts.app')
@section('header', 'Detail Pengajuan Lembur')
@section('content')

@php
    $status = $overtime['Status'] ?? 'SUBMITTED';
    $badgeColor = match($status) {
        'APPROVED', 'INCLUDED_IN_PAYROLL' => 'green',
        'SUBMITTED' => 'yellow',
        'REJECTED' => 'red',
        default => 'slate',
    };
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="Pengajuan Lembur #{{ $overtime['Overtime_ID'] }}" 
        description="Pemohon: {{ $docData['employee']['Full_Name'] ?? $overtime['Employee_Name'] }} ({{ $overtime['Employee_ID'] }})"
        status="{{ $status }}"
        badgeColor="{{ $badgeColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'HR' => '#', 'Pengajuan Lembur' => route('hr.overtimes.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('hr.overtimes.pdf', $overtime['Overtime_ID']) }}" target="_blank" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-md transition-colors flex items-center gap-1.5">
                📄 PDF Surat Lembur Resmi
            </a>

            @if(in_array(strtoupper(auth()->user()->Role ?? ''), ['ADMINISTRATOR', 'HR', 'DIRECTOR']) && strtoupper($status) === 'SUBMITTED')
                <form action="{{ route('hr.overtimes.approve', $overtime['Overtime_ID']) }}" method="POST" onsubmit="return confirm('Setujui pengajuan lembur ini?');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        ✅ Setujui Lembur
                    </button>
                </form>

                <div x-data="{ openReject: false }" class="inline">
                    <button @click="openReject = true" type="button" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        ❌ Tolak Lembur
                    </button>

                    <div x-show="openReject" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" style="display: none;">
                        <div @click.away="openReject = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4 text-left">
                            <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-2">Tolak Pengajuan Lembur</h3>
                            <form action="{{ route('hr.overtimes.reject', $overtime['Overtime_ID']) }}" method="POST" class="space-y-4">
                                @csrf
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Alasan Penolakan</label>
                                    <textarea name="reason" rows="3" class="w-full text-xs border border-slate-200 rounded-xl p-3" placeholder="Alasan penolakan..." required></textarea>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openReject = false" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                                    <button type="submit" class="px-4 py-2 bg-rose-600 text-white text-xs font-bold rounded-xl">Tolak Lembur</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tanggal Lembur</p>
                        <p class="text-base font-black text-slate-800">{{ $overtime['Date'] }}</p>
                    </div>

                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Durasi Terhitung</p>
                        <p class="text-base font-black text-blue-600">{{ $overtime['Duration_Hours'] ?? 0 }} Jam</p>
                    </div>

                    <div class="bg-emerald-50 p-5 rounded-2xl border border-emerald-100 space-y-2">
                        <p class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Estimasi Upah Lembur</p>
                        <p class="text-base font-black text-emerald-700">Rp {{ number_format((float)($overtime['Overtime_Pay'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Jam Lembur</p>
                    <p class="text-sm font-bold text-slate-800">{{ $overtime['Start_Time'] }} s/d {{ $overtime['End_Time'] }}</p>
                </div>

                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tugas & Alasan Pengajuan</p>
                    <p class="text-xs text-slate-700 leading-relaxed font-medium">"{{ $overtime['Reason'] }}"</p>
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">Metadata & Audit Trail</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">No. Dokumen Lembur</p>
                        <p class="text-sm font-mono font-bold text-blue-600 mt-1">{{ $overtime['Document_Number'] ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Disetujui Oleh</p>
                        <p class="text-sm font-bold text-emerald-600 mt-1">{{ $overtime['Approved_By'] ?? 'Belum Disetujui' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
