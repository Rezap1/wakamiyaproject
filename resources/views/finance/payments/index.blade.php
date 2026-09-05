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

    @if(($paymentGroups ?? collect())->count() > 0)
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
            @foreach($paymentGroups as $group)
                @php
                    $groupBadgeClasses = match($group['id']) {
                        'Verified' => 'bg-emerald-50 text-emerald-700',
                        'Rejected' => 'bg-rose-50 text-rose-700',
                        'Need Revision' => 'bg-purple-50 text-purple-700',
                        'Waiting Verification' => 'bg-amber-50 text-amber-700',
                        default => 'bg-slate-100 text-slate-700',
                    };
                @endphp
                <details class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden group">
                    <summary class="cursor-pointer list-none p-4 flex flex-col gap-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-sm font-black text-slate-800">{{ $group['title'] }}</h3>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $group['total'] }} pembayaran</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                            <span class="px-2.5 py-1 rounded-lg {{ $groupBadgeClasses }} text-xs font-black">Rp {{ number_format($group['amount'], 0, ',', '.') }}</span>
                            <span class="text-slate-400 group-open:rotate-180 transition-transform">v</span>
                        </div>
                    </summary>
                    <div class="border-t border-slate-100 divide-y divide-slate-100">
                        @foreach($group['items'] as $payment)
                            <a href="{{ route('payments.show', $payment['Payment_ID']) }}" class="flex flex-col gap-2 px-4 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">{{ $payment['student_name'] ?? \App\Helpers\UserResolverHelper::getName($payment['Student_ID'] ?? $payment['User_ID'] ?? '') }}</p>
                                    <p class="text-[11px] text-slate-500 font-mono">{{ $payment['Payment_ID'] ?? '-' }} | {{ $payment['Invoice_ID'] ?? '-' }}</p>
                                </div>
                                <span class="text-sm font-black text-slate-800 shrink-0">Rp {{ number_format((float)($payment['Amount_Paid'] ?? 0), 0, ',', '.') }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif

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
                <td class="px-6 py-4 text-sm font-bold text-slate-800">{{ \App\Helpers\UserResolverHelper::getName($item['Student_ID'] ?? $item['User_ID'] ?? '') }}</td>
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
                    <div class="wms-action-group">
                        @if($status === 'Verified')
                            <a href="{{ route('payments.receipt', $item['Payment_ID']) }}" target="_blank" class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors shadow-xs flex items-center gap-1" title="Unduh Kuitansi PDF Resmi">
                                📄 PDF Kuitansi
                            </a>
                        @endif

                        @if(!empty($item['Proof_File']) || !empty($item['Proof_Image']))
                            <a href="{{ route('payments.proof', $item['Payment_ID']) }}" class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold transition-colors border border-blue-200" title="Download bukti pembayaran">
                                Bukti
                            </a>
                        @endif

                        @if($status == 'Waiting Verification')
                            @if(!empty($item['Invoice_ID']))
                                <x-universal.action-button action="approve" url="{{ route('payments.verify', $item['Payment_ID']) }}" />
                            @endif
                            <x-universal.action-button action="detail" url="{{ route('payments.show', $item['Payment_ID']) }}" />
                        @else
                            <x-universal.action-button action="detail" url="{{ route('payments.show', $item['Payment_ID']) }}" />
                        @endif
                        @if($status !== 'Verified')
                            <x-universal.action-button action="delete" url="{{ route('payments.destroy', $item['Payment_ID']) }}" />
                        @endif
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
