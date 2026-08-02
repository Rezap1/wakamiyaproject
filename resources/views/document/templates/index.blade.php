@extends('layouts.app')
@section('header', 'Manajer Templat')
@section('content')
<div class="space-y-6">
    <x-page-header title="Manajer Templat" description="Kelola templat HTML untuk dokumen." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Dokumen' => route('documents.index'), 'Templat' => '#']">
        <x-slot:actions>
            <a href="{{ route('templates.create') }}" class="px-4 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl shadow-sm hover:bg-emerald-700">Buat Templat</a>
        </x-slot:actions>
    </x-page-header>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($templates as $tpl)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col h-full">
                <div class="flex justify-between items-start mb-4">
                    <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded uppercase">{{ $tpl['Document_Type'] ?? 'Umum' }}</span>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">{{ $tpl['Status'] ?? 'Aktif' }}</span>
                </div>
                <h3 class="font-bold text-slate-800 text-lg">{{ $tpl['Template_Name'] ?? 'Tanpa Judul' }}</h3>
                <p class="text-sm text-slate-500 mt-1 mb-6 flex-grow">{{ $tpl['Description'] ?? 'Tidak ada deskripsi.' }}</p>
                <div class="border-t border-slate-50 pt-4 flex justify-between items-center">
                    <span class="text-xs text-slate-400 font-mono">{{ $tpl['Template_Code'] ?? '-' }}</span>
                    <a href="{{ route('templates.edit', $tpl['Template_ID']) }}" class="text-sm text-blue-600 font-bold hover:underline">Ubah</a>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-12 text-slate-400 bg-white rounded-2xl border border-slate-200 shadow-sm">Tidak ada templat yang ditemukan.</div>
        @endforelse
    </div>
</div>
@endsection



