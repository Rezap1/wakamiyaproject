@extends('layouts.app')
@section('header', 'Data Perusahaan')
@section('content')

<x-universal.index-layout 
    title="Daftar Induk Perusahaan" 
    description="Kelola data profil perusahaan, alamat, kontak, dan perizinan bisnis."
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Data Induk' => '#', 'Perusahaan' => route('companies.index')]"
    add-action="{{ route('companies.create') }}"
    add-text="Tambah Perusahaan"
>
    <x-slot:headerActions>
        <x-universal.multi-export route-prefix="companies" />
    </x-slot:headerActions>
    <x-slot:toolbar>
        <x-universal.toolbar 
            search-url="{{ route('companies.index') }}" 
            refresh-url="{{ route('companies.index') }}"
            export-url="#"
        >
            <div class="w-full md:w-auto">
                <select name="status" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors">
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif Saja</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif Saja</option>
                </select>
            </div>
        </x-universal.toolbar>
    </x-slot:toolbar>

    <x-universal.data-table :empty="count($companies) === 0" empty-title="Belum ada data perusahaan yang terdaftar." empty-description="">
        <x-slot:header>
            <th class="px-6 py-4">Perusahaan & Legalitas</th>
            <th class="px-6 py-4">Kontak & Lokasi</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($companies as $company)
        <tr class="hover:bg-slate-50 transition-colors {{ ($company['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'opacity-50' : '' }}">
            <td class="px-6 py-4">
                <div class="flex items-center">
                    @if(!empty($company['Company_Logo']))
                        <img src="{{ Storage::url($company['Company_Logo']) }}" alt="Logo" class="h-12 w-12 rounded-xl object-cover bg-slate-50 border border-slate-200 shadow-sm flex-shrink-0">
                    @else
                        <div class="h-12 w-12 flex-shrink-0 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 text-blue-600 flex items-center justify-center font-bold text-sm shadow-sm border border-blue-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                    @endif
                    <div class="ml-4">
                        <div class="text-sm font-bold text-slate-800">{{ $company['Company_Name'] }}</div>
                        <div class="text-[11px] font-medium text-slate-500 mt-0.5">{{ $company['Legal_Name'] }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <x-badge color="blue">ID: {{ $company['Company_Code'] }}</x-badge>
                            @if(!empty($company['NPWP']))
                                <span class="text-[10px] font-mono text-slate-400">NPWP: {{ $company['NPWP'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm text-slate-800 font-bold">{{ $company['City'] ? $company['City'] . ', ' : '' }}{{ $company['Country'] }}</div>
                <div class="text-xs text-slate-500 mt-1 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    {{ $company['Email'] ?: 'Tidak ada email' }}
                </div>
                <div class="text-xs text-slate-500 mt-1 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    {{ $company['Phone_Number'] ?: 'Tidak ada telepon' }}
                </div>
            </td>
            <td class="px-6 py-4">
                <x-badge status="{{ ($company['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Active' : 'Inactive' }}">
                    {{ ($company['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}
                </x-badge>
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                    <x-universal.action-button action="detail" url="{{ route('companies.show', $company['Company_ID']) }}" />
                    <x-universal.action-button action="edit" url="{{ route('companies.edit', $company['Company_ID']) }}" />
                    @if(($company['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <x-universal.action-button action="delete" url="{{ route('companies.destroy', $company['Company_ID']) }}" />
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($companies, 'links'))
                <x-universal.pagination :paginator="$companies" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>
</x-universal.index-layout>
@endsection
