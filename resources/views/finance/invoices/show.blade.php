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
    $studentName = $invoice['student_name'] ?? \App\Helpers\UserResolverHelper::getName($invoice['Student_ID'] ?? '');
    $className = $invoice['class_name'] ?? '-';
    $batchName = $invoice['batch_name'] ?? '-';
    
    $studentFormatted = $studentName !== '-' ? $studentName : ($invoice['Company_Name'] ?? 'Pihak Terkait');
    if ($className !== '-' || $batchName !== '-') {
        $extra = [];
        if ($className !== '-') $extra[] = "Kelas: {$className}";
        if ($batchName !== '-') $extra[] = "Batch: {$batchName}";
        $studentFormatted .= ' (' . implode(' | ', $extra) . ')';
    }
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.detail-layout 
        title="Invoice #{{ $invoice['Invoice_ID'] ?? '-' }}" 
        description="Pihak Tagihan: {{ $studentFormatted }} ({{ $invoice['Student_ID'] ?? $invoice['Company_ID'] ?? '-' }})"
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
                                <h4 class="text-sm font-bold text-rose-900">Tagihan Ini Telah Jatuh Tempo (OVERDUE)</h4>
                                <p class="text-xs text-rose-700 mt-0.5">Jatuh tempo pada {{ !empty($invoice['Due_Date']) ? \Carbon\Carbon::parse($invoice['Due_Date'])->format('d M Y') : '-' }}. Mohon segera hubungi siswa/pembayar.</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- FINANCIAL SUMMARY CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-900 text-white p-5 rounded-2xl shadow-md">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Grand Total Tagihan</p>
                        <p class="text-2xl font-black mt-1 text-emerald-400">Rp {{ number_format($amount, 0, ',', '.') }}</p>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Sudah Dibayar</p>
                        <p class="text-2xl font-black text-emerald-600 mt-1">Rp {{ number_format($paid, 0, ',', '.') }}</p>
                    </div>

                    <div class="bg-rose-50 border border-rose-100 p-5 rounded-2xl">
                        <p class="text-xs font-bold text-rose-800 uppercase tracking-wider">Sisa Piutang (Belum Lunas)</p>
                        <p class="text-2xl font-black text-rose-700 mt-1">Rp {{ number_format($remaining, 0, ',', '.') }}</p>
                    </div>
                </div>

                <!-- ITEMIZED LINE ITEMS -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Rincian Komponen Tagihan (Line Items)</h3>
                    @if(count($lineItems) > 0)
                        <div class="bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th class="p-3">Deskripsi Komponen</th>
                                        <th class="p-3 text-center">Jumlah (Qty)</th>
                                        <th class="p-3 text-right">Harga Satuan</th>
                                        <th class="p-3 text-right">Total Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach($lineItems as $item)
                                        <tr>
                                            <td class="p-3 font-bold text-slate-800">{{ $item['description'] ?? '-' }}</td>
                                            <td class="p-3 text-center font-medium">{{ $item['qty'] ?? 1 }}</td>
                                            <td class="p-3 text-right font-medium">Rp {{ number_format((float)($item['unit_price'] ?? 0), 0, ',', '.') }}</td>
                                            <td class="p-3 text-right font-bold text-slate-900">Rp {{ number_format((float)($item['subtotal'] ?? 0), 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs text-slate-600">
                            Tagihan tunggal sebesar <strong>Rp {{ number_format($amount, 0, ',', '.') }}</strong> untuk kategori <strong>{{ $invoice['Category'] ?? 'Pendidikan' }}</strong>.
                        </div>
                    @endif
                </div>
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">Metadata & Audit Trail</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Dibuat Oleh</p>
                        <p class="text-sm font-bold text-slate-800 mt-1">{{ $invoice['Created_By_Name'] ?? \App\Helpers\UserResolverHelper::getName($invoice['Created_By'] ?? '') }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Pembuatan</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ !empty($invoice['Created_At']) ? \Carbon\Carbon::parse($invoice['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>
@endsection
