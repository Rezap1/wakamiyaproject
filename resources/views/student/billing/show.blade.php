@extends('layouts.app')
@section('header', 'Invoice Detail')
@section('content')
<div class="space-y-6">
    <x-page-header title="Invoice Details" description="View and pay your invoice." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Billing' => route('student.billing.index'), 'Invoice' => '#']" />
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">{{ $invoice['Invoice_ID'] ?? '' }} | {{ $invoice['Category'] ?? '' }}</p>
                <h2 class="text-5xl font-black text-slate-800 my-4">Rp {{ number_format($invoice['Amount'] ?? 0, 0, ',', '.') }}</h2>
                <span class="inline-block px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wide bg-blue-100 text-blue-700">{{ $invoice['Status'] ?? '' }}</span>
                <p class="text-sm font-semibold text-slate-500 mt-4">Due on: {{ $invoice['Due_Date'] ?? '' }}</p>
            </div>
            
            @if(($invoice['Status'] ?? '') == 'Waiting Payment')
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Submit Payment</h3>
                <form action="{{ route('student.billing.pay', $invoice['Invoice_ID']) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 mb-4">
                        <div>
                            <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Amount Paid (Rp)</label>
                            <input type="number" name="Amount_Paid" value="{{ $invoice['Amount'] ?? 0 }}" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm" required>
                        </div>

                        <div>
                            <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nama Pengirim</label>
                            <input type="text" name="Sender_Name" placeholder="Atas Nama Rekening" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm" required>
                        </div>
                        <div>
                            <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Tanggal Transfer</label>
                            <input type="date" name="Transfer_Date" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div>
                            <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Upload Proof of Payment</label>
                            <input type="file" name="Proof_File" class="block w-full text-[13px] rounded-xl bg-white border-slate-200 text-slate-800 focus:ring-2 focus:border-emerald-500 focus:ring-emerald-500/20 px-4 py-2.5 shadow-sm" accept="image/*,.pdf">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl shadow-md transition-colors">Submit Payment for Verification</button>
                </form>
            </div>
            @endif
        </div>
        
        <div class="md:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Payment History</h3>
                <div class="space-y-4">
                    @forelse($relatedPayments as $pay)
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                            <p class="text-xs font-bold text-slate-500">{{ $pay['Payment_ID'] ?? '' }}</p>
                            <p class="text-lg font-black text-slate-800">Rp {{ number_format($pay['Amount_Paid'] ?? 0, 0, ',', '.') }}</p>
                            <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase">{{ $pay['Payment_Date'] ?? '' }}</p>
                            <div class="mt-2">
                                <span class="px-2 py-1 text-[10px] font-bold rounded bg-blue-100 text-blue-700">{{ $pay['Status'] ?? '' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic">No payments recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



