@extends('layouts.app')
@section('header', 'Pengajuan Cuti & Izin Pegawai')
@section('content')

<x-universal.index-layout 
    title="Manajemen Cuti & Izin (Leave Engine)" 
    description="Kelola pengajuan cuti, izin resmi, sakit, serta validasi tanggal bentrok secara deterministik server-side."
    :breadcrumbs="['Dasbor' => route('dashboard.hr'), 'HR' => '#', 'Pengajuan Cuti' => route('hr.leaves.index')]"
    add-action="{{ route('hr.leaves.create') }}"
    add-text="Ajukan Cuti Baru"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="hr.leaves" />
    </x-slot:headerActions>

    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('hr.leaves.index') }}" 
            refresh-url="{{ route('hr.leaves.index') }}"
            export-url="{{ route('hr.leaves.export-pdf') }}"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($leaves) === 0" empty-title="Data Cuti Kosong" empty-description="Belum ada pengajuan cuti pegawai.">
        <x-slot:header>
            <th class="px-6 py-4">ID & Dokumen Cuti</th>
            <th class="px-6 py-4">Pegawai Pemohon</th>
            <th class="px-6 py-4 text-center">Tipe Cuti</th>
            <th class="px-6 py-4 text-center">Rentang Tanggal</th>
            <th class="px-6 py-4 text-center">Durasi</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($leaves as $item)
            @php
                $status = $item['Status'] ?? 'SUBMITTED';
                $badgeColor = match($status) {
                    'APPROVED', 'COMPLETED' => 'green',
                    'SUBMITTED', 'UNDER_REVIEW' => 'yellow',
                    'REJECTED' => 'red',
                    'CANCELLED' => 'slate',
                    default => 'slate',
                };
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-mono font-bold text-slate-800 text-sm">{{ $item['Leave_ID'] }}</div>
                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ $item['Document_Number'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800 text-sm">{{ $item['Employee_Name'] ?? $item['Employee_ID'] }}</div>
                    <div class="text-[11px] text-slate-500 font-mono">{{ $item['Employee_ID'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-100">
                        {{ $item['Leave_Type'] ?? 'CUTI_TAHUNAN' }}
                    </span>
                </td>
                <td class="px-6 py-4 text-center font-medium text-slate-700 text-xs">
                    {{ $item['Start_Date'] ?? '-' }} s/d {{ $item['End_Date'] ?? '-' }}
                </td>
                <td class="px-6 py-4 text-center font-black text-slate-900 text-xs">
                    {{ $item['Duration_Days'] ?? 1 }} Hari
                </td>
                <td class="px-6 py-4 text-center">
                    <x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <a href="{{ route('leaves.pdf', $item['Leave_ID']) }}" target="_blank" class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors shadow-xs flex items-center gap-1" title="Unduh Surat Cuti PDF Resmi">
                            📄 PDF
                        </a>
                        <x-universal.action-button action="detail" url="{{ route('hr.leaves.show', $item['Leave_ID']) }}" />
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($leaves, 'links'))
                <x-universal.pagination :paginator="$leaves" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>
@endsection
