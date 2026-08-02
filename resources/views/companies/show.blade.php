@extends('layouts.app')
@section('header', 'Profil Perusahaan')
@section('content')

@php
    $tab = request('tab', 'informasi');
@endphp

<x-universal.detail-layout 
    title="{{ $company['Company_Name'] }}" 
    subtitle="{{ $company['Legal_Name'] }}"
    status="{{ ($company['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif' }}"
    statusColor="{{ ($company['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'green' : 'red' }}"
    avatarInitials="{{ substr($company['Company_Name'] ?? 'C', 0, 1) }}"
    activeTab="{{ $tab }}"
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Data Induk' => '#', 'Perusahaan' => route('companies.index'), 'Detail' => '#']"
>
    
    <x-slot:headerActions>
        <x-universal.action-button action="edit" url="{{ route('companies.edit', $company['Company_ID']) }}" />
    </x-slot:headerActions>

    <x-slot:sidebarContent>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Kode Perusahaan</p>
            <p class="text-sm font-bold text-slate-800 mt-0.5"><x-badge color="blue">{{ $company['Company_Code'] }}</x-badge></p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">NPWP</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $company['NPWP'] ?: '-' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">NIB</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $company['Business_License_Number'] ?: '-' }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase">Pimpinan</p>
            <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $company['Director_Name'] ?: '-' }}</p>
        </div>
    </x-slot:sidebarContent>

    @if($tab === 'informasi')
        <div class="space-y-8">
            <!-- Kontak & Lokasi -->
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Alamat & Lokasi</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <p class="text-xs font-bold text-slate-400 uppercase">Alamat Lengkap</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $company['Address'] ?: 'Alamat belum diisi.' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Kota / Provinsi</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $company['City'] }} / {{ $company['Province'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Negara (Kode Pos)</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ $company['Country'] }} ({{ $company['Postal_Code'] }})</p>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Kontak Perusahaan</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Telepon</p>
                        <p class="text-sm font-bold text-slate-800 mt-1">{{ $company['Phone_Number'] ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Email</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">
                            @if($company['Email'])
                                <a href="mailto:{{ $company['Email'] }}" class="text-blue-600 hover:underline">{{ $company['Email'] }}</a>
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-bold text-slate-400 uppercase">Website</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">
                            @if($company['Website'])
                                <a href="{{ $company['Website'] }}" target="_blank" class="text-blue-600 hover:underline">{{ $company['Website'] }}</a>
                            @else
                                -
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Aset Visual</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Logo</p>
                        <div class="mt-2 h-24 w-24 rounded-xl border border-slate-200 p-2 flex items-center justify-center bg-slate-50">
                            @if(!empty($company['Company_Logo']))
                                <img src="{{ Storage::url($company['Company_Logo']) }}" alt="Logo" class="max-h-full object-contain">
                            @else
                                <span class="text-xs text-slate-400">Tidak ada</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Stempel / Cap</p>
                        <div class="mt-2 h-24 w-24 rounded-xl border border-slate-200 p-2 flex items-center justify-center bg-slate-50">
                            @if(!empty($company['Company_Stamp']))
                                <img src="{{ Storage::url($company['Company_Stamp']) }}" alt="Stamp" class="max-h-full object-contain mix-blend-multiply">
                            @else
                                <span class="text-xs text-slate-400">Tidak ada</span>
                            @endif
                        </div>
                    </div>
                    @if($company['Notes'])
                    <div class="sm:col-span-2 mt-4 bg-amber-50 rounded-xl p-4 border border-amber-100">
                        <p class="text-xs font-bold text-amber-700 uppercase mb-2">Catatan Internal</p>
                        <p class="text-sm font-medium text-amber-900">{{ $company['Notes'] }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    @elseif($tab === 'audit')
        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Log Sistem</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">ID Data</p>
                    <p class="text-sm font-mono font-bold text-slate-800 mt-1">{{ $company['Company_ID'] }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $company['Created_At'] ? \Carbon\Carbon::parse($company['Created_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs font-medium text-slate-500 mt-1">Oleh: {{ $company['Created_By'] ?? 'Sistem' }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-400 uppercase">Data Diupdate</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $company['Updated_At'] ? \Carbon\Carbon::parse($company['Updated_At'])->format('d M Y, H:i') : '-' }}</p>
                    <p class="text-xs font-medium text-slate-500 mt-1">Oleh: {{ $company['Updated_By'] ?? 'Sistem' }}</p>
                </div>
            </div>
        </div>
    @else
        <x-universal.empty-state title="Belum Ada Data" description="Data untuk tab ini belum tersedia atau sedang dikembangkan." />
    @endif

</x-universal.detail-layout>

@endsection
