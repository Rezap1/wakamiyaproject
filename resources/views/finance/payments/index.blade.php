@extends('layouts.app')
@section('header', 'Verifikasi Pembayaran')
@section('content')

<x-universal.index-layout 
    title="Verifikasi Pembayaran" 
    description="Tinjau dan verifikasi pembayaran siswa."
    :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Pembayaran' => route('payments.index')]"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="payments" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('payments.index') }}" 
            refresh-url="{{ route('payments.index') }}"
            export-url="#"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($payments) === 0" empty-title="Data Pembayaran Kosong" empty-description="Belum ada data verifikasi pembayaran.">
        <x-slot:header>
            <th class="px-6 py-4">ID Kuitansi</th>
            <th class="px-6 py-4">ID Tagihan</th>
            <th class="px-6 py-4">ID Siswa</th>
            <th class="px-6 py-4 text-center">Nominal Dibayar</th>
            <th class="px-6 py-4">Tanggal Bayar</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($payments as $item)
            @php
                $status = $item['Status'] ?? 'Waiting Verification';
                $badgeColor = match($status) {
                    'Verified' => 'green',
                    'Rejected' => 'red',
                    'Waiting Verification' => 'yellow',
                    default => 'slate',
                };
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Payment_ID'] ?? '' }}</td>
                <td class="px-6 py-4">{{ $item['Invoice_ID'] ?? '' }}</td>
                <td class="px-6 py-4">{{ $item['Student_ID'] ?? '' }}</td>
                <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Amount_Paid'] ?? 0, 0, ',', '.') }}</td>
                <td class="px-6 py-4">
                    @if(!empty($item['Payment_Date']))
                        <div class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($item['Payment_Date'])->format('d M Y') }}</div>
                    @else
                        -
                    @endif
                </td>
                <td class="px-6 py-4 text-center">
                    @php
                        $statusText = match($status) {
                            'Verified' => 'Terverifikasi',
                            'Rejected' => 'Ditolak',
                            'Waiting Verification' => 'Menunggu Verifikasi',
                            default => $status,
                        };
                    @endphp
                    <x-badge color="{{ $badgeColor }}">{{ $statusText }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        @if($status == 'Waiting Verification')
                            <form action="{{ route('payments.verify', $item['Payment_ID']) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="Status" value="Verified">
                                <button type="submit" class="px-2 py-1 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-colors flex items-center justify-center gap-1 shadow-sm" title="Verifikasi">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Verifikasi
                                </button>
                            </form>
                            <form action="{{ route('payments.verify', $item['Payment_ID']) }}" method="POST" class="inline" onsubmit="return confirm('Tolak pembayaran ini?');">
                                @csrf
                                <button type="submit" name="Status" value="Rejected" class="px-2 py-1 bg-red-50 text-red-600 border border-red-200 rounded-lg text-xs font-bold hover:bg-red-100 transition-colors flex items-center justify-center gap-1 shadow-sm" title="Tolak">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    Tolak
                                </button>
                            </form>
                        @endif
                        <x-universal.action-button action="detail" url="{{ route('payments.show', $item['Payment_ID']) }}" />
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



