@extends('layouts.app')
@section('header', 'Verifikasi Keabsahan Surat Lembur Pegawai')
@section('content')

@php
    $status = $data['overtime']['Status'] ?? 'SUBMITTED';
    $isApproved = strtoupper($status) === 'APPROVED' || strtoupper($status) === 'INCLUDED_IN_PAYROLL';
@endphp

<div class="max-w-2xl mx-auto py-8 px-4 space-y-6">
    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center space-y-4">
        <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-3xl {{ $isApproved ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : 'bg-amber-100 text-amber-600 border border-amber-200' }}">
            @if($isApproved) ✅ @else ⏳ @endif
        </div>

        <div>
            <span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-slate-100 text-slate-800 border border-slate-200">
                STATUS LEMBUR: {{ $status }}
            </span>
            <h2 class="text-2xl font-black text-slate-800 mt-3">Surat Lembur #{{ $data['overtime']['Document_Number'] ?? $data['overtime']['Overtime_ID'] ?? '-' }}</h2>
            <p class="text-xs text-slate-500 mt-1">Sistem Otentikasi & Verifikasi Lembur Wakamiya Management System (WMS)</p>
        </div>

        <div class="pt-4 border-t border-slate-100 grid grid-cols-2 gap-4 text-left text-xs">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Pegawai Lembur</span>
                <span class="text-sm font-black text-slate-800 mt-1 block">{{ $data['employee']['Full_Name'] ?? $data['overtime']['Employee_Name'] ?? '-' }}</span>
            </div>
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Durasi Terhitung</span>
                <span class="text-sm font-black text-emerald-600 mt-1 block">{{ $data['overtime']['Duration_Hours'] ?? 0 }} Jam</span>
            </div>
        </div>

        <div class="space-y-3 pt-4 text-left border-t border-slate-100 text-xs">
            <div class="flex justify-between py-1 border-b border-slate-50">
                <span class="text-slate-500 font-medium">Tanggal Lembur:</span>
                <span class="font-bold text-slate-800">{{ $data['overtime']['Date'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <span class="text-slate-500 font-medium">Jam Lembur:</span>
                <span class="font-bold text-slate-800">{{ $data['overtime']['Start_Time'] ?? '-' }} s/d {{ $data['overtime']['End_Time'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between py-1">
                <span class="text-slate-500 font-medium">Estimasi Upah Lembur:</span>
                <span class="font-bold text-emerald-600">Rp {{ number_format((float)($data['overtime']['Overtime_Pay'] ?? 0), 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100">
            <a href="{{ route('overtimes.pdf', $data['overtime']['Overtime_ID']) }}" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-2xl shadow-md transition-colors inline-block text-xs uppercase tracking-wider">
                📄 Unduh Dokumen PDF Surat Lembur Resmi
            </a>
        </div>
    </div>
</div>
@endsection
