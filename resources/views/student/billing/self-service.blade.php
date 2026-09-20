@extends('layouts.app')
@section('header', 'Pembayaran Mandiri')
@section('content')
@php
    $rupiah = fn ($amount) => 'Rp' . number_format((float) $amount, 0, ',', '.');
    $educationPaymentState = $educationPaymentState ?? [];
    $isPaid = ($educationPaymentState['status'] ?? null) === 'paid';
    $canSubmit = !$isPaid
        && (float)($educationPaymentState['tuition_fee'] ?? 0) > 0
        && (float)($educationPaymentState['remaining_payable'] ?? 0) > 0;
@endphp
<div class="max-w-3xl mx-auto space-y-5 sm:space-y-6">
    <x-page-header title="Pembayaran Mandiri" description="Kirim bukti pembayaran Biaya Pendidikan untuk diverifikasi Finance." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Billing' => route('student.billing.index'), 'Pembayaran Mandiri' => '#']" />

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="education-payment-summary">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 id="education-payment-summary" class="font-black text-slate-900">Ringkasan Biaya Pendidikan</h2><p class="mt-1 text-xs text-slate-500">Pending menjadi reservasi dan belum termasuk Sudah Dibayar.</p></div>
            <span class="rounded-full px-3 py-1 text-xs font-black {{ $isPaid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $isPaid ? 'Lunas' : ((float)($educationPaymentState['verified_paid'] ?? 0) > 0 ? 'Cicilan' : 'Belum Bayar') }}</span>
        </div>
        <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-[11px] font-bold text-slate-500">Biaya Pendidikan</dt><dd class="mt-1 font-black text-slate-900">{{ $rupiah($educationPaymentState['tuition_fee'] ?? 0) }}</dd></div>
            <div class="rounded-xl bg-emerald-50 p-3"><dt class="text-[11px] font-bold text-emerald-700">Sudah Dibayar</dt><dd class="mt-1 font-black text-emerald-900">{{ $rupiah($educationPaymentState['verified_paid'] ?? 0) }}</dd></div>
            <div class="rounded-xl bg-amber-50 p-3"><dt class="text-[11px] font-bold text-amber-700">Pending / Reservasi</dt><dd class="mt-1 font-black text-amber-900">{{ $rupiah($educationPaymentState['pending_reserved'] ?? 0) }}</dd></div>
            <div class="rounded-xl bg-sky-50 p-3"><dt class="text-[11px] font-bold text-sky-700">Sisa Dapat Dibayar</dt><dd class="mt-1 font-black text-sky-900">{{ $rupiah($educationPaymentState['remaining_payable'] ?? 0) }}</dd></div>
        </dl>
    </section>

    @if($isPaid)
        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900"><h2 class="font-black">Biaya Pendidikan Anda sudah lunas.</h2><p class="mt-1 text-sm">Bayar Mandiri tidak tersedia karena sisa Biaya Pendidikan Anda Rp0.</p></section>
    @elseif((float)($educationPaymentState['tuition_fee'] ?? 0) <= 0)
        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><h2 class="font-black">Biaya Pendidikan belum ditetapkan.</h2><p class="mt-1 text-sm">Hubungi Administrator sebelum mengirim pembayaran.</p></section>
    @elseif((float)($educationPaymentState['remaining_payable'] ?? 0) <= 0)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><h2 class="font-black">Pembayaran sedang menunggu verifikasi.</h2><p class="mt-1 text-sm">Tidak ada sisa yang dapat dibayar sampai Finance memproses pembayaran pending.</p></section>
    @endif

    @if($canSubmit)

    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5 text-amber-950" aria-labelledby="payment-info-title">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.29 3.86l-7.2 12.48A1.5 1.5 0 004.39 18.6h15.22a1.5 1.5 0 001.3-2.26l-7.2-12.48a1.5 1.5 0 00-2.6 0z" /></svg>
            </div>
            <div class="min-w-0 text-sm leading-relaxed">
                <h2 id="payment-info-title" class="font-extrabold">Pembayaran menunggu verifikasi</h2>
                <p class="mt-1">Pengiriman ini bukan tagihan resmi. Status awal selalu <strong>Menunggu Verifikasi</strong> dan belum memengaruhi saldo atau laporan keuangan sampai diverifikasi oleh tim Keuangan atau Administrator Utama.</p>
            </div>
        </div>
    </section>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <form id="self-service-payment-form" action="{{ route('student.billing.self-service.pay') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <input type="hidden" name="Idempotency_Key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <fieldset class="space-y-4">
                <legend class="flex items-center gap-2 text-sm font-black uppercase tracking-wider text-slate-800">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-100 text-sky-700" aria-hidden="true">1</span>
                    Informasi Pembayaran
                </legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="amount-paid" class="mb-1.5 block text-sm font-bold text-slate-700">Nominal Pembayaran <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">wajib</span></label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-bold text-slate-500" aria-hidden="true">Rp</span>
                            <input id="amount-paid" type="number" name="Amount_Paid" value="{{ old('Amount_Paid') }}" min="0.01" max="{{ $educationPaymentState['remaining_payable'] }}" step="0.01" inputmode="decimal" class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-base font-semibold text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100" required aria-describedby="amount-help">
                        </div>
                        <p id="amount-help" class="mt-1.5 text-xs text-slate-500">Maksimum {{ $rupiah($educationPaymentState['remaining_payable']) }} sesuai sisa Biaya Pendidikan setelah reservasi.</p>
                        @error('Amount_Paid')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="payment-method" class="mb-1.5 block text-sm font-bold text-slate-700">Metode Pembayaran <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">wajib</span></label>
                        <select id="payment-method" name="Payment_Method" class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-semibold text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100" required>
                            <option value="TRANSFER">Transfer</option>
                            <option value="CASH">Tunai</option>
                        </select>
                        @error('Payment_Method')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="transfer-date" class="mb-1.5 block text-sm font-bold text-slate-700">Tanggal Pembayaran <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">wajib</span></label>
                        <input id="transfer-date" type="date" name="Transfer_Date" value="{{ old('Transfer_Date', now()->toDateString()) }}" class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-semibold text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100" required>
                        @error('Transfer_Date')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="sender-name" class="mb-1.5 block text-sm font-bold text-slate-700">Nama Pengirim <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">wajib</span></label>
                        <input id="sender-name" type="text" name="Sender_Name" value="{{ old('Sender_Name') }}" maxlength="255" autocomplete="name" class="block min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-base font-semibold text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100" required>
                        @error('Sender_Name')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4 border-t border-slate-100 pt-5">
                <legend class="flex items-center gap-2 text-sm font-black uppercase tracking-wider text-slate-800">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700" aria-hidden="true">2</span>
                    Bukti Pembayaran
                </legend>

                <div>
                    <input id="proof-file" type="file" name="Proof_File" accept="image/jpeg,image/png,application/pdf" class="sr-only" required aria-describedby="proof-help proof-selected">
                    <label for="proof-file" id="proof-upload-label" class="flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center transition hover:border-sky-400 hover:bg-sky-50 focus-within:border-sky-500 focus-within:ring-4 focus-within:ring-sky-100">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M5 20h14a2 2 0 002-2v-3a2 2 0 00-2-2h-1m-10 0H7a2 2 0 00-2 2v3a2 2 0 002 2z" /></svg>
                        </span>
                        <span class="mt-2 text-sm font-extrabold text-slate-800">Unggah bukti pembayaran</span>
                        <span class="mt-1 text-xs text-slate-500">Ketuk untuk memilih file</span>
                        <span id="proof-help" class="mt-1 text-[11px] font-medium text-slate-400">JPG • PNG • PDF • maksimum 5 MB</span>
                    </label>
                    <div id="proof-selected" class="mt-3 hidden items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-3" aria-live="polite">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="text-emerald-700" aria-hidden="true">✓</span>
                            <div class="min-w-0"><p id="proof-name" class="truncate text-sm font-bold text-slate-800"></p><p id="proof-size" class="text-xs text-slate-500"></p></div>
                        </div>
                        <button type="button" id="proof-remove" class="shrink-0 rounded-lg px-2.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-300">Hapus</button>
                    </div>
                    @error('Proof_File')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            <div class="border-t border-slate-100 pt-5">
                <button id="self-service-submit" type="submit" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:opacity-70" aria-live="polite">
                    <svg id="self-service-submit-spinner" class="hidden h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 8 0 12h4z"></path></svg>
                    <span id="self-service-submit-label">Kirim Pembayaran</span>
                </button>
                <p class="mt-2 text-center text-xs text-slate-500">Pastikan data dan bukti sudah benar sebelum dikirim.</p>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('self-service-payment-form');
        const fileInput = document.getElementById('proof-file');
        const selected = document.getElementById('proof-selected');
        const name = document.getElementById('proof-name');
        const size = document.getElementById('proof-size');
        const remove = document.getElementById('proof-remove');
        const submit = document.getElementById('self-service-submit');
        const spinner = document.getElementById('self-service-submit-spinner');
        const label = document.getElementById('self-service-submit-label');

        function formatSize(bytes) {
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        fileInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            name.textContent = file.name;
            size.textContent = formatSize(file.size);
            selected.classList.remove('hidden');
            selected.classList.add('flex');
        });

        remove.addEventListener('click', function () {
            fileInput.value = '';
            selected.classList.add('hidden');
            selected.classList.remove('flex');
            fileInput.focus();
        });

        form.addEventListener('submit', function (event) {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            spinner.classList.remove('hidden');
            label.textContent = 'Mengirim...';
        });
    });
</script>
    @endif
@endsection
