@extends('layouts.app')

@section('header')
    <h2 class="font-bold text-xl text-slate-800 leading-tight">Laporan Arus Kas (Cash Flow Report)</h2>
@endsection

@section('content')
<div class="space-y-6">
    <x-universal.index-layout 
        title="Laporan Arus Kas" 
        description="Pantau pergerakan arus kas masuk (Income), kas keluar (Expense), saldo awal (Opening Balance), dan saldo akhir (Closing Balance) secara akurat."
        :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Keuangan' => '#', 'Laporan' => route('reports.finance.index'), 'Arus Kas' => route('reports.finance.cash_flow')]"
    >
        <x-slot:headerActions>
            <x-universal.multi-export route-prefix="reports.finance" :extra-params="['report_type' => 'cash_flow', 'start_date' => $start_date, 'end_date' => $end_date, 'account_id' => $account_filter, 'category' => $category_filter]" />
        </x-slot:headerActions>

        <x-slot:toolbar>
            <form method="GET" action="{{ route('reports.finance.cash_flow') }}" class="w-full grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Mulai Tanggal</label>
                    <input type="date" name="start_date" value="{{ $start_date }}" class="bg-slate-50 border border-slate-200 text-slate-900 text-xs rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" onchange="this.form.submit()">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ $end_date }}" class="bg-slate-50 border border-slate-200 text-slate-900 text-xs rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" onchange="this.form.submit()">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Filter Akun Kas/Bank</label>
                    <select name="account_id" class="bg-slate-50 border border-slate-200 text-slate-900 text-xs rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" onchange="this.form.submit()">
                        <option value="ALL" {{ $account_filter === 'ALL' ? 'selected' : '' }}>Semua Akun (ALL)</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc['Account_Code'] ?? $acc['Account_ID'] }}" {{ $account_filter === ($acc['Account_Code'] ?? $acc['Account_ID']) ? 'selected' : '' }}>
                                {{ $acc['Account_Code'] ?? $acc['Account_ID'] }} - {{ $acc['Account_Name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Filter Kategori</label>
                    <select name="category" class="bg-slate-50 border border-slate-200 text-slate-900 text-xs rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" onchange="this.form.submit()">
                        <option value="ALL" {{ $category_filter === 'ALL' ? 'selected' : '' }}>Semua Kategori (ALL)</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ $category_filter === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-xs transition-colors">
                        🔍 Terapkan Filter
                    </button>
                </div>
            </form>
        </x-slot:toolbar>

        <!-- SUMMARY CARDS GRID -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <!-- 1. Opening Balance -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1">
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">1. Saldo Awal (Opening)</p>
                <p class="text-lg font-black text-slate-800">Rp {{ number_format($opening_balance, 0, ',', '.') }}</p>
                <p class="text-[10px] text-slate-400">Sebelum {{ \Carbon\Carbon::parse($start_date)->format('d M Y') }}</p>
            </div>

            <!-- 2. Total Income -->
            <div class="bg-white p-4 rounded-2xl border border-emerald-200 shadow-sm space-y-1 bg-emerald-50/20">
                <p class="text-[10px] font-extrabold text-emerald-600 uppercase tracking-wider">2. Total Pemasukan</p>
                <p class="text-lg font-black text-emerald-600">+ Rp {{ number_format($total_income, 0, ',', '.') }}</p>
                <p class="text-[10px] text-emerald-600/80">Kas Masuk (Income)</p>
            </div>

            <!-- 3. Total Expense -->
            <div class="bg-white p-4 rounded-2xl border border-rose-200 shadow-sm space-y-1 bg-rose-50/20">
                <p class="text-[10px] font-extrabold text-rose-600 uppercase tracking-wider">3. Total Pengeluaran</p>
                <p class="text-lg font-black text-rose-600">- Rp {{ number_format($total_expense, 0, ',', '.') }}</p>
                <p class="text-[10px] text-rose-600/80">Kas Keluar (Expense)</p>
            </div>

            <!-- 4. Net Cash Flow -->
            <div class="bg-white p-4 rounded-2xl border shadow-sm space-y-1 {{ $net_cash_flow >= 0 ? 'border-blue-200 bg-blue-50/20' : 'border-amber-200 bg-amber-50/20' }}">
                <p class="text-[10px] font-extrabold uppercase tracking-wider {{ $net_cash_flow >= 0 ? 'text-blue-600' : 'text-amber-700' }}">4. Arus Kas Bersih</p>
                <p class="text-lg font-black {{ $net_cash_flow >= 0 ? 'text-blue-600' : 'text-amber-700' }}">
                    {{ $net_cash_flow >= 0 ? '+' : '' }} Rp {{ number_format($net_cash_flow, 0, ',', '.') }}
                </p>
                <p class="text-[10px] text-slate-500">{{ $net_cash_flow >= 0 ? 'Surplus Kas' : 'Defisit Kas' }}</p>
            </div>

            <!-- 5. Closing Balance -->
            <div class="bg-slate-900 p-4 rounded-2xl shadow-md text-white space-y-1">
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">5. Saldo Akhir (Closing)</p>
                <p class="text-lg font-black text-emerald-400">Rp {{ number_format($closing_balance, 0, ',', '.') }}</p>
                <p class="text-[10px] text-slate-400">Per {{ \Carbon\Carbon::parse($end_date)->format('d M Y') }}</p>
            </div>
        </div>

        <!-- TRANSACTIONS DATA TABLE -->
        <x-universal.data-table :empty="count($transactions) === 0" empty-title="Tidak Ada Transaksi" empty-description="Tidak ditemukan transaksi pada rentang tanggal dan filter akun yang dipilih.">
            <x-slot:header>
                <th class="px-6 py-4">Tanggal</th>
                <th class="px-6 py-4">Kode Akun</th>
                <th class="px-6 py-4 text-center">Tipe Transaksi</th>
                <th class="px-6 py-4">Kategori</th>
                <th class="px-6 py-4">Keterangan Transaksi</th>
                <th class="px-6 py-4 text-right">Nominal (Rp)</th>
            </x-slot:header>

            @foreach($transactions as $trx)
                @php
                    $isIncome = strcasecmp($trx['Type'] ?? '', 'Income') === 0;
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 text-sm font-bold text-slate-800">
                        {{ !empty($trx['Transaction_Date']) ? \Carbon\Carbon::parse($trx['Transaction_Date'])->format('d M Y') : '-' }}
                    </td>
                    <td class="px-6 py-4 font-mono font-bold text-slate-700 text-sm">
                        {{ $trx['Account_ID'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($isIncome)
                            <span class="px-2.5 py-1 text-xs font-black rounded-lg bg-emerald-100 text-emerald-800 uppercase">📈 INCOME</span>
                        @else
                            <span class="px-2.5 py-1 text-xs font-black rounded-lg bg-rose-100 text-rose-800 uppercase">📉 EXPENSE</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-slate-700">
                        {{ $trx['Category'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">
                        {{ $trx['Description'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-right font-black text-sm {{ $isIncome ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $isIncome ? '+' : '-' }} Rp {{ number_format((float)($trx['Amount'] ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </x-universal.data-table>
    </x-universal.index-layout>
</div>
@endsection
