@extends('layouts.app')

@section('header')
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Akun Master</h2>
        <a href="{{ route('accounts.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700">Tambah Akun</a>
    </div>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white p-4 rounded shadow mb-6">
                <form method="GET" action="{{ route('accounts.index') }}" class="flex gap-4">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari Kode / Nama Akun..." class="form-input w-full md:w-1/3 rounded-md border-gray-300">
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded hover:bg-slate-700">Cari</button>
                    @if(request('search'))
                        <a href="{{ route('accounts.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Reset</a>
                    @endif
                </form>
            </div>

            <x-slot:headerActions>
        <x-universal.multi-export route-prefix="accounts" />
    </x-slot:headerActions>
    <x-universal.data-table>
                <x-slot:header>
                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Akun</th>
                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Akun</th>
                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Induk</th>
                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Dibuat</th>
                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </x-slot:header>

                @forelse($accounts as $account)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $account['Account_Code'] ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $account['Account_Name'] ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $account['Account_Category'] ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $account['Parent_Account_ID'] ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                        {{ isset($account['Created_At']) && $account['Created_At'] ? \Carbon\Carbon::parse($account['Created_At'])->format('d M Y') : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 flex space-x-2">
                        <a href="{{ route('accounts.edit', $account['Account_ID']) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                        <form action="{{ route('accounts.destroy', $account['Account_ID']) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">Data Akun Master tidak ditemukan.</td>
                </tr>
                @endforelse
            </x-universal.data-table>

            <div class="mt-4">
                {{ $accounts->links() }}
            </div>
            
        </div>
    </div>
@endsection
