@extends('layouts.app')

@section('header')
    <h2 class="font-bold text-xl text-slate-800 leading-tight">Laporan Piutang & Tagihan Belum Lunas</h2>
@endsection

@section('content')
<div class="space-y-6">
    <x-universal.index-layout 
        title="Laporan Piutang" 
        description="Pantau seluruh sisa piutang tagihan aktif, status pembayaran parsial, dan tagihan terkurasi terlambat (OVERDUE)."
        :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Laporan' => route('reports.finance.index'), 'Laporan Piutang' => route('reports.finance.outstanding')]"
    >
        <x-slot:headerActions>
            <x-universal.multi-export route-prefix="reports.finance" :extra-params="['report_type' => 'outstanding']" />
        </x-slot:headerActions>

        <x-slot:toolbar>
            <form method="GET" action="{{ route('reports.finance.outstanding') }}" class="w-full flex flex-col md:flex-row gap-4 items-center justify-between">
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <select name="type" class="bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5" onchange="this.form.submit()">
                        <option value="">Semua Tipe Entitas</option>
                        <option value="STUDENT" {{ $type == 'STUDENT' ? 'selected' : '' }}>Siswa</option>
                        <option value="COMPANY" {{ $type == 'COMPANY' ? 'selected' : '' }}>Perusahaan</option>
                    </select>
                </div>

                <div class="bg-rose-50 border border-rose-200 px-4 py-2 rounded-xl flex items-center gap-3">
                    <span class="text-xs font-bold text-rose-800 uppercase">Total Piutang Belum Terbayar:</span>
                    <span class="text-lg font-black text-rose-600">Rp {{ number_format($total_outstanding, 0, ',', '.') }}</span>
                </div>
            </form>
        </x-slot:toolbar>

        <x-universal.data-table :empty="count($invoices) === 0" empty-title="Tidak Ada Piutang" empty-description="Seluruh tagihan telah lunas atau belum ada tagihan aktif.">
            <x-slot:header>
                <th class="px-6 py-4">Jatuh Tempo</th>
                <th class="px-6 py-4">No. Tagihan</th>
                <th class="px-6 py-4">Penerima Tagihan</th>
                <th class="px-6 py-4 text-center">Status</th>
                <th class="px-6 py-4 text-right">Total Tagihan</th>
                <th class="px-6 py-4 text-right">Sudah Dibayar</th>
                <th class="px-6 py-4 text-right">Sisa Piutang</th>
            </x-slot:header>

            @foreach($invoices as $inv)
                @php
                    $status = $inv['Status'] ?? 'Waiting Payment';
                    $remaining = (float)($inv['Remaining_Amount'] ?? 0);
                @endphp
                <tr class="hover:bg-slate-50 transition-colors {{ $status === 'OVERDUE' ? 'bg-rose-50/50' : '' }}">
                    <td class="px-6 py-4 text-sm font-bold {{ $status === 'OVERDUE' ? 'text-rose-600' : 'text-slate-800' }}">
                        {{ !empty($inv['Due_Date']) ? \Carbon\Carbon::parse($inv['Due_Date'])->format('d M Y') : '-' }}
                        @if($status === 'OVERDUE')
                            <div class="text-[10px] font-black text-rose-500 uppercase mt-0.5">⚠️ Terlambat</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 font-mono font-bold text-slate-800 text-sm">
                        {{ $inv['Invoice_ID'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-slate-800">
                        @if(($inv['Invoice_Type'] ?? '') == 'STUDENT')
                            Siswa: {{ $inv['Student_Name'] ?? ($inv['Student_ID'] ?? '-') }}
                        @elseif(($inv['Invoice_Type'] ?? '') == 'COMPANY')
                            Perusahaan: {{ $inv['Company_Name'] ?? ($inv['Company_ID'] ?? '-') }}
                        @else
                            {{ $inv['Invoice_Type'] ?? '-' }}
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($status === 'OVERDUE')
                            <span class="px-2.5 py-1 text-xs font-black rounded-lg bg-rose-500 text-white uppercase shadow-xs">⚠️ OVERDUE</span>
                        @elseif($status === 'Partial Paid')
                            <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-purple-100 text-purple-800 uppercase">🟪 PARTIAL PAID</span>
                        @else
                            <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-100 text-amber-800 uppercase">⏳ WAITING PAYMENT</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-slate-700 text-sm">
                        Rp {{ number_format((float)($inv['Amount'] ?? 0), 0, ',', '.') }}
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-emerald-600 text-sm">
                        Rp {{ number_format((float)($inv['Paid_Amount'] ?? 0), 0, ',', '.') }}
                    </td>
                    <td class="px-6 py-4 text-right font-black text-rose-600 text-sm">
                        Rp {{ number_format($remaining, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </x-universal.data-table>
    </x-universal.index-layout>
</div>
@endsection
