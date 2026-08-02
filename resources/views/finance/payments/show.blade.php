@extends('layouts.app')
@section('header', 'Verifikasi Pembayaran')
@section('content')

@php
    $status = $payment['Status'] ?? 'Waiting Verification';
    $statusColor = match($status) {
        'Verified' => 'green',
        'Rejected' => 'red',
        'Waiting Verification' => 'yellow',
        default => 'slate',
    };
    $tab = request('tab', 'informasi');
@endphp

<x-universal.detail-layout 
    title="Kuitansi #{{ $payment['Payment_ID'] ?? '-' }}" 
    subtitle="{{ $payment['Student_ID'] ?? '-' }}"
    status="{{ $status }}"
    statusColor="{{ $statusColor }}"
    avatarInitials="{{ substr($payment['Student_ID'] ?? 'S', 0, 1) }}"
    activeTab="{{ $tab }}"
    :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Pembayaran' => route('payments.index'), 'Detail' => '#']"
>
    <x-slot:actions>
        <x-universal.action-button action="delete" url="{{ route('payments.destroy', $payment['Payment_ID'] ?? 1) }}" />
    </x-slot:actions>

    <x-slot:sidebarContent>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Nominal Dibayar</p>
            <p class="text-xl font-black text-slate-800 mt-1">Rp {{ number_format($payment['Amount_Paid'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Tanggal Transfer</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $payment['Transfer_Date'] ?? $payment['Payment_Date'] ?? '-' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Status</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">
                @php
                    $statusText = match($status) {
                        'Verified' => 'Terverifikasi',
                        'Rejected' => 'Ditolak',
                        'Waiting Verification' => 'Menunggu Verifikasi',
                        'Need Revision' => 'Butuh Revisi',
                        default => $status,
                    };
                @endphp
                <x-badge color="{{ $statusColor }}">{{ $statusText }}</x-badge>
            </p>
        </div>
    </x-slot:sidebarContent>

    <x-slot:information>
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Informasi Pembayaran</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Tagihan</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $payment['Invoice_ID'] ?? '-' }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-xs font-bold text-slate-400 uppercase">Nama Pengirim</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($payment['Sender_Name']) ? $payment['Sender_Name'] : ($payment['Reference_Number'] ?? '-') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        @php $proofImage = $payment['Proof_Image'] ?? $payment['Proof_File'] ?? null; @endphp
                        <p class="text-xs font-bold text-slate-400 uppercase mb-2">Bukti Pembayaran</p>
                        @if(!empty($proofImage))
                            <div class="border border-slate-200 rounded-xl overflow-hidden bg-slate-50 text-center flex flex-col items-center justify-center p-4">
                                @if(Str::endsWith(strtolower($proofImage), ['.jpg', '.jpeg', '.png']))
                                    <div x-data="{ openImage: false }">
                                        <div @click="openImage = true" class="block hover:opacity-80 transition-opacity" title="Klik untuk memperbesar">
                                            <img src="{{ asset('storage/' . $proofImage) }}" alt="Bukti Pembayaran" class="max-w-full h-auto max-h-96 object-contain rounded cursor-pointer">
                                        </div>
                                        
                                        <!-- Modal Lightbox -->
                                        <div x-show="openImage" style="display: none;" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-slate-900/95 backdrop-blur-sm p-4 sm:p-8" x-transition.opacity>
                                            <div class="w-full max-w-5xl flex justify-between items-center mb-4">
                                                <h3 class="text-white font-bold text-lg">Bukti Pembayaran</h3>
                                                <button @click="openImage = false" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-full font-bold transition-colors flex items-center gap-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    Kembali / Tutup
                                                </button>
                                            </div>
                                            <div class="relative w-full max-w-5xl flex-1 min-h-0 flex items-center justify-center p-4" @click.outside="openImage = false">
                                                <img src="{{ asset('storage/' . $proofImage) }}" class="w-full h-full object-contain rounded-xl shadow-2xl">
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ asset('storage/' . $proofImage) }}" target="_blank" class="block hover:opacity-80 transition-opacity" title="Klik untuk mengunduh">
                                        <img src="https://via.placeholder.com/400x300.png?text=Lihat+Dokumen" alt="Dokumen" class="max-w-full h-auto max-h-96 object-contain rounded cursor-pointer">
                                    </a>
                                @endif
                                <p class="text-slate-500 font-medium text-sm mt-4">File: {{ $proofImage }}</p>
                            </div>
                        @else
                            <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center text-slate-400">
                                Tidak ada bukti yang diunggah
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Aksi Verifikasi</h3>
                @if($status == 'Waiting Verification')
                    <div id="verifyForm">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Catatan (Wajib untuk Tolak/Revisi)</label>
                                <textarea name="Notes" id="notesTextarea" form="rejectForm" rows="4" class="w-full rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm" placeholder="Alasan penolakan atau revisi..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <form action="{{ route('payments.verify', $payment['Payment_ID'] ?? 1) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" name="Status" value="Verified" class="px-5 py-2.5 text-sm font-bold text-white bg-green-600 rounded-xl hover:bg-green-700 transition-colors shadow-sm">Setujui</button>
                                </form>
                                <form action="{{ route('payments.verify', $payment['Payment_ID'] ?? 1) }}" method="POST" class="inline" onsubmit="document.getElementById('revisionNotes').value = document.getElementById('notesTextarea').value;">
                                    @csrf
                                    <input type="hidden" name="Notes" id="revisionNotes" value="">
                                    <button type="submit" name="Status" value="Need Revision" class="px-5 py-2.5 text-sm font-bold text-white bg-yellow-500 rounded-xl hover:bg-yellow-600 transition-colors shadow-sm">Minta Revisi</button>
                                </form>
                                <form id="rejectForm" action="{{ route('payments.verify', $payment['Payment_ID'] ?? 1) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" name="Status" value="Rejected" class="px-5 py-2.5 text-sm font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 transition-colors shadow-sm">Tolak</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-slate-50 p-6 rounded-xl border border-slate-200 text-center">
                        <p class="text-slate-500 mb-2">Pembayaran ini sudah diproses.</p>
                        <p class="font-bold text-slate-800 text-lg">Status: {{ match($status) { 'Verified' => 'Terverifikasi', 'Rejected' => 'Ditolak', 'Waiting Verification' => 'Menunggu Verifikasi', 'Need Revision' => 'Butuh Revisi', default => $status } }}</p>
                        @if(!empty($payment['Notes']))
                            <div class="text-sm text-slate-600 mt-4 bg-white p-4 rounded-xl border border-slate-200 text-left">
                                <span class="font-bold block mb-1">Catatan:</span>
                                {{ $payment['Notes'] }}
                            </div>
                        @endif
                        <p class="text-sm text-slate-400 mt-4">Diverifikasi oleh: {{ $payment['Verified_By'] ?? '-' }} pada {{ $payment['Verified_At'] ?? '-' }}</p>
                    </div>
                @endif
            </div>

        </div>
    </x-slot:information>

    <x-slot:audit>
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Log Sistem</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Record ID</p>
                    <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $payment['Payment_ID'] ?? '-' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">ID Tagihan</p>
                    <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $payment['Invoice_ID'] ?? '-' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $payment['Created_At'] ?? '-' }}</p>
                </div>
            </div>
        </div>
    </x-slot:audit>

</x-universal.detail-layout>

@endsection



