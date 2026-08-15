@extends('layouts.app')

@section('header')
    <h2 class="font-bold text-xl text-slate-800 leading-tight">Master Akun Keuangan (Chart of Accounts)</h2>
@endsection

@section('content')
<x-universal.index-layout 
    title="Master Akun (Chart of Accounts)" 
    description="Kelola seluruh daftar akun keuangan, kategori akuntansi standar, dan saldo normal."
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Keuangan' => '#', 'Master Akun' => route('accounts.index')]"
    add-action="{{ route('accounts.create') }}"
    add-text="Tambah Akun"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="accounts" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('accounts.index') }}" 
            refresh-url="{{ route('accounts.index') }}"
            export-url="{{ route('accounts.export-pdf') }}"
        />
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($accounts) === 0" empty-title="Data Akun Kosong" empty-description="Belum ada master akun yang terdaftar.">
        <x-slot:header>
            <th class="px-6 py-4">Kode Akun</th>
            <th class="px-6 py-4">Nama Akun</th>
            <th class="px-6 py-4 text-center">Kategori Akun</th>
            <th class="px-6 py-4 text-center">Saldo Normal</th>
            <th class="px-6 py-4">Akun Induk</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($accounts as $account)
            @php
                $cat = strtoupper($account['Account_Category'] ?? 'ASSET');
                $norm = strtoupper($account['Normal_Balance'] ?? ($cat === 'ASSET' || $cat === 'EXPENSE' ? 'DEBIT' : 'CREDIT'));
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-mono font-bold text-slate-800 text-sm">
                    {{ $account['Account_Code'] ?? '-' }}
                </td>
                <td class="px-6 py-4 font-bold text-slate-800 text-sm">
                    {{ $account['Account_Name'] ?? '-' }}
                    @if(!empty($account['Description']))
                        <div class="text-xs text-slate-400 font-normal truncate max-w-xs mt-0.5">{{ $account['Description'] }}</div>
                    @endif
                </td>
                <td class="px-6 py-4 text-center">
                    @if($cat === 'ASSET')
                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-100 text-blue-800">ASSET</span>
                    @elseif($cat === 'LIABILITY')
                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-100 text-amber-800">LIABILITY</span>
                    @elseif($cat === 'EQUITY')
                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-100 text-indigo-800">EQUITY</span>
                    @elseif($cat === 'REVENUE')
                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-800">REVENUE</span>
                    @else
                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-100 text-rose-800">EXPENSE</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2.5 py-1 text-xs font-black rounded-lg border {{ $norm === 'DEBIT' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-purple-50 text-purple-700 border-purple-200' }}">
                        {{ $norm }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm font-medium text-slate-600">
                    {{ $account['Parent_Account_ID'] ?: '-' }}
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <x-universal.action-button action="edit" url="{{ route('accounts.edit', $account['Account_ID']) }}" />
                        <x-universal.action-button action="delete" url="{{ route('accounts.destroy', $account['Account_ID']) }}" />
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($accounts, 'links'))
                <x-universal.pagination :paginator="$accounts" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>
@endsection
