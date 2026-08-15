@extends('layouts.app')
@section('header', 'Detail Tagihan & Invoice')
@section('content')

@php
    $status = $invoice['Display_Status'] ?? ($invoice['Status'] ?? 'Draft');
    $badgeColor = match($status) {
        'Paid' => 'green',
        'Partial Paid' => 'purple',
        'Waiting Payment' => 'yellow',
        'OVERDUE' => 'red',
        'Cancelled' => 'slate',
        default => 'slate',
    };
    $amount = (float)($invoice['Grand_Total'] ?? $invoice['Amount'] ?? 0);
    $remaining = (float)($invoice['Remaining_Amount'] ?? $amount);
    $paid = (float)($invoice['Paid_Amount'] ?? 0);
    $lineItems = $invoice['Parsed_Line_Items'] ?? [];
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="Invoice #{{ $invoice['Invoice_ID'] ?? '-' }}" 
        description="Target Tagihan: {{ $invoice['Student_ID'] ?? ($invoice['Company_ID'] ?? 'Pihak Terkait') }}"
        status="{{ $status }}"
        badgeColor="{{ $badgeColor }}"
        :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Tagihan' => route('invoices.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            <a href="{{ route('invoices.pdf', $invoice['Invoice_ID']) }}" target="_blank" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-md transition-colors flex items-center gap-1.5">
                📄 PDF Invoice Resmi
            </a>

            @if($status === 'Draft')
                <form action="{{ route('invoices.publish', $invoice['Invoice_ID']) }}" method="POST" onsubmit="return confirm('Terbitkan tagihan ini?');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        🚀 Publish Tagihan
                    </button>
                </form>
                <x-universal.action-button action="edit" url="{{ route('invoices.edit', $invoice['Invoice_ID']) }}" />
            @endif

            @if(in_array($status, ['Waiting Payment', 'Partial Paid', 'OVERDUE']))
                <form action="{{ route('invoices.cancel', $invoice['Invoice_ID']) }}" method="POST" onsubmit="return confirm('Batalkan tagihan ini secara permanen?');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-md transition-colors">
                        🚫 Batalkan Tagihan
                    </button>
                </form>
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <!-- OVERDUE WARNING BANNER -->
                @if($status === 'OVERDUE')
                    <div class="bg-rose-50 border-2 border-rose-200 p-5 rounded-2xl flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center font-black text-lg">⚠️</span>
                            <div>
                                <h4 class="text-sm font-black text-rose-900 uppercase tracking-wider">TAGIHAN INI TELAH TERLAMBAT (OVERDUE)</h4>
                                <p class="text-xs text-rose-700 mt-0.5">Tanggal jatuh tempo {{ !empty($invoice['Due_Date']) ? \Carbon\Carbon::parse($invoice['Due_Date'])->format('d M Y') : '-' }} telah terlewati. Sisa piutang sebesar <strong>Rp {{ number_format($remaining, 0, ',', '.') }}</strong> belum dilunasi.</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- FINANCIAL SUMMARY CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-900 text-white p-5 rounded-2xl shadow-md">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Grand Total Tagihan</p>
                        <p class="text-2xl font-black mt-1">Rp {{ number_format($amount, 0, ',', '.') }}</p>
                    </div>

                    <div class="bg-emerald-50 border border-emerald-100 p-5 rounded-2xl">
                        <p class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Sudah Terbayar (Verified)</p>
                        <p class="text-2xl font-black text-emerald-700 mt-1">Rp {{ number_format($paid, 0, ',', '.') }}</p>
                    </div>

                    <div class="p-5 rounded-2xl border {{ $remaining > 0 ? 'bg-rose-50 border-rose-100' : 'bg-slate-50 border-slate-200' }}">
                        <p class="text-xs font-bold uppercase tracking-wider {{ $remaining > 0 ? 'text-rose-800' : 'text-slate-600' }}">Sisa Piutang Terkini</p>
                        <p class="text-2xl font-black mt-1 {{ $remaining > 0 ? 'text-rose-700' : 'text-slate-800' }}">
                            Rp {{ number_format($remaining, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                <!-- ITEMIZED LINE ITEMS TABLE -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Rincian Komponen Item Tagihan (Itemized Line Items)</h3>
                    <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase">
                                    <th class="p-3 text-center w-10">#</th>
                                    <th class="p-3">Deskripsi Komponen Item</th>
                                    <th class="p-3 text-center w-16">Qty</th>
                                    <th class="p-3 text-right">Harga Satuan</th>
                                    <th class="p-3 text-right">Potongan/Diskon</th>
                                    <th class="p-3 text-right">Pajak</th>
                                    <th class="p-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @forelse($lineItems as $idx => $item)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="p-3 text-center font-bold text-slate-400">{{ $idx + 1 }}</td>
                                        <td class="p-3 font-bold text-slate-800">{{ $item['description'] ?? '-' }}</td>
                                        <td class="p-3 text-center font-bold text-slate-700">{{ $item['qty'] ?? 1 }}</td>
                                        <td class="p-3 text-right font-medium text-slate-700">Rp {{ number_format((float)($item['unit_price'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="p-3 text-right text-emerald-600 font-bold">
                                            {{ ($item['discount'] ?? 0) > 0 ? '- Rp ' . number_format((float)$item['discount'], 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="p-3 text-right text-rose-600 font-bold">
                                            {{ ($item['tax'] ?? 0) > 0 ? '+ Rp ' . number_format((float)$item['tax'], 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="p-3 text-right font-black text-slate-900">
                                            Rp {{ number_format((float)($item['subtotal'] ?? 0), 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-slate-400 italic">Tidak ada rincian item.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-slate-50 border-t border-slate-200 text-xs">
                                @if(($invoice['Total_Discount'] ?? 0) > 0)
                                    <tr>
                                        <td colspan="6" class="p-2 text-right font-bold text-slate-500">Total Diskon / Potongan:</td>
                                        <td class="p-2 text-right font-bold text-emerald-600">- Rp {{ number_format((float)$invoice['Total_Discount'], 0, ',', '.') }}</td>
                                    </tr>
                                @endif
                                @if(($invoice['Total_Tax'] ?? 0) > 0)
                                    <tr>
                                        <td colspan="6" class="p-2 text-right font-bold text-slate-500">Total Pajak:</td>
                                        <td class="p-2 text-right font-bold text-rose-600">+ Rp {{ number_format((float)$invoice['Total_Tax'], 0, ',', '.') }}</td>
                                    </tr>
                                @endif
                                <tr class="font-black text-slate-900 text-sm">
                                    <td colspan="6" class="p-3 text-right uppercase">Grand Total Tagihan:</td>
                                    <td class="p-3 text-right text-blue-600">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- HEADER INFO -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Informasi Metadata Tagihan</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Kategori Tagihan</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $invoice['Category'] ?? '-' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tipe Invoice</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $invoice['Invoice_Type'] ?? 'STUDENT' }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Jatuh Tempo</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">
                                {{ !empty($invoice['Due_Date']) ? \Carbon\Carbon::parse($invoice['Due_Date'])->format('d M Y') : '-' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">System Metadata & Audit Trail</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">ID Tagihan (Primary Key)</p>
                        <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $invoice['Invoice_ID'] }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Status Dinamis</p>
                        <p class="text-sm font-bold text-slate-800 mt-1 uppercase">{{ $status }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
