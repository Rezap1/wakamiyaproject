@extends('layouts.app')
@section('header', 'Verifikasi Keabsahan Slip Gaji Pegawai')
@section('content')

@php
    $status = $data['payroll']['Status'] ?? 'Draft';
    $isPaid = strtoupper($status) === 'PAID' || strtoupper($status) === 'CLOSED';
@endphp

<div class="max-w-2xl mx-auto py-8 px-4 space-y-6">
    <!-- VERIFICATION STATUS HEADER -->
    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center space-y-4">
        <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-3xl {{ $isPaid ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : 'bg-amber-100 text-amber-600 border border-amber-200' }}">
            @if($isPaid) ✅ @else ⏳ @endif
        </div>

        <div>
            <span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-slate-100 text-slate-800 border border-slate-200">
                STATUS PAYROLL: {{ $status }}
            </span>
            <h2 class="text-2xl font-black text-slate-800 mt-3">Slip Gaji #{{ $data['payroll']['Payroll_Number'] ?? $data['payroll']['Payroll_ID'] ?? '-' }}</h2>
            <p class="text-xs text-slate-500 mt-1">Sistem Otentikasi & Verifikasi Slip Gaji Wakamiya Management System (WMS)</p>
        </div>

        <div class="pt-4 border-t border-slate-100 grid grid-cols-2 gap-4 text-left text-xs">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Gaji Bersih (Net Salary)</span>
                <span class="text-lg font-black text-emerald-600 mt-1 block">Rp {{ number_format((float)($data['payroll']['Net_Salary'] ?? 0), 0, ',', '.') }}</span>
            </div>
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Periode Penggajian</span>
                <span class="text-lg font-black text-slate-800 mt-1 block">{{ $data['payroll']['Payroll_Period'] ?? '-' }}</span>
            </div>
        </div>

        <!-- DETAILED PUBLIC INFORMATION -->
        <div class="space-y-3 pt-4 text-left border-t border-slate-100">
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">Pemilik Slip Gaji:</span>
                <span class="font-bold text-slate-800">{{ $data['employee']['Full_Name'] ?? $data['payroll']['Employee_ID'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">ID Pegawai (NIP):</span>
                <span class="font-bold text-slate-800">{{ $data['payroll']['Employee_ID'] ?? '-' }} ({{ $data['employee']['Employee_Number'] ?? '-' }})</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">No. Dokumen Resmi:</span>
                <span class="font-mono font-bold text-blue-600">{{ $data['payroll']['Document_Number'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between py-1 text-xs">
                <span class="text-slate-500 font-medium">Tanggal Pembayaran:</span>
                <span class="font-bold text-slate-800">
                    {{ !empty($data['payroll']['Paid_Date']) ? \Carbon\Carbon::parse($data['payroll']['Paid_Date'])->format('d M Y, H:i') : 'Belum Dilunasi' }}
                </span>
            </div>
        </div>

        <!-- ACTION BUTTON FOR PDF DOWNLOAD -->
        <div class="pt-4 border-t border-slate-100">
            <a href="{{ route('payrolls.pdf', $data['payroll']['Payroll_ID']) }}" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-2xl shadow-md transition-colors inline-block text-xs uppercase tracking-wider">
                📄 Unduh Dokumen PDF Slip Gaji Resmi
            </a>
        </div>
    </div>

    <!-- FOOTER PRIVACY STATEMENT -->
    <p class="text-[11px] text-slate-400 text-center leading-relaxed">
        Halaman verifikasi ini dipublikasikan secara resmi oleh Wakamiya Management System (WMS) untuk memastikan otentisitas dokumen Slip Gaji Pegawai.
    </p>
</div>
@endsection
