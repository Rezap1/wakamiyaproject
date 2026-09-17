@extends('layouts.app')
@section('header', 'Detail Pembayaran Pendidikan')

@section('content')
@php
    $rupiah = fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
    $date = fn ($value) => \App\Support\Presentation\IndonesianPresentation::date($value, 'j F Y', '-');
    $dateTime = fn ($value) => \App\Support\Presentation\IndonesianPresentation::date($value, 'j F Y, H.i', '-');
    $statusClasses = [
        'fee_unset' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'unpaid' => 'bg-rose-100 text-rose-700 ring-rose-200',
        'partial' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'paid' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    ];
@endphp

<div class="min-w-0 space-y-6">
    <x-page-header
        title="Detail Pembayaran Pendidikan"
        description="Riwayat pembayaran pendidikan siswa dari sumber payment canonical."
        :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Pembayaran Pendidikan' => route('finance.education-payments.index'), 'Detail' => '#']"
    />

    <section class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-1">
            <p class="text-xs font-bold uppercase tracking-wider text-sky-600">Identitas Siswa</p>
            <h2 class="mt-2 break-words text-xl font-black text-slate-900">{{ $student['student_name'] }}</h2>
            <dl class="mt-5 space-y-3 text-sm">
                <div><dt class="text-xs font-bold uppercase text-slate-400">Student ID / NIS</dt><dd class="mt-1 font-semibold text-slate-800">{{ $student['student_id'] }} / {{ $student['student_number'] }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-400">Kelas</dt><dd class="mt-1 font-semibold text-slate-800">{{ $student['class_name'] }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-400">Program</dt><dd class="mt-1 font-semibold text-slate-800">{{ $student['program_name'] }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-400">Batch</dt><dd class="mt-1 font-semibold text-slate-800">{{ $student['batch_name'] }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-bold uppercase tracking-wider text-sky-600">Ringkasan Biaya Pendidikan</p><p class="mt-1 text-sm text-slate-500">Status keseluruhan, terpisah dari status setiap invoice.</p></div>
                <span class="rounded-full px-3 py-1.5 text-xs font-extrabold ring-1 {{ $statusClasses[$student['status']] }}">{{ $student['status_label'] }}</span>
            </div>
            <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs font-bold text-slate-500">Biaya Pendidikan</dt><dd class="mt-2 break-words text-lg font-black text-slate-900">{{ $rupiah($student['education_fee']) }}</dd></div>
                <div class="rounded-xl bg-emerald-50 p-4"><dt class="text-xs font-bold text-emerald-700">Total Sudah Dibayar</dt><dd class="mt-2 break-words text-lg font-black text-emerald-800">{{ $rupiah($student['paid']) }}</dd></div>
                <div class="rounded-xl bg-rose-50 p-4"><dt class="text-xs font-bold text-rose-700">Sisa</dt><dd class="mt-2 break-words text-lg font-black text-rose-800">{{ $rupiah($student['remaining']) }}</dd></div>
            </dl>
            @if($student['excess'] > 0)
                <p class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm font-semibold text-sky-800">Kelebihan pembayaran terverifikasi: {{ $rupiah($student['excess']) }}</p>
            @endif
        </div>
    </section>

    <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6"><h2 class="text-lg font-black text-slate-900">Riwayat Pembayaran</h2><p class="mt-1 text-sm text-slate-500">Pending dan rejected ditampilkan untuk audit, tetapi tidak masuk total terbayar.</p></div>

        <div class="divide-y divide-slate-100 lg:hidden">
            @forelse($history as $payment)
                <article class="min-w-0 space-y-4 p-4">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs text-slate-400">Sumber Pembayaran</p><p class="mt-1 font-black text-slate-900">{{ $payment['source_label'] }}</p></div><p class="shrink-0 font-black text-slate-900">{{ $rupiah($payment['amount']) }}</p></div>
                    <dl class="grid grid-cols-2 gap-3 text-xs">
                        <div><dt class="text-slate-400">Tanggal Pembayaran</dt><dd class="mt-1 font-semibold text-slate-700">{{ $date($payment['payment_date']) }}</dd></div>
                        <div><dt class="text-slate-400">Metode Pembayaran</dt><dd class="mt-1 font-semibold text-slate-700">{{ $payment['method_label'] }}</dd></div>
                        <div><dt class="text-slate-400">Status Pembayaran</dt><dd class="mt-1 font-semibold text-slate-700">{{ $payment['status_label'] }}</dd></div>
                        <div><dt class="text-slate-400">Tanggal Verifikasi</dt><dd class="mt-1 font-semibold text-slate-700">{{ $dateTime($payment['verified_at']) }}</dd></div>
                        <div class="col-span-2"><dt class="text-slate-400">Payment ID</dt><dd class="mt-1 break-all font-semibold text-slate-700">{{ $payment['payment_id'] }}</dd></div>
                        <div class="col-span-2"><dt class="text-slate-400">Referensi</dt><dd class="mt-1 break-all font-semibold text-slate-700">{{ $payment['reference'] }}</dd></div>
                        <div><dt class="text-slate-400">Invoice Terkait</dt><dd class="mt-1 break-all font-semibold text-slate-700">{{ $payment['invoice_id'] ?? '-' }}</dd></div>
                        <div><dt class="text-slate-400">Nominal Invoice</dt><dd class="mt-1 font-semibold text-slate-700">{{ $payment['invoice_id'] ? $rupiah($payment['invoice_amount']) : '-' }}</dd></div>
                        <div class="col-span-2"><dt class="text-slate-400">Status Invoice</dt><dd class="mt-1 font-semibold text-slate-700">{{ $payment['invoice_status_label'] }}</dd></div>
                        <div class="col-span-2"><dt class="text-slate-400">Catatan</dt><dd class="mt-1 break-words font-semibold text-slate-700">{{ $payment['notes'] }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="p-8"><x-empty-state icon="cash" title="Belum ada riwayat pembayaran pendidikan" message="Pembayaran akan muncul setelah dikirim melalui Bayar Mandiri atau tagihan pendidikan." /></div>
            @endforelse
        </div>

        <div class="hidden lg:block">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-white text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="w-[13%] px-4 py-4">Tanggal Pembayaran</th><th class="w-[15%] px-4 py-4">Sumber Pembayaran</th><th class="w-[13%] px-4 py-4">Metode Pembayaran</th><th class="w-[14%] px-4 py-4 text-right">Jumlah Pembayaran</th><th class="w-[14%] px-4 py-4">Status Pembayaran</th><th class="w-[19%] px-4 py-4">Invoice Terkait</th><th class="w-[12%] px-4 py-4">Payment ID / Referensi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($history as $payment)
                        <tr class="align-top hover:bg-slate-50"><td class="px-4 py-4 font-semibold text-slate-700">{{ $date($payment['payment_date']) }}</td><td class="px-4 py-4 font-bold text-slate-900">{{ $payment['source_label'] }}</td><td class="px-4 py-4 text-slate-700">{{ $payment['method_label'] }}</td><td class="px-4 py-4 text-right font-black text-slate-900">{{ $rupiah($payment['amount']) }}</td><td class="px-4 py-4"><p class="font-bold text-slate-800">{{ $payment['status_label'] }}</p><p class="mt-2 text-[11px] font-bold uppercase text-slate-400">Tanggal Verifikasi</p><p class="mt-1 text-xs text-slate-500">{{ $dateTime($payment['verified_at']) }}</p></td><td class="px-4 py-4"><p class="break-all font-semibold text-slate-800">{{ $payment['invoice_id'] ?? '-' }}</p><p class="mt-2 text-[11px] font-bold uppercase text-slate-400">Nominal Invoice</p><p class="mt-1 text-xs text-slate-500">{{ $payment['invoice_id'] ? $rupiah($payment['invoice_amount']) : '-' }}</p><p class="mt-2 text-[11px] font-bold uppercase text-slate-400">Status Invoice</p><p class="mt-1 text-xs text-slate-500">{{ $payment['invoice_status_label'] }}</p></td><td class="px-4 py-4"><p class="break-all font-semibold text-slate-700">{{ $payment['payment_id'] }}</p><p class="mt-1 break-all text-xs text-slate-500">{{ $payment['reference'] }}</p><p class="mt-2 text-[11px] font-bold uppercase text-slate-400">Catatan</p><p class="mt-1 break-words text-xs text-slate-500">{{ $payment['notes'] }}</p></td></tr>
                    @empty
                        <tr><td colspan="7" class="p-8"><x-empty-state icon="cash" title="Belum ada riwayat pembayaran pendidikan" message="Pembayaran akan muncul setelah dikirim melalui Bayar Mandiri atau tagihan pendidikan." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
