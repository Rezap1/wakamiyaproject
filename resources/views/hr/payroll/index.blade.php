@extends('layouts.app')
@section('header', 'Penggajian Pegawai & Slip Gaji')
@section('content')

@php
    $currentRoleName = strtoupper(\App\Helpers\UserResolverHelper::getRoleName(auth()->user()->Role_ID ?? ''));
    $payrollDashboardUrl = $currentRoleName === 'FINANCE' ? route('dashboard.finance') : route('dashboard.hr');
@endphp

<x-universal.index-layout 
    title="Manajemen Penggajian (HR Payroll Engine)" 
    description="Kelola gaji pegawai, integrasi presensi QR Phase F, kalkulasi deterministik server-side, dan slip gaji PDF resmi."
    :breadcrumbs="['Dasbor' => $payrollDashboardUrl, 'HR' => '#', 'Penggajian' => route('payrolls.index')]"
    add-action="{{ route('payrolls.create') }}"
    add-text="Buat Payroll Pegawai"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="payrolls" />
    </x-slot:headerActions>

    <x-slot:toolbar>
        <div class="space-y-4">
            <x-universal.toolbar 
                search-url="{{ route('payrolls.index') }}" 
                refresh-url="{{ route('payrolls.index') }}"
                export-url="{{ route('payrolls.export-pdf') }}"
            />

            <!-- BATCH PAYROLL GENERATION CARD -->
            @if(in_array($currentRoleName, ['ADMINISTRATOR', 'HR', 'MASTER'], true))
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Generate Batch Payroll Massal</h4>
                        <p class="text-[11px] text-slate-500 mt-0.5">Kalkulasi gaji seluruh pegawai aktif secara otomatis berdasarkan presensi Phase F untuk periode tertentu.</p>
                    </div>
                    <form action="{{ route('payrolls.batch-generate') }}" method="POST" class="flex items-center gap-2" onsubmit="return confirm('Proses batch payroll seluruh pegawai untuk periode ini?');">
                        @csrf
                        <input type="month" name="Payroll_Period" class="text-xs rounded-xl border-slate-200 p-2 font-bold text-slate-800" value="{{ date('Y-m') }}" required>
                        <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-sm transition-colors">
                            ⚡ Process Batch Payroll
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($payrolls) === 0" empty-title="Data Penggajian Kosong" empty-description="Belum ada data penggajian.">
        <x-slot:header>
            <th class="px-6 py-4">Nomor & ID Payroll</th>
            <th class="px-6 py-4">Pegawai</th>
            <th class="px-6 py-4 text-center">Periode</th>
            <th class="px-6 py-4 text-right">Gaji Pokok</th>
            <th class="px-6 py-4 text-right">Potongan</th>
            <th class="px-6 py-4 text-right">Gaji Bersih (Net)</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($payrolls as $item)
            @php
                $status = $item['Status'] ?? 'Draft';
                $badgeColor = match($status) {
                    'Paid', 'Closed' => 'green',
                    'Approved' => 'blue',
                    'Waiting Approval' => 'yellow',
                    'Rejected' => 'red',
                    default => 'slate',
                };
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-mono font-bold text-slate-800 text-sm">{{ $item['Payroll_Number'] ?? $item['Payroll_ID'] }}</div>
                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ $item['Document_Number'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800 text-sm">{{ $item['Employee_Name'] ?? $item['Employee_ID'] }}</div>
                    <div class="text-[11px] text-slate-500 font-mono">{{ $item['Employee_ID'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4 text-center font-bold text-xs text-slate-700">
                    {{ $item['Payroll_Period'] ?? '-' }}
                </td>
                <td class="px-6 py-4 text-right font-medium text-slate-700 text-xs">
                    Rp {{ number_format((float)($item['Base_Salary'] ?? 0), 0, ',', '.') }}
                </td>
                <td class="px-6 py-4 text-right font-medium text-rose-600 text-xs">
                    - Rp {{ number_format((float)($item['Total_Deductions'] ?? 0), 0, ',', '.') }}
                </td>
                <td class="px-6 py-4 text-right font-black text-slate-900 text-sm">
                    Rp {{ number_format((float)($item['Net_Salary'] ?? 0), 0, ',', '.') }}
                </td>
                <td class="px-6 py-4 text-center">
                    <x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <a href="{{ route('payrolls.pdf', $item['Payroll_ID']) }}" target="_blank" class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors shadow-xs flex items-center gap-1" title="Unduh Slip Gaji PDF Resmi">
                            📄 PDF
                        </a>

                        <x-universal.action-button action="detail" url="{{ route('payrolls.show', $item['Payroll_ID']) }}" />

                        @if(!in_array(strtolower($status), ['paid', 'closed']))
                            <form action="{{ route('payrolls.destroy', $item['Payroll_ID']) }}" method="POST" class="inline" onsubmit="return confirm('Hapus payroll ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus">
                                    🗑️
                                </button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($payrolls, 'links'))
                <x-universal.pagination :paginator="$payrolls" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>
@endsection
