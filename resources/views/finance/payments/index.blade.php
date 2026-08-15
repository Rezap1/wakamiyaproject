@extends('layouts.app')
@section('header', 'Verifikasi & Kuitansi Pembayaran')
@section('content')

<x-universal.index-layout 
    title="Verifikasi Pembayaran & Kuitansi" 
    description="Tinjau, verifikasi pembayaran siswa, dan cetak kuitansi resmi PDF bertanda tangan digital."
    :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Pembayaran' => route('payments.index')]"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="payments" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('payments.index') }}" 
            refresh-url="{{ route('payments.index') }}"
            export-url="{{ route('payments.export-pdf') }}"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($payments) === 0" empty-title="Data Pembayaran Kosong" empty-description="Belum ada data verifikasi pembayaran yang tercatat.">
        <x-slot:header>
            <th class="px-6 py-4">ID Kuitansi</th>
            <th class="px-6 py-4">ID Tagihan</th>
            <th class="px-6 py-4">Siswa / Pihak Pembayar</th>
            <th class="px-6 py-4 text-center">Nominal Dibayar</th>
            <th class="px-6 py-4 text-center">Tanggal Bayar</th>
            <th class="px-6 py-4 text-center">Status Verifikasi</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($payments as $item)
            @php
                $status = $item['Status'] ?? 'Waiting Verification';
                $badgeColor = match($status) {
                    'Verified' => 'green',
                    'Rejected' => 'red',
                    'Need Revision' => 'purple',
                    'Waiting Verification' => 'yellow',
                    default => 'slate',
                };
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-mono font-bold text-slate-800 text-sm">{{ $item['Payment_ID'] ?? '' }}</td>
                <td class="px-6 py-4 font-mono font-bold text-slate-700 text-sm">{{ $item['Invoice_ID'] ?? '' }}</td>
                <td class="px-6 py-4 text-sm font-bold text-slate-800">{{ $item['Student_Display'] ?? $item['Student_ID'] ?? '-' }}</td>
                <td class="px-6 py-4 text-center font-black text-slate-800 text-sm">Rp {{ number_format((float)($item['Amount_Paid'] ?? 0), 0, ',', '.') }}</td>
                <td class="px-6 py-4 text-center">
                    @if(!empty($item['Payment_Date']))
                        <div class="text-xs font-bold text-slate-700">{{ \Carbon\Carbon::parse($item['Payment_Date'])->format('d M Y') }}</div>
                    @else
                        <span class="text-xs text-slate-400">-</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-center">
                    @php
                        $statusText = match($status) {
                            'Verified' => '✅ TERVERIFIKASI',
                            'Rejected' => '❌ DITOLAK',
                            'Need Revision' => '🟪 REVISI',
                            'Waiting Verification' => '⏳ MENUNGGU',
                            default => $status,
                        };
                    @endphp
                    <x-badge color="{{ $badgeColor }}">{{ $statusText }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        @if($status === 'Verified')
                            <a href="{{ route('payments.receipt', $item['Payment_ID']) }}" target="_blank" class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors shadow-xs flex items-center gap-1" title="Unduh Kuitansi PDF Resmi">
                                📄 PDF Kuitansi
                            </a>
                        @endif

                        @if($status == 'Waiting Verification')
                            <x-universal.action-button action="detail" url="{{ route('payments.show', $item['Payment_ID']) }}" />
                        @else
                            <x-universal.action-button action="detail" url="{{ route('payments.show', $item['Payment_ID']) }}" />
                        @endif
                        <x-universal.action-button action="delete" url="{{ route('payments.destroy', $item['Payment_ID']) }}" />
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($payments, 'links'))
                <x-universal.pagination :paginator="$payments" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>
@endsection
