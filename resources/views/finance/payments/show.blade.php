@extends('layouts.app')
@section('header', 'Verifikasi Pembayaran & Kuitansi')
@section('content')

@php
    $status = $payment['Status'] ?? 'Waiting Verification';
    $statusColor = match($status) {
        'Verified' => 'green',
        'Rejected' => 'red',
        'Waiting Verification' => 'yellow',
        'Need Revision' => 'purple',
        default => 'slate',
    };
    $amountPaid = (float)($payment['Amount_Paid'] ?? 0);
    $invoiceAmount = (float)($invoice['Amount'] ?? 0);
    $remainingAmount = (float)($invoice['Remaining_Amount'] ?? $invoiceAmount);
    $isOverpaying = ($amountPaid > $remainingAmount && $remainingAmount > 0);
    $studentName = $payment['student_name'] ?? \App\Helpers\UserResolverHelper::getName($payment['Student_ID'] ?? '');
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="Kuitansi #{{ $payment['Payment_ID'] ?? '-' }}" 
        description="Tagihan #{{ $payment['Invoice_ID'] ?? '-' }} | Pembayar: {{ $studentName }} ({{ $payment['Student_ID'] ?? '-' }})"
        status="{{ $status }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Pembayaran' => route('payments.index'), 'Verifikasi' => '#']"
    >
        <x-slot:actions>
            <x-universal.action-button action="delete" url="{{ route('payments.destroy', $payment['Payment_ID']) }}" />
        </x-slot:actions>

        <x-slot:sidebarContent>
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">Nominal Dibayar</p>
                <p class="text-2xl font-black text-slate-800 mt-1">Rp {{ number_format($amountPaid, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">Metode Pembayaran</p>
                <p class="text-sm font-bold text-slate-800 mt-0.5 uppercase">{{ $payment['Payment_Method'] ?? 'TRANSFER' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">Tanggal Bayar</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ !empty($payment['Payment_Date']) ? \Carbon\Carbon::parse($payment['Payment_Date'])->format('d M Y') : '-' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">Status Verifikasi</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">
                    <x-badge color="{{ $statusColor }}">{{ $status }}</x-badge>
                </p>
            </div>
        </x-slot:sidebarContent>

        <x-slot:information>
            <div class="space-y-8">
                <!-- OVERPAYMENT ALERT -->
                @if($isOverpaying)
                    <div class="bg-amber-50 border-2 border-amber-200 p-5 rounded-2xl flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-lg">⚠️</span>
                            <div>
                                <h4 class="text-sm font-bold text-amber-900">Peringatan Overpayment!</h4>
                                <p class="text-xs text-amber-700 mt-0.5">Nominal bayar (Rp {{ number_format($amountPaid, 0, ',', '.') }}) melebihi sisa tagihan (Rp {{ number_format($remainingAmount, 0, ',', '.') }}).</p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl space-y-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Informasi Kwitansi & Transaksi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="text-slate-500 font-medium">No. Kwitansi Resmi:</span>
                            <span class="font-mono font-bold text-slate-800 block mt-0.5">{{ $payment['Receipt_Number'] ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium">No. Tagihan (Invoice):</span>
                            <span class="font-mono font-bold text-blue-600 block mt-0.5">{{ $payment['Invoice_ID'] ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">Metadata & Audit Trail</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Dibuat Oleh</p>
                        <p class="text-sm font-bold text-slate-800 mt-1">{{ $payment['Created_By_Name'] ?? \App\Helpers\UserResolverHelper::getName($payment['Created_By'] ?? '') }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Diverifikasi Oleh</p>
                        <p class="text-sm font-bold text-emerald-600 mt-1">{{ $payment['Approved_By_Name'] ?? \App\Helpers\UserResolverHelper::getName($payment['Verified_By'] ?? '') }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
