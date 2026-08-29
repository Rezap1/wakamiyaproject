@extends('layouts.app')

@section('header', 'Pusat Laporan Keuangan')

@section('content')
<div class="space-y-6">
    <x-page-header title="Laporan Eksekutif Keuangan" description="Akses laporan transaksi arus kas, saldo awal & akhir, serta analisa piutang terlambat (OVERDUE)." :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Laporan' => route('reports.finance.index')]" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Cash Flow Report Card -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition-shadow flex flex-col justify-between">
            <div class="space-y-3">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black text-xl">
                    📊
                </div>
                <h3 class="text-lg font-black text-slate-800">Laporan Arus Kas (Cash Flow)</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Pantau pergerakan arus kas masuk (Income), kas keluar (Expense), perhitungan Saldo Awal (Opening Balance), Saldo Akhir (Closing Balance), serta filter menurut Akun Kas/Bank & Kategori Transaksi.
                </p>
            </div>
            <div class="pt-6 mt-4 border-t border-slate-100 flex justify-between items-center">
                <span class="text-[11px] font-bold text-slate-400 uppercase">Perhitungan Kumulatif</span>
                <a href="{{ route('reports.finance.cash_flow') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
                    Lihat Laporan Arus Kas &rarr;
                </a>
            </div>
        </div>

        <!-- Outstanding Invoices Report Card -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition-shadow flex flex-col justify-between">
            <div class="space-y-3">
                <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-black text-xl">
                    ⚠️
                </div>
                <h3 class="text-lg font-black text-slate-800">Laporan Piutang & Tagihan Belum Lunas</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Daftar seluruh tagihan siswa dan perusahaan yang belum terbayar, terbayar sebagian (Partial Paid), serta tagihan terkurasi terlambat (OVERDUE).
                </p>
            </div>
            <div class="pt-6 mt-4 border-t border-slate-100 flex justify-between items-center">
                <span class="text-[11px] font-bold text-slate-400 uppercase">Penilaian Overdue Dinamis</span>
                <a href="{{ route('reports.finance.outstanding') }}" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
                    Lihat Laporan Piutang &rarr;
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
