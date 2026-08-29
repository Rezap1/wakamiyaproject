@extends('layouts.app')

@section('header', 'Edit Master Akun')

@section('content')
@php
    $currentCategory = strtoupper($account['Account_Category'] ?? 'ASSET');
@endphp

<div class="max-w-4xl mx-auto space-y-6" x-data="accountEditForm()">
    <x-universal.form 
        action="{{ route('accounts.update', $account['Account_ID']) }}" 
        method="PUT"
        title="Edit Data Akun Keuangan" 
        description="Ubah informasi master akun (Kode Akun: {{ $account['Account_Code'] ?? '-' }})."
        buttonText="Perbarui Master Akun"
    >
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Kode Akun <span class="text-rose-500 font-black">*</span></label>
                    <input type="text" name="Account_Code" value="{{ old('Account_Code', $account['Account_Code'] ?? '') }}" class="w-full text-sm font-mono font-bold rounded-xl border-slate-200 focus:ring-2 focus:ring-blue-500 px-4 py-2.5 shadow-sm" required>
                    @error('Account_Code') <p class="mt-1 text-xs font-bold text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nama Akun <span class="text-rose-500 font-black">*</span></label>
                    <input type="text" name="Account_Name" value="{{ old('Account_Name', $account['Account_Name'] ?? '') }}" class="w-full text-sm font-bold rounded-xl border-slate-200 focus:ring-2 focus:ring-blue-500 px-4 py-2.5 shadow-sm" required>
                    @error('Account_Name') <p class="mt-1 text-xs font-bold text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Kategori Akun <span class="text-rose-500 font-black">*</span></label>
                    <select name="Account_Category" x-model="category" @change="updateNormalBalance()" class="w-full text-sm font-bold rounded-xl border-slate-200 focus:ring-2 focus:ring-blue-500 px-4 py-2.5 shadow-sm" required>
                        <option value="ASSET" {{ $currentCategory === 'ASSET' ? 'selected' : '' }}>ASSET (Aset / Aktiva)</option>
                        <option value="LIABILITY" {{ $currentCategory === 'LIABILITY' ? 'selected' : '' }}>LIABILITY (Kewajiban / Hutang)</option>
                        <option value="EQUITY" {{ $currentCategory === 'EQUITY' ? 'selected' : '' }}>EQUITY (Ekuitas / Modal)</option>
                        <option value="REVENUE" {{ $currentCategory === 'REVENUE' ? 'selected' : '' }}>REVENUE (Pendapatan / Income)</option>
                        <option value="EXPENSE" {{ $currentCategory === 'EXPENSE' ? 'selected' : '' }}>EXPENSE (Beban / Biaya)</option>
                    </select>
                    @error('Account_Category') <p class="mt-1 text-xs font-bold text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Saldo Normal (Otomatis)</label>
                    <div class="p-2.5 rounded-xl border flex items-center justify-between shadow-sm"
                         :class="normalBalance === 'DEBIT' ? 'bg-blue-50 border-blue-200 text-blue-800' : 'bg-purple-50 border-purple-200 text-purple-800'">
                        <span class="text-xs font-bold uppercase tracking-wider">Tipe Saldo Normal:</span>
                        <span class="px-3 py-1 text-xs font-black rounded-lg uppercase bg-white border shadow-xs" x-text="normalBalance"></span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Diberlakukan otomatis sesuai kaidah akuntansi EPS Rev.1.0.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Akun Induk / Parent Account (Opsional)</label>
                    <select name="Parent_Account_ID" class="w-full text-sm rounded-xl border-slate-200 focus:ring-2 focus:ring-blue-500 px-4 py-2.5 shadow-sm">
                        <option value="">- Tidak Ada (Sebagai Akun Utama) -</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc['Account_Code'] }}" {{ old('Parent_Account_ID', $account['Parent_Account_ID'] ?? '') == $acc['Account_Code'] ? 'selected' : '' }}>
                                {{ $acc['Account_Code'] }} - {{ $acc['Account_Name'] }} ({{ $acc['Account_Category'] ?? 'ASSET' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-universal.textarea 
                    name="Description" 
                    label="Deskripsi / Catatan Tambahan" 
                    value="{{ old('Description', $account['Description'] ?? '') }}"
                />
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    function accountEditForm() {
        return {
            category: '{{ $currentCategory }}',
            normalBalance: '{{ $account['Normal_Balance'] ?? "DEBIT" }}',

            init() {
                this.updateNormalBalance();
            },

            updateNormalBalance() {
                if (this.category === 'ASSET' || this.category === 'EXPENSE') {
                    this.normalBalance = 'DEBIT';
                } else {
                    this.normalBalance = 'CREDIT';
                }
            }
        }
    }
</script>
@endsection
