@extends('layouts.app')

@section('header')
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Laporan Arus Kas</h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white p-4 rounded shadow mb-6">
                <form method="GET" action="{{ route('reports.finance.cash_flow') }}" class="flex flex-col md:flex-row gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mulai Tanggal</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="form-input rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sampai Tanggal</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="form-input rounded-md border-gray-300">
                    </div>
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded hover:bg-slate-700 h-10">Tampilkan</button>
                    <div class="ml-auto h-10 flex items-center">
                        <x-universal.multi-export route-prefix="reports.finance" :extra-params="['report_type' => 'cash_flow']" />
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white p-4 rounded shadow border-l-4 border-green-500">
                    <p class="text-gray-500 text-sm">Total Pemasukan</p>
                    <p class="text-2xl font-bold text-green-600">Rp {{ number_format($total_income, 0, ',', '.') }}</p>
                </div>
                <div class="bg-white p-4 rounded shadow border-l-4 border-red-500">
                    <p class="text-gray-500 text-sm">Total Pengeluaran</p>
                    <p class="text-2xl font-bold text-red-600">Rp {{ number_format($total_expense, 0, ',', '.') }}</p>
                </div>
                <div class="bg-white p-4 rounded shadow border-l-4 {{ $net_cash_flow >= 0 ? 'border-blue-500' : 'border-yellow-500' }}">
                    <p class="text-gray-500 text-sm">Arus Kas Bersih</p>
                    <p class="text-2xl font-bold {{ $net_cash_flow >= 0 ? 'text-blue-600' : 'text-yellow-600' }}">Rp {{ number_format($net_cash_flow, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white rounded shadow overflow-hidden">
                <div class="p-4 border-b">
                    <h3 class="font-bold">Rincian Transaksi</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($transactions as $trx)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $trx['Transaction_Date'] ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold {{ ($trx['Type'] ?? '') == 'Income' ? 'text-green-600' : 'text-red-600' }}">{{ $trx['Type'] ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $trx['Category'] ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $trx['Description'] ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-right text-gray-900">Rp {{ number_format($trx['Amount'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Tidak ada data pada periode ini.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
@endsection
