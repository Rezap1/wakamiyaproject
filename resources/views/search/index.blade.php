@extends('layouts.app')
@section('header', 'Pencarian Perusahaan')
@section('content')
<div class="mb-6 bg-white rounded-2xl shadow p-6">
    <form action="{{ route('search.index') }}" method="GET" class="flex gap-4">
        <div class="flex-1 relative">
            <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" name="q" value="{{ $keyword }}" placeholder="Cari sesuatu di WMS..." class="w-full pl-12 pr-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-blue-500 font-medium text-lg">
        </div>
        <button type="submit" class="px-8 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition">Cari</button>
    </form>
</div>

@if($keyword)
    <h3 class="font-bold text-gray-500 mb-4 uppercase tracking-wider text-sm">Hasil pencarian untuk "<span class="text-slate-800">{{ $keyword }}</span>"</h3>
    
    @if(empty($results))
    <div class="bg-white rounded-2xl shadow p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <h3 class="text-xl font-bold text-gray-700">Hasil Tidak Ditemukan</h3>
        <p class="text-gray-500 mt-2">Coba sesuaikan kata kunci Anda atau periksa izin akses Anda.</p>
    </div>
    @else
        <div class="space-y-6">
        @foreach($results as $group => $items)
            <div class="bg-white rounded-2xl shadow overflow-hidden">
                <div class="bg-slate-50 px-6 py-3 border-b border-gray-100">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        {{ $group }} <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded ml-2">{{ count($items) }}</span>
                    </h4>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($items as $item)
                    <a href="{{ $item['url'] }}" class="block px-6 py-4 hover:bg-slate-50 transition">
                        <p class="font-bold text-blue-700 text-lg mb-1">{{ $item['title'] }}</p>
                        <p class="text-sm text-gray-500">{{ $item['desc'] }}</p>
                    </a>
                    @endforeach
                </div>
            </div>
        @endforeach
        </div>
    @endif

@else
    <div class="bg-white rounded-2xl shadow p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        <h3 class="text-xl font-bold text-gray-700">Pencarian Perusahaan</h3>
        <p class="text-gray-500 mt-2">Ketik kata kunci di atas untuk memindai modul yang diizinkan.</p>
        
        <form action="{{ route('search.clearHistory') }}" method="POST" class="mt-6">
            @csrf
            <button type="submit" class="text-sm font-bold text-red-500 hover:underline">Bersihkan Riwayat</button>
        </form>
    </div>
@endif
@endsection



