@extends('layouts.app')
@section('header', 'My Billing & Finance')
@section('content')
<div class="space-y-6">
    <x-page-header title="My Billing" description="View your outstanding bills and payment history." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Billing' => '#']" />
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Biaya Pendidikan (Pokok)</h4>
            <p class="text-3xl font-black text-slate-800 mt-2">Rp {{ number_format($biayaBelajar ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Dibayar</h4>
            <p class="text-3xl font-black text-emerald-600 mt-2">Rp {{ number_format($totalPaid ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sisa Tagihan Pokok</h4>
            <p class="text-3xl font-black text-rose-600 mt-2">Rp {{ number_format($sisaTagihan ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-center">
            <div class="flex justify-between items-end mb-2">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Progress Pembayaran</h4>
                <span class="text-xl font-black text-blue-600">{{ $progress ?? 0 }}%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2.5">
                <div class="bg-emerald-600 h-2.5 rounded-full transition-all duration-500" style="width: {{ $progress ?? 0 }}%"></div>
            </div>
        </div>
    </div>
    
    @if(isset($categoryBreakdown) && count($categoryBreakdown) > 0)
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
        <h4 class="text-sm font-bold text-slate-800 mb-4">Rincian Pembayaran per Kategori</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($categoryBreakdown as $catName => $catData)
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ $catName }}</p>
                <p class="text-lg font-black text-slate-800 mt-1">Rp {{ number_format($catData['total_paid'] ?? 0, 0, ',', '.') }} <span class="text-[10px] text-slate-400 font-medium">/ Rp {{ number_format($catData['total_billed'] ?? 0, 0, ',', '.') }}</span></p>
                @if(($catData['outstanding'] ?? 0) > 0)
                <p class="text-xs font-bold text-rose-500 mt-1">Tunggakan: Rp {{ number_format($catData['outstanding'], 0, ',', '.') }}</p>
                @else
                <p class="text-xs font-bold text-emerald-500 mt-1">Lunas</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Payment Information Card -->
    <div class="relative rounded-2xl shadow-md border border-[#00A39D]/30 mb-6 overflow-hidden bg-[#00A39D]">
        <!-- Decorative Background Elements -->
        <div class="absolute inset-0 bg-cover bg-center opacity-30 mix-blend-overlay" style="background-image: url('https://images.unsplash.com/photo-1490806843957-31f4c9a91c65?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-[#00A39D] via-[#00A39D]/90 to-[#00A39D]/70"></div>
        <div class="absolute -top-24 -right-24 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-black opacity-10 rounded-full blur-3xl"></div>
        
        <div class="absolute top-4 right-6 opacity-20">
            <svg class="w-48 h-48 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        </div>
        
        <div class="relative z-10 flex flex-col md:flex-row gap-8 items-center justify-between p-6">
            <div class="w-full md:w-1/3 text-white">
                <h4 class="text-sm font-black text-white/90 uppercase tracking-widest mb-1">Make all checks payable to</h4>
                <p class="text-xs text-white/70 font-bold mb-4">振込先 (Transfer Destination)</p>
                <!-- Simulated White BSI Logo -->
                <div class="flex items-center gap-3">
                    <div class="leading-none flex items-start">
                        <span class="text-6xl font-black text-white tracking-tighter" style="font-family: Arial, sans-serif;">BSI</span>
                        <svg class="w-6 h-6 text-[#F9A825] ml-1 mt-1 fill-current" viewBox="0 0 24 24"><path d="M12 2l2.4 7.6H22l-6.2 4.8 2.3 7.6-6.1-4.7-6.1 4.7 2.3-7.6-6.2-4.8h7.6z"/></svg>
                    </div>
                    <div class="leading-tight border-l-2 border-white/30 pl-3">
                        <span class="block text-sm font-bold text-white tracking-widest uppercase">Bank Syariah</span>
                        <span class="block text-sm font-bold text-white tracking-widest uppercase">Indonesia</span>
                    </div>
                </div>
            </div>

            <div class="w-full md:w-2/3 bg-white rounded-xl p-5 shadow-lg border border-white/20 relative">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-8">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Bank Name / 銀行名</p>
                        <p class="text-sm font-black text-slate-800">BANK SYARIAH INDONESIA (BSI)</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">SWIFT Code</p>
                        <p class="text-sm font-black text-slate-800 tracking-widest">BSMDIDJAXXX</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Branch / 支店</p>
                        <p class="text-sm font-black text-slate-800">CIANJUR CIPANAS 2</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Account Name / 口座名義</p>
                        <p class="text-sm font-black text-slate-800">PT WAKAMIYA MANDIRI SEJAHTERA</p>
                    </div>
                    <div class="sm:col-span-2 mt-2 p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between group transition-all hover:border-[#00A39D] hover:shadow-md">
                        <div>
                            <p class="text-[10px] uppercase font-bold text-[#00A39D] tracking-wider mb-1">Account Number / 口座番号</p>
                            <p class="text-2xl font-black text-slate-800 tracking-widest" id="accountNumber">7343551023</p>
                        </div>
                        <button onclick="copyAccountNumber()" class="flex flex-col items-center justify-center p-2 rounded-lg bg-teal-50 text-[#00A39D] hover:bg-[#00A39D] hover:text-white transition-colors cursor-pointer border border-teal-100 shadow-sm">
                            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            <span class="text-[9px] font-bold uppercase tracking-wider" id="copyText">Copy</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyAccountNumber() {
            var accountNumber = document.getElementById("accountNumber").innerText;
            navigator.clipboard.writeText(accountNumber).then(function() {
                var copyText = document.getElementById("copyText");
                copyText.innerText = "Copied!";
                copyText.classList.add("text-emerald-700");
                setTimeout(function() {
                    copyText.innerText = "Copy";
                    copyText.classList.remove("text-emerald-700");
                }, 2000);
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-50"><h3 class="font-bold text-slate-800">My Invoices</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr><th class="px-6 py-4">Invoice ID</th><th class="px-6 py-4">Category</th><th class="px-6 py-4">Due Date</th><th class="px-6 py-4 text-center">Amount</th><th class="px-6 py-4 text-center">Status</th><th class="px-6 py-4 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($myInvoices as $item)
                        <tr>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Invoice_ID'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $item['Category'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $item['Due_Date'] ?? '' }}</td>
                            <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Amount'] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $status = $item['Status'] ?? 'Draft';
                                    $bg = $status == 'Paid' ? 'bg-emerald-100 text-emerald-700' : ($status == 'Waiting Payment' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700');
                                @endphp
                                <span class="{{ $bg }} px-2 py-1 text-[11px] font-bold rounded-lg">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('student.billing.show', $item['Invoice_ID']) }}" class="px-3 py-1 bg-blue-50 text-blue-600 font-bold rounded-lg hover:bg-blue-100 text-xs">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No invoices found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection



