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
    $receiptLabel = trim((string) ($payment['Receipt_Number'] ?? '')) !== '' ? $payment['Receipt_Number'] : 'Kuitansi Pembayaran';
    $invoiceLabel = trim((string) ($payment['Invoice_ID'] ?? '')) !== '' ? 'Tagihan tersedia' : 'Tanpa invoice';
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="{{ $receiptLabel }}"
        description="Pembayar: {{ $studentName }} | {{ $invoiceLabel }}"
        status="{{ $status }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Pembayaran' => route('payments.index'), 'Verifikasi' => '#']"
    >
        <x-slot:actions>
            @if($status !== 'Verified')
                <x-universal.action-button action="delete" url="{{ route('payments.destroy', $payment['Payment_ID']) }}" />
            @endif
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
                @if(in_array($status, ['Waiting Verification', 'Need Revision'], true))
                    <form method="POST" action="{{ route('payments.verify', $payment['Payment_ID']) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
                        @csrf
                        @if(trim((string) ($payment['Invoice_ID'] ?? '')) === '')
                            <div>
                                <label for="payment-invoice" class="mb-1.5 block text-xs font-bold text-slate-700">Tagihan Tujuan</label>
                                <select id="payment-invoice" name="Invoice_ID" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">Tidak terkait invoice</option>
                                    @foreach(($candidateInvoices ?? collect()) as $candidate)
                                        <option value="{{ $candidate['Invoice_ID'] }}" @selected(old('Invoice_ID') === ($candidate['Invoice_ID'] ?? ''))>
                                            {{ $candidate['Category'] ?? 'Tagihan' }} | Rp {{ number_format((float)($candidate['Remaining_Amount'] ?? 0), 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div>
                            <label for="payment-notes" class="mb-1.5 block text-xs font-bold text-slate-700">Catatan Verifikasi</label>
                            <textarea id="payment-notes" name="notes" rows="3" maxlength="1000" class="block w-full rounded-lg border-slate-300 text-sm">{{ old('notes') }}</textarea>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="status" value="Verified" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">Verifikasi</button>
                            <button type="submit" name="status" value="Need Revision" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white hover:bg-purple-700">Perlu Revisi</button>
                            <button type="submit" name="status" value="Rejected" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700">Tolak</button>
                        </div>
                    </form>
                @endif

                @if($status === 'Verified' && trim((string) ($payment['Invoice_ID'] ?? '')) === '' && ($candidateInvoices ?? collect())->isNotEmpty())
                    <form method="POST" action="{{ route('payments.link-invoice', $payment['Payment_ID']) }}" class="space-y-4 rounded-xl border border-amber-200 bg-amber-50 p-5">
                        @csrf
                        <div>
                            <label for="verified-payment-invoice" class="mb-1.5 block text-xs font-bold text-amber-900">Rekonsiliasi ke Tagihan</label>
                            <select id="verified-payment-invoice" name="Invoice_ID" required class="block min-h-11 w-full rounded-lg border-amber-300 bg-white text-sm">
                                <option value="">Pilih tagihan</option>
                                @foreach($candidateInvoices as $candidate)
                                    <option value="{{ $candidate['Invoice_ID'] }}" @selected(old('Invoice_ID') === ($candidate['Invoice_ID'] ?? ''))>
                                        {{ $candidate['Category'] ?? 'Tagihan' }} | Rp {{ number_format((float)($candidate['Remaining_Amount'] ?? 0), 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700">Hubungkan dan Rekonsiliasi</button>
                    </form>
                @endif

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
                            <span class="text-slate-500 font-medium">Nomor Referensi Internal:</span>
                            <span class="font-mono font-bold text-slate-800 block mt-0.5">{{ $payment['Payment_ID'] ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium">No. Tagihan (Invoice):</span>
                            <span class="font-mono font-bold text-blue-600 block mt-0.5">{{ trim((string) ($payment['Invoice_ID'] ?? '')) !== '' ? $payment['Invoice_ID'] : 'Tidak terkait invoice' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium">Metode Pembayaran:</span>
                            <span class="font-bold text-slate-800 block mt-0.5">{{ $payment['Payment_Method'] ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium">Nomor Referensi:</span>
                            <span class="font-mono font-bold text-slate-800 block mt-0.5">{{ $payment['Reference_Number'] ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                @if(!empty($payment['Notes']))
                <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl space-y-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Catatan</h3>
                    <p class="text-sm text-slate-700">{{ $payment['Notes'] }}</p>
                </div>
                @endif

                <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl space-y-4" x-data="{ isModalOpen: false, imageUrl: '' }">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Bukti Pembayaran</h3>
                    <div class="mt-2">
                        @if($paymentEvidence['available'] ?? false)
                            <div class="mb-3">
                                <a href="{{ $paymentEvidence['download_url'] }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition-colors">
                                    {{ $paymentEvidence['label'] ?? 'Lihat Bukti Pembayaran' }}
                                </a>
                            </div>
                            @if($paymentEvidence['is_pdf'] ?? false)
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="mb-3 text-sm font-semibold text-slate-600">Bukti tersedia dalam format PDF.</p>
                                    <iframe src="{{ $paymentEvidence['inline_url'] }}" class="h-[60vh] w-full rounded-lg border border-slate-200"></iframe>
                                </div>
                            @else
                                <!-- Thumbnail -->
                                <button @click="imageUrl = '{{ $paymentEvidence['inline_url'] }}'; isModalOpen = true" type="button" class="block rounded-xl overflow-hidden border border-slate-200 shadow-sm hover:opacity-90 transition-opacity max-w-sm cursor-zoom-in focus:outline-none focus:ring-4 focus:ring-slate-300">
                                    <img src="{{ $paymentEvidence['inline_url'] }}" alt="Bukti Pembayaran" class="w-full h-auto object-cover">
                                </button>
                            @endif

                            <!-- Alpine Modal -->
                            @if(!($paymentEvidence['is_pdf'] ?? false))
                                <template x-teleport="body">
                                    <div x-show="isModalOpen"
                                         style="display: none;"
                                         class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/90 backdrop-blur-sm p-4"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0"
                                         x-transition:enter-end="opacity-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100"
                                         x-transition:leave-end="opacity-0">

                                        <!-- Close Button -->
                                        <button @click="isModalOpen = false" @keydown.escape.window="isModalOpen = false" class="absolute top-6 right-6 text-white/70 hover:text-white bg-slate-800/50 hover:bg-slate-800 rounded-full p-2 focus:outline-none transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>

                                        <!-- Image Container -->
                                        <div class="max-w-4xl max-h-[90vh] w-full flex items-center justify-center relative" @click.away="isModalOpen = false">
                                            <img :src="imageUrl" alt="Bukti Pembayaran Preview" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl">
                                        </div>
                                    </div>
                                </template>
                            @endif
                        @else
                            <div class="p-6 bg-slate-100 rounded-xl text-center border border-dashed border-slate-300">
                                <p class="text-sm text-slate-500 font-medium">{{ $paymentEvidence['message'] ?? 'Bukti pembayaran tidak tersedia.' }}</p>
                            </div>
                        @endif
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
