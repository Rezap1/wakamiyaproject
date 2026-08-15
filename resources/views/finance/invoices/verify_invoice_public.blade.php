@extends('layouts.app')
@section('header', 'Verifikasi Keabsahan Invoice Tagihan')
@section('content')

@php
    $status = $data['invoice']['Display_Status'] ?? ($data['invoice']['Status'] ?? 'Draft');
    $statusColor = match($status) {
        'Paid' => 'emerald',
        'OVERDUE' => 'rose',
        'Partial Paid' => 'purple',
        'Cancelled' => 'slate',
        default => 'amber',
    };
@endphp

<div class="max-w-2xl mx-auto py-8 px-4 space-y-6">
    <!-- VERIFICATION STATUS HEADER -->
    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center space-y-4">
        <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-3xl {{ $status === 'Paid' ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : ($status === 'OVERDUE' ? 'bg-rose-100 text-rose-600 border border-rose-200' : 'bg-amber-100 text-amber-600 border border-amber-200') }}">
            @if($status === 'Paid') ✅ @elseif($status === 'OVERDUE') ⚠️ @elseif($status === 'Cancelled') 🚫 @else 📄 @endif
        </div>

        <div>
            <span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-slate-100 text-slate-800 border border-slate-200">
                STATUS INVOICE: {{ $status }}
            </span>
            <h2 class="text-2xl font-black text-slate-800 mt-3">Invoice Tagihan #{{ $data['invoice']['Invoice_ID'] ?? '-' }}</h2>
            <p class="text-xs text-slate-500 mt-1">Sistem Otentikasi & Verifikasi Publik Wakamiya Management System (WMS)</p>
        </div>

        <div class="pt-4 border-t border-slate-100 grid grid-cols-2 gap-4 text-left text-xs">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Grand Total Tagihan</span>
                <span class="text-lg font-black text-slate-800 mt-1 block">Rp {{ number_format((float)($data['invoice']['Grand_Total'] ?? $data['invoice']['Amount'] ?? 0), 0, ',', '.') }}</span>
            </div>
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Sisa Piutang Terkini</span>
                <span class="text-lg font-black text-rose-600 mt-1 block">Rp {{ number_format((float)($data['invoice']['Remaining_Amount'] ?? 0), 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- DETAILED PUBLIC INFORMATION -->
        <div class="space-y-3 pt-4 text-left border-t border-slate-100">
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">Ditujukan Kepada:</span>
                <span class="font-bold text-slate-800">{{ $data['customer']['name'] ?? '-' }} ({{ $data['customer']['code'] ?? '-' }})</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">Kategori Tagihan:</span>
                <span class="font-bold text-slate-800">{{ $data['invoice']['Category'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">Jatuh Tempo:</span>
                <span class="font-bold text-rose-600">
                    {{ !empty($data['invoice']['Due_Date']) ? \Carbon\Carbon::parse($data['invoice']['Due_Date'])->format('d M Y') : '-' }}
                </span>
            </div>
            <div class="flex justify-between py-1 text-xs">
                <span class="text-slate-500 font-medium">Total Sudah Dibayar:</span>
                <span class="font-bold text-emerald-600">Rp {{ number_format((float)($data['invoice']['Paid_Amount'] ?? 0), 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- ACTION BUTTON FOR PDF DOWNLOAD -->
        <div class="pt-4 border-t border-slate-100">
            <a href="{{ route('invoices.pdf', $data['invoice']['Invoice_ID']) }}" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-2xl shadow-md transition-colors inline-block text-xs uppercase tracking-wider">
                📄 Unduh Dokumen PDF Invoice Resmi
            </a>
        </div>
    </div>

    <!-- FOOTER PRIVACY STATEMENT -->
    <p class="text-[11px] text-slate-400 text-center leading-relaxed">
        Halaman verifikasi ini dipublikasikan secara resmi oleh Wakamiya Management System (WMS) untuk memastikan keabsahan dokumen tagihan.
    </p>
</div>
@endsection
