@extends('layouts.app')
@section('header', 'Catat Transaksi')
@section('content')

<div class="space-y-6">
    <x-page-header 
        title="Catat Transaksi Baru" 
        description="Catat transaksi pengeluaran atau pemasukan baru ke buku besar (Ledger)."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Keuangan' => '#', 'Transaksi' => route('transactions.index'), 'Tambah Baru' => '#']"
    />

    <x-card class="p-0 overflow-hidden">
        <x-universal.form 
            action="{{ route('transactions.store') }}" 
            method="POST"
            title="Informasi Transaksi" 
            description="Lengkapi detail informasi transaksi di bawah ini."
            buttonText="Simpan Transaksi"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Group 1: Identity & Basic Info -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dibuat Oleh</label>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-lg">
                                {{ strtoupper(substr(auth()->user()->Full_Name ?? auth()->user()->Username ?? 'U', 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 uppercase">{{ auth()->user()->Full_Name ?? auth()->user()->Username ?? 'USER' }}</p>
                                <p class="text-xs text-gray-500 font-medium uppercase">{{ auth()->user()->Role_ID ?? 'UNKNOWN ROLE' }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hidden sm:block">
                            Identitas Sesi Aktif
                        </span>
                    </div>
                </div>

                <x-universal.input 
                    type="date" 
                    name="Transaction_Date" 
                    label="Tanggal Transaksi" 
                    :required="true" 
                    value="{{ old('Transaction_Date', date('Y-m-d')) }}" 
                />

                <x-universal.select 
                    name="Type" 
                    label="Jenis Transaksi" 
                    :required="true"
                    :options="['Income' => 'Pemasukan', 'Expense' => 'Pengeluaran']"
                    value="{{ old('Type') }}" 
                />

                <!-- Group 2: Categorization & Amount -->
                <x-universal.input
                    name="Category" 
                    label="Kategori Transaksi"
                    :required="true"
                    placeholder="Contoh: Operasional, Transportasi, ATK, Pembayaran SPP"
                    value="{{ old('Category') }}"
                />

                <div class="relative">
                    <x-universal.input 
                        type="number" 
                        name="Amount" 
                        label="Nominal (Rp)" 
                        :required="true" 
                        min="0"
                        placeholder="Contoh: 1500000"
                        value="{{ old('Amount') }}" 
                    />
                    <p class="mt-1 text-xs text-gray-500">Masukkan angka saja tanpa titik/koma.</p>
                </div>

                <!-- Group 3: Ledger Dependencies (Auto Resolved) -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Akun Kas/Bank</label>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $defaultAccount['Account_Name'] ?? 'Tidak diketahui' }} <span class="text-xs text-gray-500">({{ $defaultAccount['Account_Code'] ?? '-' }})</span></p>
                            <p class="text-xs text-gray-500 mt-0.5">Akun kas/bank ditentukan otomatis oleh sistem sesuai konfigurasi.</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Auto Resolved
                        </span>
                    </div>
                </div>

                <!-- Group 4: References -->
                <x-universal.select 
                    name="Reference_Type" 
                    label="Tipe Referensi" 
                    :required="true"
                    :options="['Other' => 'Lainnya', 'Invoice' => 'Tagihan', 'Payment' => 'Pembayaran', 'Payroll' => 'Gaji', 'Adjustment' => 'Penyesuaian']"
                    value="{{ old('Reference_Type', 'Other') }}" 
                />

                <x-universal.input 
                    name="Reference_ID" 
                    label="No. Referensi (Opsional)" 
                    placeholder="Contoh: INV-001, KWT-002"
                    value="{{ old('Reference_ID') }}" 
                />

                <div class="md:col-span-2">
                    <x-universal.textarea 
                        name="Description" 
                        label="Keterangan / Tujuan Transaksi (Opsional)" 
                        placeholder="Tuliskan detail mengenai tujuan atau keterangan transaksi ini."
                    >{{ old('Description') }}</x-universal.textarea>
                </div>
            </div>
        </x-universal.form>
    </x-card>
</div>
@endsection
