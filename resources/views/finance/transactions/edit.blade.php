@extends('layouts.app')
@section('header', 'Edit Transaksi')
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
        title="Formulir Pembaruan Transaksi" 
        description="Mengubah data transaksi: {{ $transaction['Transaction_ID'] ?? '' }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Keuangan' => '#', 'Transaksi' => route('transactions.index'), 'Edit' => '#']"
    />

    <x-card class="p-0 overflow-hidden">
        <x-universal.form 
            action="{{ route('transactions.update', $transaction['Transaction_ID']) }}" 
            method="POST"
            title="Informasi Transaksi" 
            description="Lengkapi informasi transaksi."
            buttonText="Update Transaksi"
        >
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-universal.input 
                    type="date" 
                    name="Transaction_Date" 
                    label="Tanggal Transaksi" 
                    :required="true" 
                    value="{{ old('Transaction_Date', $transaction['Transaction_Date'] ?? '') }}" 
                />

                <x-universal.searchable-select 
                    name="Account_ID" 
                    label="Akun" 
                    :options="$accountOptions" 
                    :required="true" 
                    value="{{ old('Account_ID', $transaction['Account_ID'] ?? '') }}" 
                />

                <x-universal.select 
                    name="Type" 
                    label="Tipe Transaksi" 
                    :required="true"
                    :options="['Income' => 'Pemasukan', 'Expense' => 'Pengeluaran']"
                    value="{{ old('Type', $transaction['Type'] ?? '') }}" 
                />

                <x-universal.input
                    name="Category" 
                    label="Nama Pemasukan/Pengeluaran"
                    :required="true"
                    value="{{ old('Category', $transaction['Category'] ?? '') }}" 
                    placeholder="Contoh: Beli ATK, Sewa Gedung, Donasi, Pembayaran SPP"
                />

                <x-universal.input 
                    type="number" 
                    name="Amount" 
                    label="Nominal (Rp)" 
                    :required="true" 
                    value="{{ old('Amount', $transaction['Amount'] ?? 0) }}" 
                />

                <x-universal.select 
                    name="Reference_Type" 
                    label="Tipe Referensi" 
                    :required="true"
                    :options="['Other' => 'Lainnya', 'Invoice' => 'Tagihan', 'Payment' => 'Pembayaran', 'Payroll' => 'Gaji', 'Adjustment' => 'Penyesuaian']"
                    value="{{ old('Reference_Type', $transaction['Reference_Type'] ?? '') }}" 
                />

                <div class="md:col-span-2">
                    <x-universal.input 
                        name="Reference_ID" 
                        label="No. Referensi (Opsional)" 
                        value="{{ old('Reference_ID', $transaction['Reference_ID'] ?? '') }}" 
                    />
                </div>

                <div class="md:col-span-2">
                    <x-universal.textarea 
                        name="Description" 
                        label="Keterangan" 
                    >{{ old('Description', $transaction['Description'] ?? '') }}</x-universal.textarea>
                </div>
            </div>
        </x-universal.form>
    </x-card>
</div>
@endsection
