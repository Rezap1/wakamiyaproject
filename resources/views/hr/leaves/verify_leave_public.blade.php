@extends('layouts.app')
@section('header', 'Verifikasi Keabsahan Surat Cuti Pegawai')
@section('content')

@php
    $status = $data['leave']['Status'] ?? 'SUBMITTED';
    $isApproved = strtoupper($status) === 'APPROVED' || strtoupper($status) === 'COMPLETED';
@endphp

<div class="max-w-2xl mx-auto py-8 px-4 space-y-6">
    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center space-y-4">
        <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-3xl {{ $isApproved ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : 'bg-amber-100 text-amber-600 border border-amber-200' }}">
            @if($isApproved) ✅ @else ⏳ @endif
        </div>

        <div>
            <span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-slate-100 text-slate-800 border border-slate-200">
                STATUS CUTI: {{ $status }}
            </span>
            <h2 class="text-2xl font-black text-slate-800 mt-3">Surat Cuti #{{ $data['leave']['Document_Number'] ?? $data['leave']['Leave_ID'] ?? '-' }}</h2>
            <p class="text-xs text-slate-500 mt-1">Sistem Otentikasi & Verifikasi Cuti Wakamiya Management System (WMS)</p>
        </div>

        <div class="pt-4 border-t border-slate-100 grid grid-cols-2 gap-4 text-left text-xs">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Pegawai Pemohon</span>
                <span class="text-sm font-black text-slate-800 mt-1 block">{{ $data['employee']['Full_Name'] ?? $data['leave']['Employee_Name'] ?? '-' }}</span>
            </div>
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Durasi Cuti</span>
                <span class="text-sm font-black text-blue-600 mt-1 block">{{ $data['leave']['Duration_Days'] ?? 1 }} Hari</span>
            </div>
        </div>

        <div class="space-y-3 pt-4 text-left border-t border-slate-100 text-xs">
            <div class="flex justify-between py-1 border-b border-slate-50">
                <span class="text-slate-500 font-medium">Tipe Cuti:</span>
                <span class="font-bold text-slate-800">{{ $data['leave']['Leave_Type'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50">
                <span class="text-slate-500 font-medium">Rentang Tanggal:</span>
                <span class="font-bold text-slate-800">{{ $data['leave']['Start_Date'] ?? '-' }} s/d {{ $data['leave']['End_Date'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between py-1">
                <span class="text-slate-500 font-medium">Disetujui Oleh:</span>
                <span class="font-bold text-emerald-600">{{ $data['leave']['Approved_By'] ?? 'Belum Disetujui' }}</span>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100">
            <a href="{{ route('leaves.pdf', $data['leave']['Leave_ID']) }}" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-2xl shadow-md transition-colors inline-block text-xs uppercase tracking-wider">
                📄 Unduh Dokumen PDF Surat Cuti Resmi
            </a>
        </div>
    </div>
</div>
@endsection
