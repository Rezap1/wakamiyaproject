@extends('layouts.app')
@section('header', 'Student Billing')
@section('content')

<div class="space-y-6">
    <x-page-header title="My Billing & Invoices" description="Manage and review your invoices, payment history, and overdue statements." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Billing' => '#']" />
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-6 shadow-md flex justify-between items-center">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Active Unpaid Bills</p>
                <h3 class="text-3xl font-black text-white mt-1">
                    Rp {{ number_format(collect($myInvoices)->whereIn('Status', ['Waiting Payment', 'Partial Paid', 'OVERDUE'])->sum('Remaining_Amount'), 0, ',', '.') }}
                </h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center font-bold text-xl">💳</div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex justify-between items-center">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Official Payment Account</p>
                <p class="text-xs text-slate-500 font-medium">{{ $bank['name'] ?? 'BANK BCA' }}</p>
                <p class="text-lg font-black text-slate-800 tracking-wider mt-0.5" id="accountNumber">{{ $bank['account_number'] ?? '888-999-777' }}</p>
                <p class="text-[11px] font-bold text-slate-400">a.n. {{ $bank['account_holder'] ?? 'PT WAKAMIYA INDONESIA' }}</p>
            </div>
            <button onclick="copyAccountNumber()" class="p-3 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white transition-colors cursor-pointer border border-emerald-100 shadow-xs flex flex-col items-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                <span class="text-[9px] font-bold uppercase mt-1" id="copyText">Copy</span>
            </button>
        </div>
    </div>

    <script>
        function copyAccountNumber() {
            var accountNumber = document.getElementById("accountNumber").innerText;
            navigator.clipboard.writeText(accountNumber).then(function() {
                var copyText = document.getElementById("copyText");
                copyText.innerText = "Copied!";
                setTimeout(function() {
                    copyText.innerText = "Copy";
                }, 2000);
            });
        }
    </script>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Daftar Tagihan Saya</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">ID Tagihan</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4 text-center">Jatuh Tempo</th>
                        <th class="px-6 py-4 text-center">Total Tagihan</th>
                        <th class="px-6 py-4 text-center">Sisa Tagihan</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($myInvoices as $item)
                        @php
                            $status = $item['Status'] ?? 'Draft';
                            $remaining = (float)($item['Remaining_Amount'] ?? ($item['Amount'] ?? 0));
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors {{ $status === 'OVERDUE' ? 'bg-rose-50/50' : '' }}">
                            <td class="px-6 py-4 font-mono font-bold text-slate-800">{{ $item['Invoice_ID'] ?? '' }}</td>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Category'] ?? '' }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs font-bold {{ $status === 'OVERDUE' ? 'text-rose-600' : 'text-slate-700' }}">
                                    {{ !empty($item['Due_Date']) ? \Carbon\Carbon::parse($item['Due_Date'])->format('d M Y') : '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Amount'] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center font-bold {{ $remaining > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                Rp {{ number_format($remaining, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($status === 'OVERDUE')
                                    <span class="px-2.5 py-1 text-[11px] font-black rounded-lg bg-rose-500 text-white uppercase shadow-xs">⚠️ OVERDUE</span>
                                @elseif($status === 'Paid')
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-emerald-100 text-emerald-800 uppercase">✅ PAID</span>
                                @elseif($status === 'Partial Paid')
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-purple-100 text-purple-800 uppercase">🟪 PARTIAL PAID</span>
                                @elseif($status === 'Waiting Payment')
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-amber-100 text-amber-800 uppercase">⏳ WAITING PAYMENT</span>
                                @elseif($status === 'Cancelled')
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-200 text-slate-700 uppercase">❌ CANCELLED</span>
                                @else
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-100 text-slate-600 uppercase">📝 DRAFT</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('student.billing.show', $item['Invoice_ID']) }}" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-xl text-xs transition-colors shadow-xs">
                                    Detail / Bayar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-slate-400">Tidak ada data tagihan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
