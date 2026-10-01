@extends('layouts.app')
@section('header', 'Detail Notifikasi')
@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Detail Notifikasi" description="Informasi pemberitahuan untuk akun Anda." :breadcrumbs="['Dasbor' => route('dashboard'), 'Notifikasi' => route('notifications.index'), 'Detail' => '#']" />

    @if($billingContext)
        <article class="min-w-0 overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm">
            <header class="bg-gradient-to-br from-emerald-50 via-white to-sky-50 px-5 py-6 sm:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-700">Tagihan</span>
                        <h1 class="mt-2 break-words text-xl font-black uppercase leading-tight text-slate-900 sm:text-2xl">{{ $billingContext['title'] }}</h1>
                    </div>
                    <time class="shrink-0 text-xs font-semibold text-slate-500">{{ $billingContext['created_at'] }}</time>
                </div>
                @if(!empty($billingContext['student_name']))
                    <p class="mt-5 text-sm text-slate-700">Halo, <strong>{{ $billingContext['student_name'] }}</strong></p>
                @endif
            </header>

            <div class="space-y-6 px-5 py-6 sm:px-8 sm:py-8">
                @if(($billingContext['status'] ?? '') === 'unavailable')
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
                        Tagihan {{ $billingContext['invoice_id'] }} tidak lagi tersedia. Tidak ada tindakan pembayaran yang dapat dilakukan.
                    </div>
                @else
                    <section aria-labelledby="billing-information-heading">
                        <h2 id="billing-information-heading" class="text-sm font-black text-slate-900">Informasi Tagihan</h2>
                        <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="min-w-0 rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">No. Tagihan</dt>
                                <dd class="mt-1 break-all font-mono text-sm font-black text-slate-900">{{ $billingContext['invoice_id'] }}</dd>
                            </div>
                            <div class="min-w-0 rounded-2xl bg-slate-50 p-4">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Tagihan</dt>
                                <dd class="mt-1 break-words text-xl font-black text-slate-900">Rp{{ number_format($billingContext['total'], 0, ',', '.') }}</dd>
                            </div>
                            <div class="min-w-0 rounded-2xl bg-emerald-50 p-4">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Sudah Dibayar</dt>
                                <dd class="mt-1 break-words text-xl font-black text-emerald-900">Rp{{ number_format($billingContext['paid'], 0, ',', '.') }}</dd>
                            </div>
                            <div class="min-w-0 rounded-2xl bg-amber-50 p-4">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Sisa Pembayaran</dt>
                                <dd class="mt-1 break-words text-xl font-black text-amber-900">Rp{{ number_format($billingContext['remaining'], 0, ',', '.') }}</dd>
                            </div>
                            <div class="min-w-0 rounded-2xl bg-sky-50 p-4">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-sky-700">Jatuh Tempo</dt>
                                <dd class="mt-1 break-words text-base font-black text-sky-900">{{ $billingContext['due_date'] }}</dd>
                            </div>
                        </dl>
                    </section>

                    @if($billingContext['overdue'])
                        <p class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">Tagihan telah melewati jatuh tempo.</p>
                    @endif

                    @if($billingContext['actionable'])
                        <p class="text-sm leading-6 text-slate-700">{{ $billingContext['instruction'] }}</p>
                        <form action="{{ route('notifications.read', $notification['Notification_ID']) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-black text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto">Bayar Sekarang</button>
                        </form>
                    @elseif(($billingContext['status'] ?? '') === 'paid')
                        <p class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">Lunas — tagihan ini telah lunas.</p>
                    @elseif(in_array(($billingContext['status'] ?? ''), ['cancelled', 'void'], true))
                        <p class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-700">Tagihan ini telah dibatalkan dan tidak dapat dibayar.</p>
                    @else
                        <p class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-700">Pembayaran tidak tersedia untuk status tagihan saat ini.</p>
                    @endif
                @endif
            </div>
        </article>
    @else
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{ $notification['Priority'] ?? 'Normal' }}</span>
                <span class="rounded bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700">{{ $notification['Notification_Type'] ?? 'Sistem' }}</span>
                <span class="ml-auto text-xs text-slate-400">{{ \App\Helpers\DateHelper::format($notification['Created_At'] ?? null, 'j F Y • H:i') }} WIB</span>
            </div>
            <h1 class="mb-4 break-words text-2xl font-black text-slate-800">{{ $notification['Title'] ?? 'Notifikasi' }}</h1>
            <div class="whitespace-pre-line break-words text-sm leading-7 text-slate-600">{{ $notification['Message'] ?? '-' }}</div>

            @if(!empty($notification['Link'] ?? $notification['Action_URL'] ?? null))
                <form action="{{ route('notifications.read', $notification['Notification_ID']) }}" method="POST" class="mt-6">
                    @csrf
                    <button type="submit" class="inline-flex min-h-12 items-center rounded-xl bg-emerald-600 px-6 py-3 font-bold text-white shadow-sm hover:bg-emerald-700">Buka Tautan Aksi</button>
                </form>
            @endif
        </article>
    @endif

    <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-5">
        <form action="{{ route('notifications.archive', $notification['Notification_ID']) }}" method="POST">
            @csrf
            <button type="submit" class="min-h-11 rounded-lg bg-amber-50 px-4 py-2 text-sm font-bold text-amber-700 hover:bg-amber-100">Arsip</button>
        </form>
        <form action="{{ route('notifications.destroy', $notification['Notification_ID']) }}" method="POST" onsubmit="return confirm('Hapus notifikasi ini?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="min-h-11 rounded-lg bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100">Hapus</button>
        </form>
    </div>
</div>
@endsection
