@extends('layouts.app')

@section('header', 'Detail Transaksi')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-universal.detail-layout>
                <x-slot:header>
                    <h3 class="text-lg font-bold">Transaksi #{{ $transaction['Transaction_ID'] }}</h3>
                    <p class="text-sm text-gray-500">Dicatat pada {{ $transaction['Created_At'] ?? '-' }}</p>
                </x-slot:header>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <p class="text-sm text-gray-500">Tanggal Transaksi</p>
                        <p class="font-bold">{{ $transaction['Transaction_Date'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Akun</p>
                        <p class="font-bold">{{ $account['Account_Code'] ?? '-' }} - {{ $account['Account_Name'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Tipe Transaksi</p>
                        <p class="font-bold {{ strcasecmp($transaction['Type'] ?? '', 'Income') === 0 ? 'text-green-600' : 'text-red-600' }}">{{ $transaction['Type'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Kategori</p>
                        <p class="font-bold">{{ $transaction['Category'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Nominal</p>
                        <p class="font-bold text-xl">Rp {{ number_format($transaction['Amount'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Tipe Referensi</p>
                        <p class="font-bold">{{ $transaction['Reference_Type'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">No. Referensi</p>
                        <p class="font-bold">{{ $transaction['Reference_ID'] ?? '-' }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500">Keterangan</p>
                        <p class="font-bold">{{ $transaction['Description'] ?? '-' }}</p>
                    </div>
                </div>
            </x-universal.detail-layout>
        </div>
    </div>
@endsection
