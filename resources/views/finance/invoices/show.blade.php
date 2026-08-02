@extends('layouts.app')
@section('header', 'Detail Tagihan')
@section('content')

@php
    $status = $invoice['Status'] ?? 'Draft';
    $statusColor = match($status) {
        'Paid' => 'green',
        'Waiting Payment' => 'yellow',
        default => 'slate',
    };
    $tab = request('tab', 'informasi');
@endphp

<x-universal.detail-layout 
    title="Invoice #{{ $invoice['Invoice_ID'] ?? '-' }}" 
    subtitle="{{ $invoice['Student_ID'] ?? '-' }}"
    status="{{ $status }}"
    statusColor="{{ $statusColor }}"
    avatarInitials="{{ substr($invoice['Student_ID'] ?? 'S', 0, 1) }}"
    activeTab="{{ $tab }}"
    :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Tagihan' => route('invoices.index'), 'Detail' => '#']"
>
    
    <x-slot:headerActions>
        <x-universal.action-button action="edit" url="{{ route('invoices.edit', $invoice['Invoice_ID'] ?? 1) }}" />
    </x-slot:headerActions>

    <x-slot:sidebarContent>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Nominal</p>
            <p class="text-xl font-black text-slate-800 mt-1">Rp {{ number_format($invoice['Amount'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Jatuh Tempo</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $invoice['Due_Date'] ?? '-' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Status</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">
                <x-badge color="{{ $statusColor }}">{{ $status }}</x-badge>
            </p>
        </div>
    </x-slot:sidebarContent>

    @if($tab === 'informasi')
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Tagihan</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Kategori</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $invoice['Category'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Referensi</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">
                            {{ $invoice['Reference_Type'] ?? '-' }} 
                            @if(isset($invoice['Reference_ID']))
                                (#{{ $invoice['Reference_ID'] }})
                            @endif
                        </p>
                    </div>
                    @if(isset($invoice['Payment_Date']))
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Pembayaran</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $invoice['Payment_Date'] }}</p>
                        </div>
                    @endif
                </div>
            </div>

            @if(isset($invoice['Notes']) && $invoice['Notes'])
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan Tambahan</h3>
                <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200 whitespace-pre-wrap">{{ $invoice['Notes'] }}</p>
            </div>
            @endif
        </div>
    @elseif($tab === 'audit')
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Log Sistem</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">ID Rekam</p>
                    <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $invoice['Invoice_ID'] ?? '-' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $invoice['Created_At'] ?? '-' }}</p>
                </div>
            </div>
        </div>
    @else
        <x-universal.empty-state title="Belum Ada Data" description="Data untuk tab ini belum tersedia atau sedang dikembangkan." />
    @endif

</x-universal.detail-layout>

@endsection



