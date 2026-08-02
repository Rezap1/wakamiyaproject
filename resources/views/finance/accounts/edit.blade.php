@extends('layouts.app')

@section('header')
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Edit Akun Master</h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded shadow max-w-2xl">
                <form action="{{ route('accounts.update', $account['Account_ID']) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Kode Akun *</label>
                        <input type="text" name="Account_Code" value="{{ old('Account_Code', $account['Account_Code'] ?? '') }}" class="form-input w-full rounded-md border-gray-300" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Nama Akun *</label>
                        <input type="text" name="Account_Name" value="{{ old('Account_Name', $account['Account_Name'] ?? '') }}" class="form-input w-full rounded-md border-gray-300" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Tipe Akun *</label>
                        <select name="Account_Category" class="form-select w-full rounded-md border-gray-300" required>
                            @foreach(['Asset' => 'Aset', 'Liability' => 'Kewajiban', 'Equity' => 'Ekuitas', 'Revenue' => 'Pendapatan', 'Expense' => 'Beban'] as $type => $label)
                                <option value="{{ $type }}" {{ ($account['Account_Category'] ?? '') === $type ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Akun Induk</label>
                        <select name="Parent_Account_ID" class="form-select w-full rounded-md border-gray-300">
                            <option value="">- Tidak Ada (Sebagai Header) -</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc['Account_Code'] }}" {{ ($account['Parent_Account_ID'] ?? '') === $acc['Account_Code'] ? 'selected' : '' }}>{{ $acc['Account_Code'] }} - {{ $acc['Account_Name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Deskripsi</label>
                        <textarea name="Description" class="form-textarea w-full rounded-md border-gray-300">{{ old('Description', $account['Description'] ?? '') }}</textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('accounts.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Batal</a>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Perbarui Akun</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
