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
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="Kuitansi #{{ $payment['Payment_ID'] ?? '-' }}" 
        description="Target Tagihan: #{{ $payment['Invoice_ID'] ?? '-' }} | Siswa ID: {{ $payment['Student_ID'] ?? '-' }}"
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
                <!-- OVERPAYMENT OR STATE WARNING ALERT -->
                @if($isOverpaying)
                    <div class="bg-rose-50 border-2 border-rose-200 p-5 rounded-2xl flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-rose-500 text-white flex items-center justify-center font-black text-sm shrink-0">⚠️</span>
                        <div>
                            <h4 class="text-xs font-black text-rose-900 uppercase">PERINGATAN NOMINAL OVERPAYMENT</h4>
                            <p class="text-xs text-rose-700 mt-0.5">Nominal pembayaran (Rp {{ number_format($amountPaid, 0, ',', '.') }}) melebihi sisa tagihan (Rp {{ number_format($remainingAmount, 0, ',', '.') }}). Sistem server-side akan menolak verifikasi jika overpayment.</p>
                        </div>
                    </div>
                @endif

                <!-- INVOICE REFERENCE CARD -->
                @if(!empty($invoice))
                    <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl space-y-3">
                        <div class="flex justify-between items-center border-b pb-2">
                            <span class="text-xs font-bold text-slate-400 uppercase">Ringkasan Invoice Referensi</span>
                            <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-lg uppercase bg-slate-200 text-slate-700">
                                Status Invoice: {{ $invoice['Status'] ?? 'Draft' }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                            <div>
                                <span class="text-slate-400 font-medium">Total Tagihan:</span>
                                <p class="font-bold text-slate-800 text-sm">Rp {{ number_format($invoiceAmount, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400 font-medium">Sudah Terbayar:</span>
                                <p class="font-bold text-emerald-600 text-sm">Rp {{ number_format((float)($invoice['Paid_Amount'] ?? 0), 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400 font-medium">Sisa Piutang:</span>
                                <p class="font-bold text-rose-600 text-sm">Rp {{ number_format($remainingAmount, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- PAYMENT INFORMATION -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Informasi Pengirim & Bukti</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nama Pengirim Rekening</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ !empty($payment['Sender_Name']) ? $payment['Sender_Name'] : ($payment['Reference_Number'] ?? '-') }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">ID Tagihan (Invoice ID)</p>
                            <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $payment['Invoice_ID'] ?? '-' }}</p>
                        </div>
                    </div>

                    <!-- PROOF OF PAYMENT FILE DISPLAY -->
                    @php $proofImage = $payment['Proof_Image'] ?? $payment['Proof_File'] ?? null; @endphp
                    <div class="mt-4">
                        <p class="text-xs font-bold text-slate-400 uppercase mb-2">Dokumen Bukti Transfer</p>
                        @if(!empty($proofImage))
                            <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50 text-center flex flex-col items-center justify-center p-4">
                                @if(Str::endsWith(strtolower($proofImage), ['.jpg', '.jpeg', '.png']))
                                    <div x-data="{ openImage: false }">
                                        <div @click="openImage = true" class="block hover:opacity-80 transition-opacity" title="Klik memperbesar">
                                            <img src="{{ asset('storage/' . $proofImage) }}" alt="Bukti Pembayaran" class="max-w-full h-auto max-h-96 object-contain rounded-xl cursor-pointer shadow-md">
                                        </div>
                                        
                                        <div x-show="openImage" style="display: none;" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-slate-900/95 backdrop-blur-sm p-4" x-transition.opacity>
                                            <div class="w-full max-w-5xl flex justify-between items-center mb-4">
                                                <h3 class="text-white font-bold text-lg">Bukti Transfer Kuitansi #{{ $payment['Payment_ID'] }}</h3>
                                                <button @click="openImage = false" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-full font-bold transition-colors">Tutup</button>
                                            </div>
                                            <div class="relative w-full max-w-5xl flex-1 flex items-center justify-center p-4">
                                                <img src="{{ asset('storage/' . $proofImage) }}" class="w-full h-full object-contain rounded-xl shadow-2xl">
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ asset('storage/' . $proofImage) }}" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded-xl font-bold text-xs shadow-sm hover:bg-blue-700">
                                        📄 Unduh Dokumen Bukti Transfer ({{ $proofImage }})
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center text-slate-400 text-xs font-bold">
                                Tidak ada dokumen bukti transfer diunggah.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- VERIFICATION ACTIONS (FOR FINANCE OFFICERS) -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Tindakan Keputusan Verifikasi</h3>
                    
                    @if($status === 'Waiting Verification')
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Akun Kas / Bank Penerima (Tujuan Buku Kas Transaksi) <span class="text-rose-500 font-black">*</span></label>
                                <select name="Account_ID" id="verifyAccountId" class="w-full text-xs font-bold rounded-xl border-slate-200 focus:ring-2 focus:ring-emerald-500 p-2.5">
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc['Account_Code'] }}" {{ (str_contains(strtolower($acc['Account_Name']), 'kas') && str_contains(strtolower($payment['Payment_Method'] ?? ''), 'cash')) || (str_contains(strtolower($acc['Account_Name']), 'bank') && !str_contains(strtolower($payment['Payment_Method'] ?? ''), 'cash')) ? 'selected' : '' }}>
                                            {{ $acc['Account_Code'] }} - {{ $acc['Account_Name'] }} ({{ $acc['Account_Category'] ?? 'ASSET' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Evaluator (Wajib untuk Tolak / Revisi)</label>
                                <textarea name="Notes" id="notesTextarea" rows="3" class="w-full rounded-xl border-slate-200 focus:ring-blue-500 text-xs shadow-sm" placeholder="Alasan penolakan atau instruksi revisi..."></textarea>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 pt-2">
                                <form action="{{ route('payments.verify', $payment['Payment_ID']) }}" method="POST" class="inline" onsubmit="this.Account_ID.value = document.getElementById('verifyAccountId').value; this.Notes.value = document.getElementById('notesTextarea').value;">
                                    @csrf
                                    <input type="hidden" name="Status" value="Verified">
                                    <input type="hidden" name="Account_ID" value="">
                                    <input type="hidden" name="Notes" value="">
                                    <button type="submit" class="px-6 py-2.5 text-xs font-black text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors shadow-md flex items-center gap-1.5 uppercase">
                                        ✅ Setujui & Rekam Ke Buku Kas
                                    </button>
                                </form>

                                <form action="{{ route('payments.verify', $payment['Payment_ID']) }}" method="POST" class="inline" onsubmit="this.Notes.value = document.getElementById('notesTextarea').value;">
                                    @csrf
                                    <input type="hidden" name="Status" value="Need Revision">
                                    <input type="hidden" name="Notes" value="">
                                    <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 rounded-xl transition-colors shadow-sm uppercase">
                                        🟨 Minta Revisi
                                    </button>
                                </form>

                                <form action="{{ route('payments.verify', $payment['Payment_ID']) }}" method="POST" class="inline" onsubmit="this.Notes.value = document.getElementById('notesTextarea').value;">
                                    @csrf
                                    <input type="hidden" name="Status" value="Rejected">
                                    <input type="hidden" name="Notes" value="">
                                    <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors shadow-sm uppercase">
                                        ❌ Tolak Pembayaran
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center space-y-2">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Keputusan Verifikasi</p>
                            <p class="font-black text-slate-800 text-lg uppercase">{{ $status }}</p>
                            @if(!empty($payment['Notes']))
                                <div class="text-xs text-slate-600 bg-white p-3 rounded-xl border border-slate-200 text-left mt-2">
                                    <span class="font-bold block mb-0.5">Catatan Verifikator:</span>
                                    {{ $payment['Notes'] }}
                                </div>
                            @endif
                            <p class="text-[11px] text-slate-400 pt-2">Diverifikasi oleh <strong>{{ $payment['Verified_By'] ?? '-' }}</strong> pada {{ !empty($payment['Verified_At']) ? \Carbon\Carbon::parse($payment['Verified_At'])->format('d M Y, H:i:s') : '-' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">System Metadata & Audit Trail</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Nomor Kuitansi (Primary Key)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $payment['Payment_ID'] ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Waktu Pencatatan</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($payment['Created_At']) ? \Carbon\Carbon::parse($payment['Created_At'])->format('d M Y, H:i:s') : '-' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
