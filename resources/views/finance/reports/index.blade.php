@extends('layouts.app')

@section('header')
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Laporan Keuangan</h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Cash Flow Report -->
                <div class="bg-white p-6 rounded shadow border-l-4 border-blue-500">
                    <h3 class="text-lg font-bold text-gray-800">Laporan Arus Kas</h3>
                    <p class="text-gray-500 text-sm mt-2">Lihat pergerakan uang masuk dan keluar berdasarkan periode transaksi.</p>
                    <div class="mt-4">
                        <a href="{{ route('reports.finance.cash_flow') }}" class="text-blue-600 font-bold hover:underline">Lihat Laporan &rarr;</a>
                    </div>
                </div>

                <!-- Outstanding Invoices Report -->
                <div class="bg-white p-6 rounded shadow border-l-4 border-red-500">
                    <h3 class="text-lg font-bold text-gray-800">Laporan Piutang</h3>
                    <p class="text-gray-500 text-sm mt-2">Daftar tagihan yang belum dibayar atau baru dibayar sebagian.</p>
                    <div class="mt-4">
                        <a href="{{ route('reports.finance.outstanding') }}" class="text-red-600 font-bold hover:underline">Lihat Laporan &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
