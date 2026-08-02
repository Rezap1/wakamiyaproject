@extends('layouts.app')

@section('header')
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Transaksi Keuangan</h2>
            <a href="{{ route('transactions.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700">Catat Transaksi</a>
        </div>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white p-4 rounded shadow mb-6">
                <form method="GET" action="{{ route('transactions.index') }}" class="flex gap-4">
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors">
                    <select name="type" class="form-select w-full md:w-1/4 rounded-md border-gray-300">
                        <option value="">Semua Tipe</option>
                        <option value="Income" {{ request('type') === 'Income' ? 'selected' : '' }}>Pemasukan</option>
                        <option value="Expense" {{ request('type') === 'Expense' ? 'selected' : '' }}>Pengeluaran</option>
                    </select>
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded hover:bg-slate-700">Filter</button>
                    @if(request('type') || request('date_from') || request('date_to'))
                        <a href="{{ route('transactions.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Reset</a>
                    @endif
                </form>
            </div>

            <x-slot:headerActions>
        <x-universal.multi-export route-prefix="transactions" />
    </x-slot:headerActions>
    <x-universal.data-table>
                <x-slot:header>
                    <tr>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Akun</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nominal</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </x-slot:header>

                @forelse($transactions as $trx)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">{{ isset($trx['Transaction_Date']) ? \Carbon\Carbon::parse($trx['Transaction_Date'])->format('d M Y') : '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $trx['Account_ID'] ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold {{ ($trx['Type'] ?? '') == 'Income' ? 'text-green-600' : 'text-red-600' }}">{{ $trx['Type'] ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $trx['Category'] ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Rp {{ number_format($trx['Amount'] ?? 0, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 flex space-x-2">
                            <a href="{{ route('transactions.show', $trx['Transaction_ID']) }}" class="text-blue-600 hover:text-blue-900">Detail</a>
                            <a href="{{ route('transactions.edit', $trx['Transaction_ID']) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <form action="{{ route('transactions.destroy', $trx['Transaction_ID']) }}" method="POST" onsubmit="return confirm('Yakin ingin membatalkan transaksi ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Batal</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">Data Transaksi tidak ditemukan.</td>
                    </tr>
                    @endforelse
                 
            </x-universal.data-table>

            <div class="mt-4">
                {{ $transactions->links() }}
            </div>
            
        </div>
    </div>
@endsection
