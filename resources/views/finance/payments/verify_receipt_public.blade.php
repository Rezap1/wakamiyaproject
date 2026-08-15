@extends('layouts.app')
@section('header', 'Verifikasi Keabsahan Kuitansi Pembayaran')
@section('content')

@php
    $status = $data['payment']['Status'] ?? 'Waiting Verification';
    $statusText = match($status) {
        'Verified' => 'VERIFIED (SAH & TERVERIFIKASI)',
        'Rejected' => 'REJECTED (DITOLAK / TIDAK SAH)',
        default => 'PENDING (MENUNGGU VERIFIKASI)',
    };
    $statusColor = match($status) {
        'Verified' => 'emerald',
        'Rejected' => 'rose',
        default => 'amber',
    };
@endphp

<div class="max-w-2xl mx-auto py-8 px-4 space-y-6">
    <!-- VERIFICATION STATUS HEADER -->
    <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center space-y-4">
        <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-3xl {{ $status === 'Verified' ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : ($status === 'Rejected' ? 'bg-rose-100 text-rose-600 border border-rose-200' : 'bg-amber-100 text-amber-600 border border-amber-200') }}">
            @if($status === 'Verified') ✅ @elseif($status === 'Rejected') ❌ @else ⏳ @endif
        </div>

        <div>
            <span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider {{ $status === 'Verified' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : ($status === 'Rejected' ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-200') }}">
                {{ $statusText }}
            </span>
            <h2 class="text-2xl font-black text-slate-800 mt-3">Kuitansi Pembayaran #{{ $data['payment']['Payment_ID'] ?? '-' }}</h2>
            <p class="text-xs text-slate-500 mt-1">Sistem Otentikasi & Verifikasi Publik Wakamiya Management System (WMS)</p>
        </div>

        <div class="pt-4 border-t border-slate-100 grid grid-cols-2 gap-4 text-left text-xs">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Jumlah Pembayaran</span>
                <span class="text-lg font-black text-slate-800 mt-1 block">Rp {{ number_format($data['balances']['currentPayment'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] block">Tanggal Bayar</span>
                <span class="text-sm font-bold text-slate-800 mt-1 block">
                    {{ !empty($data['payment']['Payment_Date']) ? \Carbon\Carbon::parse($data['payment']['Payment_Date'])->format('d M Y') : '-' }}
                </span>
            </div>
        </div>

        <!-- DETAILED PUBLIC INFORMATION -->
        <div class="space-y-3 pt-4 text-left border-t border-slate-100">
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">Penerima Tagihan:</span>
                <span class="font-bold text-slate-800">{{ $data['customer']['name'] ?? '-' }} ({{ $data['customer']['code'] ?? '-' }})</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">Referensi Invoice:</span>
                <span class="font-mono font-bold text-slate-800">#{{ $data['payment']['Invoice_ID'] ?? '-' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">Metode Pembayaran:</span>
                <span class="font-bold text-slate-800 uppercase">{{ $data['payment']['Payment_Method'] ?? 'TRANSFER' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-50 text-xs">
                <span class="text-slate-500 font-medium">Diverifikasi Oleh:</span>
                <span class="font-bold text-slate-800">{{ $data['payment']['Verified_By'] ?? 'Finance Officer' }}</span>
            </div>
            <div class="flex justify-between py-1 text-xs">
                <span class="text-slate-500 font-medium">Waktu Verifikasi:</span>
                <span class="font-bold text-slate-800">{{ !empty($data['payment']['Verified_At']) ? \Carbon\Carbon::parse($data['payment']['Verified_At'])->format('d M Y, H:i:s') : '-' }}</span>
            </div>
        </div>

        <!-- ACTION BUTTON FOR VERIFIED RECEIPT PDF -->
        @if($status === 'Verified')
            <div class="pt-4 border-t border-slate-100">
                <a href="{{ route('payments.receipt', $data['payment']['Payment_ID']) }}" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-2xl shadow-md transition-colors inline-block text-xs uppercase tracking-wider">
                    📄 Unduh Dokumen PDF Kuitansi Resmi
                </a>
            </div>
        @endif
    </div>

    <!-- FOOTER PRIVACY STATEMENT -->
    <p class="text-[11px] text-slate-400 text-center leading-relaxed">
        Halaman verifikasi ini dipublikasikan secara aman oleh Wakamiya Management System (WMS). Tidak ada data sensitif yang ditampilkan untuk menjaga privasi transaksi.
    </p>
</div>
@endsection
