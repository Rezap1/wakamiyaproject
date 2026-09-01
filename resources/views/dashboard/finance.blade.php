@extends('layouts.app')

@section('header', 'Dashboard Keuangan WMS')

@section('content')

@if(isset($api_error) && $api_error)
    <div class="bg-red-50 border border-red-200 rounded-2xl p-6 m-4 md:m-8 lg:m-12 flex flex-col items-center justify-center text-center space-y-4">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h2 class="text-xl font-bold text-red-800">Data belum dapat dimuat</h2>
        <p class="text-red-600 max-w-md">{{ $error_message ?? 'Terjadi kesalahan komunikasi dengan database utama (Google Sheets). Silakan coba beberapa saat lagi.' }}</p>
        <button onclick="window.location.reload()" class="px-4 py-2 mt-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors shadow-sm">
            Coba Muat Ulang
        </button>
    </div>
@else

@php
    $formattedKpi = [
        ['title' => 'Saldo Kas', 'value' => 'Rp '.number_format($kpi['cash_balance'] ?? 0, 0, ',', '.'), 'icon' => 'cash', 'color' => ($kpi['cash_balance'] ?? 0) >= 0 ? 'emerald' : 'rose'],
        ['title' => 'Cash Collected Bulan Ini', 'value' => 'Rp '.number_format($kpi['revenue_this_month'] ?? 0, 0, ',', '.'), 'icon' => 'trending-up', 'color' => 'emerald'],
        ['title' => 'Pengeluaran Bulan Ini', 'value' => 'Rp '.number_format($kpi['expense_this_month'] ?? 0, 0, ',', '.'), 'icon' => 'trending-down', 'color' => 'rose'],
        ['title' => 'Tagihan Belum Lunas', 'value' => 'Rp '.number_format($kpi['outstanding_amount'] ?? 0, 0, ',', '.'), 'icon' => 'clock', 'color' => 'amber', 'link' => route('invoices.index')],
        ['title' => 'Menunggu Verifikasi', 'value' => $kpi['pending_verification'] ?? 0, 'icon' => 'document-text', 'color' => ($kpi['pending_verification'] ?? 0) > 0 ? 'blue' : 'emerald', 'link' => route('payments.index')],
    ];

    $quickActions = [
        ['title' => 'Verifikasi Pembayaran', 'url' => route('payments.index'), 'icon' => 'badge-check', 'color' => 'blue'],
        ['title' => 'Buat Tagihan', 'url' => route('invoices.create'), 'icon' => 'document-duplicate', 'color' => 'emerald'],
        ['title' => 'Catat Transaksi', 'url' => route('transactions.create'), 'icon' => 'switch-horizontal', 'color' => 'indigo'],
    ];
@endphp

<!-- MOBILE-FIRST DASHBOARD HERO (UNIFIED WMS DESIGN SYSTEM ON MOBILE) -->
<x-mobile-dashboard-hero user-role="FINANCE" :kpi-data="$kpi ?? []" />

<!-- DESKTOP DASHBOARD VIEW -->
<div class="hidden lg:block">
    <x-dashboard-header />
    <x-dashboard.action-center 
        title="Dashboard Keuangan" 
        description="Pusat kontrol transaksi keuangan, invoice, dan laporan kas WMS."
        :kpi="$formattedKpi"
        :quick-actions="$quickActions"
        :reminders="$reminders ?? []"
        :recent-activities="$recentActivities ?? []"
    >
        <!-- Charts (Riil dari Service) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Cash Flow Chart -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h4 class="text-base font-bold text-slate-800 mb-4">Cash Flow Bulanan</h4>
                @if(count($charts['cashFlow']['labels'] ?? []) > 0 && (array_sum($charts['cashFlow']['income'] ?? []) > 0 || array_sum($charts['cashFlow']['expense'] ?? []) > 0))
                    <div class="space-y-3">
                        @foreach($charts['cashFlow']['labels'] as $i => $label)
                            @php
                                $income = $charts['cashFlow']['income'][$i] ?? 0;
                                $expense = $charts['cashFlow']['expense'][$i] ?? 0;
                                $maxVal = max(max($charts['cashFlow']['income'] ?? [1]), max($charts['cashFlow']['expense'] ?? [1]), 1);
                                $incomeWidth = round(($income / $maxVal) * 100);
                                $expenseWidth = round(($expense / $maxVal) * 100);
                            @endphp
                            <div>
                                <div class="flex justify-between text-xs text-slate-500 mb-1">
                                    <span>{{ $label }}</span>
                                    <span class="text-emerald-600">+{{ number_format($income, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex gap-1 h-2">
                                    <div class="bg-emerald-400 rounded-full transition-all" style="width: {{ $incomeWidth }}%"></div>
                                    <div class="bg-rose-400 rounded-full transition-all" style="width: {{ $expenseWidth }}%"></div>
                                </div>
                            </div>
                        @endforeach
                        <div class="flex items-center gap-4 mt-3 text-xs">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-emerald-400 rounded-full"></span> Income</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-rose-400 rounded-full"></span> Expense</span>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-center min-h-[200px] bg-slate-50 rounded-xl">
                        <p class="text-slate-400 text-sm">Belum ada data transaksi.</p>
                    </div>
                @endif
            </div>
            
            <!-- Collection Rate -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h4 class="text-base font-bold text-slate-800 mb-4">Tingkat Koleksi</h4>
                <div class="flex-grow flex flex-col items-center justify-center min-h-[200px] bg-slate-50 rounded-xl">
                    <span class="text-4xl font-bold text-blue-600">{{ $kpi['collection_rate'] ?? 0 }}%</span>
                    <p class="text-slate-400 text-sm mt-2">Tagihan Berhasil Ditagih</p>
                    @if(($kpi['overdue_invoices'] ?? 0) > 0)
                        <p class="text-rose-500 text-xs font-bold mt-2">{{ $kpi['overdue_invoices'] }} tagihan overdue</p>
                    @endif
                </div>
            </div>
        </div>
    </x-dashboard.action-center>
</div>

@endif

@endsection
