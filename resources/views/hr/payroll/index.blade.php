@extends('layouts.app')
@section('header', 'Penggajian Karyawan')
@section('content')

<x-universal.index-layout 
    title="Manajemen Penggajian" 
    description="Kelola gaji dan slip karyawan."
    :breadcrumbs="['Dashboard' => route('dashboard.hr'), 'Penggajian' => route('payrolls.index')]"
    add-action="{{ route('payrolls.create') }}"
    add-text="Buat Penggajian"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="payrolls" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('payrolls.index') }}" 
            refresh-url="{{ route('payrolls.index') }}"
            export-url="#"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($payrolls) === 0" empty-title="Data Penggajian Kosong" empty-description="Belum ada data penggajian.">
        <x-slot:header>
            <th class="px-6 py-4">Nomor Penggajian</th>
            <th class="px-6 py-4">Karyawan</th>
            <th class="px-6 py-4 text-center">Role</th>
            <th class="px-6 py-4 text-center">Periode</th>
            <th class="px-6 py-4 text-center">Gaji Bersih</th>
            <th class="px-6 py-4 text-center">Tanggal Dibuat</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($payrolls as $item)
            @php
                $status = $item['Status'] ?? 'Draft';
                $badgeColor = match($status) {
                    'Paid' => 'green',
                    'Approved' => 'blue',
                    'Calculated', 'Generated' => 'slate',
                    'Waiting Approval' => 'yellow',
                    default => 'slate',
                };
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Payroll_Number'] ?? '' }}</td>
                <td class="px-6 py-4">{{ $item['Employee_ID'] ?? 'Tidak Diketahui' }}</td>
                <td class="px-6 py-4 text-center">{{ $item['Role'] ?? 'Karyawan' }}</td>
                <td class="px-6 py-4 text-center">{{ $item['Payroll_Period'] ?? '' }}</td>
                <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format((float)($item['Net_Salary'] ?? 0), 0, ',', '.') }}</td>
                <td class="px-6 py-4 text-center text-xs text-slate-500">
                    {{ isset($item['Created_At']) ? \Carbon\Carbon::parse($item['Created_At'])->format('d M Y') : '-' }}
                </td>
                <td class="px-6 py-4 text-center">
                    <x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <x-universal.action-button action="detail" url="{{ route('payrolls.show', $item['Payroll_ID']) }}" />
                        <x-universal.action-button action="edit" url="{{ route('payrolls.edit', $item['Payroll_ID']) }}" />
                        <form action="{{ route('payrolls.destroy', $item['Payroll_ID']) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
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
