@extends('layouts.app')
@section('header', 'Detail Tagihan Saya')
@section('content')

@php
    $status = $invoice['Status'] ?? 'Draft';
    $amount = (float)($invoice['Amount'] ?? 0);
    $remaining = (float)($invoice['Remaining_Amount'] ?? $amount);
    $paid = (float)($invoice['Paid_Amount'] ?? 0);
@endphp

<div class="space-y-6">
    <x-page-header title="Invoice Details" description="Lihat rincian tagihan dan unggah bukti pembayaran." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Tagihan' => route('student.billing.index'), 'Detail' => '#']" />
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            @if($status === 'OVERDUE')
                <div class="bg-rose-50 border-2 border-rose-200 p-5 rounded-2xl flex items-center gap-4">
                    <span class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center font-black text-lg">⚠️</span>
                    <div>
                        <h4 class="text-sm font-black text-rose-900 uppercase">TAGIHAN INI TELAH OVERDUE (TERLAMBAT)</h4>
                        <p class="text-xs text-rose-700 mt-0.5">Jatuh tempo pada {{ !empty($invoice['Due_Date']) ? \Carbon\Carbon::parse($invoice['Due_Date'])->format('d M Y') : '-' }}. Mohon segera lakukan pembayaran sebesar <strong>Rp {{ number_format($remaining, 0, ',', '.') }}</strong>.</p>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">{{ $invoice['Invoice_ID'] ?? '' }} | {{ $invoice['Category'] ?? '' }}</p>
                <h2 class="text-4xl font-black text-slate-800 my-2">Rp {{ number_format($amount, 0, ',', '.') }}</h2>
                
                <div class="flex justify-center items-center gap-2 my-3">
                    @if($status === 'OVERDUE')
                        <span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wide bg-rose-500 text-white shadow-xs">⚠️ OVERDUE</span>
                    @elseif($status === 'Paid')
                        <span class="px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wide bg-emerald-100 text-emerald-800">✅ PAID / LUNAS</span>
                    @elseif($status === 'Partial Paid')
                        <span class="px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wide bg-purple-100 text-purple-800">🟪 PARTIAL PAID</span>
                    @elseif($status === 'Waiting Payment')
                        <span class="px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wide bg-amber-100 text-amber-800">⏳ WAITING PAYMENT</span>
                    @else
                        <span class="px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wide bg-slate-100 text-slate-700">{{ $status }}</span>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4 mt-6 pt-4 border-t border-slate-100 max-w-md mx-auto">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase">Sudah Dibayar</p>
                        <p class="text-lg font-bold text-emerald-600 mt-0.5">Rp {{ number_format($paid, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase">Sisa Tagihan</p>
                        <p class="text-lg font-bold text-rose-600 mt-0.5">Rp {{ number_format($remaining, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
            
            @if(in_array($status, ['Waiting Payment', 'Partial Paid', 'OVERDUE']))
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h3 class="font-bold text-slate-800 text-base border-b pb-2">Unggah Bukti Pembayaran</h3>
                    <form action="{{ route('student.billing.pay', $invoice['Invoice_ID']) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nominal Transfer (Rp) <span class="text-rose-500 font-black">*</span></label>
                                <input type="number" name="Amount_Paid" value="{{ $remaining }}" max="{{ $remaining }}" class="block w-full text-[13px] font-bold rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm" required>
                            </div>

                            <div>
                                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nama Pengirim <span class="text-rose-500 font-black">*</span></label>
                                <input type="text" name="Sender_Name" placeholder="Atas Nama Rekening Pengirim" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm" required>
                            </div>
                            <div>
                                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Tanggal Transfer <span class="text-rose-500 font-black">*</span></label>
                                <input type="date" name="Transfer_Date" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div>
                                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">File Bukti Transfer <span class="text-rose-500 font-black">*</span></label>
                                <input type="file" name="Proof_File" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2 shadow-sm" accept="image/*,.pdf" required>
                            </div>
                        </div>
                        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md transition-colors">
                            Kirim Bukti Pembayaran Ke Verifikasi
                        </button>
                    </form>
                </div>
            @endif
        </div>
        
        <div class="md:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="font-bold text-slate-800 mb-4 pb-2 border-b">Histori Pembayaran</h3>
                <div class="space-y-4">
                    @forelse($relatedPayments as $pay)
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                            <p class="text-xs font-mono font-bold text-slate-500">{{ $pay['Payment_ID'] ?? '' }}</p>
                            <p class="text-lg font-black text-slate-800">Rp {{ number_format($pay['Amount_Paid'] ?? 0, 0, ',', '.') }}</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">{{ !empty($pay['Payment_Date']) ? \Carbon\Carbon::parse($pay['Payment_Date'])->format('d M Y') : '-' }}</p>
                            <div class="pt-1 flex items-center justify-between">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded {{ ($pay['Status'] ?? '') === 'Verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $pay['Status'] ?? 'Waiting Verification' }}
                                </span>
                                @if(($pay['Status'] ?? '') === 'Verified')
                                    <a href="{{ route('payments.receipt', $pay['Payment_ID']) }}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">
                                        📄 Kuitansi PDF
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic">Belum ada histori pembayaran.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
