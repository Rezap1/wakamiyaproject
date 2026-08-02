@extends('layouts.app')
@section('header', 'Catat Transaksi')
@section('content')

@php
    $accountOptions = [];
    if(isset($accounts)) {
        foreach($accounts as $acc) {
            $accountOptions[$acc['Account_ID']] = $acc['Account_Code'] . ' - ' . $acc['Account_Name'];
        }
    }
@endphp

<div class="space-y-6">
    <x-page-header 
        title="Catat Transaksi Baru" 
        description="Catat transaksi pengeluaran atau pemasukan baru."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Keuangan' => '#', 'Transaksi' => route('transactions.index'), 'Tambah Baru' => '#']"
    />

    <x-card class="p-0 overflow-hidden">
        <x-universal.form 
            action="{{ route('transactions.store') }}" 
            method="POST"
            title="Informasi Transaksi" 
            description="Lengkapi informasi transaksi."
            buttonText="Simpan Transaksi"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-universal.input 
                    type="date" 
                    name="Transaction_Date" 
                    label="Tanggal Transaksi" 
                    :required="true" 
                    value="{{ old('Transaction_Date', date('Y-m-d')) }}" 
                />

                <x-universal.searchable-select 
                    name="Account_ID" 
                    label="Akun" 
                    :options="$accountOptions" 
                    :required="true" 
                    value="{{ old('Account_ID') }}" 
                />

                <x-universal.select 
                    name="Type" 
                    label="Tipe Transaksi" 
                    :required="true"
                    :options="['Income' => 'Pemasukan', 'Expense' => 'Pengeluaran']"
                    value="{{ old('Type') }}" 
                />

                <x-universal.select 
                    name="Category" 
                    label="Kategori" 
                    :required="true"
                    :options="array_combine($categories, $categories)"
                    value="{{ old('Category') }}" 
                />

                <x-universal.input 
                    type="number" 
                    name="Amount" 
                    label="Nominal (Rp)" 
                    :required="true" 
                    value="{{ old('Amount') }}" 
                />

                <x-universal.select 
                    name="Reference_Type" 
                    label="Tipe Referensi" 
                    :required="true"
                    :options="['Other' => 'Lainnya', 'Invoice' => 'Tagihan', 'Payment' => 'Pembayaran', 'Payroll' => 'Gaji', 'Adjustment' => 'Penyesuaian']"
                    value="{{ old('Reference_Type') }}" 
                />

                <div class="md:col-span-2">
                    <x-universal.input 
                        name="Reference_ID" 
                        label="No. Referensi (Opsional)" 
                        value="{{ old('Reference_ID') }}" 
                    />
                </div>

                <div class="md:col-span-2">
                    <x-universal.textarea 
                        name="Description" 
                        label="Keterangan" 
                    >{{ old('Description') }}</x-universal.textarea>
                </div>
            </div>
        </x-universal.form>
    </x-card>
</div>
@endsection
