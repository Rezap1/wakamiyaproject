@extends('layouts.app')
@section('header', 'Verifikasi Surat Cuti')
@section('content')

@php
    $leave = $data['leave'] ?? [];
    $employee = $data['employee'] ?? [];
    $status = $leave['Status'] ?? 'SUBMITTED';
    $documentNumber = $leave['Document_Number'] ?? 'Dokumen Terverifikasi';
    $maskName = function ($name) {
        $name = trim((string) $name);
        if ($name === '') return '-';
        $parts = preg_split('/\s+/', $name);
        return collect($parts)->map(function ($part, $index) {
            return $index === 0 ? $part : substr($part, 0, 1) . str_repeat('*', max(strlen($part) - 1, 1));
        })->implode(' ');
    };
@endphp

<div class="max-w-xl mx-auto py-8 px-4">
    <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm text-center space-y-5">
        <div class="w-14 h-14 rounded-2xl mx-auto flex items-center justify-center bg-emerald-100 text-emerald-700 border border-emerald-200">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>

        <div>
            <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                Dokumen Valid
            </span>
            <h2 class="text-2xl font-black text-slate-800 mt-3">Surat Cuti Terverifikasi</h2>
            <p class="text-xs text-slate-500 mt-1">Detail HR internal tidak ditampilkan pada halaman publik.</p>
        </div>

        <div class="space-y-3 pt-4 text-left border-t border-slate-100 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-slate-500 font-medium">Nomor Dokumen</span>
                <span class="font-bold text-slate-800 text-right">{{ $documentNumber }}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-slate-500 font-medium">Pegawai</span>
                <span class="font-bold text-slate-800 text-right">{{ $maskName($employee['Full_Name'] ?? $leave['Employee_Name'] ?? '') }}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-slate-500 font-medium">Tipe</span>
                <span class="font-bold text-slate-800 text-right">{{ $leave['Leave_Type'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-slate-500 font-medium">Status</span>
                <span class="font-bold text-emerald-700 text-right">{{ $status }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
