@extends('layouts.app')
@section('header', 'Manajemen Dokumen')
@section('content')
<div class="space-y-6">
    <x-page-header title="Manajemen Dokumen" description="Kelola semua dokumen dan slip yang dibuat." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Dokumen' => '#']">
        <x-slot:actions>
            <a href="{{ route('templates.index') }}" class="px-4 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl shadow-sm hover:bg-slate-50 mr-2">Kelola Templat</a>
            <a href="{{ route('documents.create') }}" class="px-4 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl shadow-sm hover:bg-emerald-700">Buat Dokumen Kustom</a>
        </x-slot:actions>
    </x-page-header>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">Nomor Dokumen</th>
                        <th class="px-6 py-4">Tipe</th>
                        <th class="px-6 py-4">Referensi</th>
                        <th class="px-6 py-4">Tanggal Dibuat</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($documents as $doc)
                        <tr>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $doc['Document_Number'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $doc['Document_Type'] ?? 'Tidak Diketahui' }}</td>
                            <td class="px-6 py-4">
                                @if(isset($doc['Reference_Module']))
                                    <span class="text-xs bg-slate-100 text-slate-500 px-2 py-1 rounded">{{ $doc['Reference_Module'] }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $doc['Generated_Date'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $status = $doc['Status'] ?? 'Draf';
                                    $bg = 'bg-slate-100 text-slate-700';
                                    if($status == 'Diterbitkan' || $status == 'Disetujui') $bg = 'bg-emerald-100 text-emerald-700';
                                    elseif($status == 'Dibuat') $bg = 'bg-indigo-100 text-indigo-700';
                                    elseif($status == 'Menunggu Persetujuan') $bg = 'bg-amber-100 text-amber-700';
                                    elseif($status == 'Diarsipkan') $bg = 'bg-rose-100 text-rose-700';
                                @endphp
                                <span class="{{ $bg }} px-2 py-1 text-[11px] font-bold rounded-lg">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('documents.show', $doc['Document_ID']) }}" class="text-blue-600 font-bold text-xs hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">Tidak ada dokumen yang ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection



