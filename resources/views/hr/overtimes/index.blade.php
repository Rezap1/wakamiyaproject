@extends('layouts.app')
@section('header', 'Pengajuan Lembur Pegawai')
@section('content')

<x-universal.index-layout 
    title="Manajemen Lembur (Overtime Engine)" 
    description="Kelola pengajuan lembur, perhitungan upah lembur 100% server-side, dan integrasi Payroll Phase G."
    :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'HR' => '#', 'Pengajuan Lembur' => route('hr.overtimes.index')]"
    add-action="{{ route('hr.overtimes.create') }}"
    add-text="Ajukan Lembur Baru"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="hr.overtimes" />
    </x-slot:headerActions>

    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('hr.overtimes.index') }}" 
            refresh-url="{{ route('hr.overtimes.index') }}"
            export-url="{{ route('hr.overtimes.export-pdf') }}"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($overtimes) === 0" empty-title="Data Lembur Kosong" empty-description="Belum ada pengajuan lembur pegawai.">
        <x-slot:header>
            <th class="px-6 py-4">ID & Dokumen Lembur</th>
            <th class="px-6 py-4">Pegawai Pemohon</th>
            <th class="px-6 py-4 text-center">Tanggal</th>
            <th class="px-6 py-4 text-center">Jam Lembur</th>
            <th class="px-6 py-4 text-center">Durasi</th>
            <th class="px-6 py-4 text-right">Estimasi Upah</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($overtimes as $item)
            @php
                $status = $item['Status'] ?? 'SUBMITTED';
                $badgeColor = match($status) {
                    'APPROVED', 'INCLUDED_IN_PAYROLL' => 'green',
                    'SUBMITTED' => 'yellow',
                    'REJECTED' => 'red',
                    default => 'slate',
                };
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-mono font-bold text-slate-800 text-sm">{{ $item['Overtime_ID'] }}</div>
                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ $item['Document_Number'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800 text-sm">{{ $item['Employee_Name'] ?? $item['Employee_ID'] }}</div>
                    <div class="text-[11px] text-slate-500 font-mono">{{ $item['Employee_ID'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4 text-center font-bold text-xs text-slate-700">
                    {{ $item['Date'] ?? '-' }}
                </td>
                <td class="px-6 py-4 text-center font-medium text-slate-700 text-xs">
                    {{ $item['Start_Time'] ?? '-' }} s/d {{ $item['End_Time'] ?? '-' }}
                </td>
                <td class="px-6 py-4 text-center font-bold text-blue-600 text-xs">
                    {{ $item['Duration_Hours'] ?? 0 }} Jam
                </td>
                <td class="px-6 py-4 text-right font-black text-emerald-700 text-xs">
                    Rp {{ number_format((float)($item['Overtime_Pay'] ?? 0), 0, ',', '.') }}
                </td>
                <td class="px-6 py-4 text-center">
                    <x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <a href="{{ route('hr.overtimes.pdf', $item['Overtime_ID']) }}" target="_blank" class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors shadow-xs flex items-center gap-1" title="Unduh Surat Lembur PDF Resmi">
                            📄 PDF
                        </a>
                        <x-universal.action-button action="detail" url="{{ route('hr.overtimes.show', $item['Overtime_ID']) }}" />
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($overtimes, 'links'))
                <x-universal.pagination :paginator="$overtimes" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>
@endsection
