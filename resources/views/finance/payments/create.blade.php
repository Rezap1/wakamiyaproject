@extends('layouts.app')

@section('header', 'Catat Pembayaran')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Catat Pembayaran"
        description="Rekam pembayaran manual untuk invoice yang sudah diterbitkan."
        :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Pembayaran' => route('payments.index'), 'Catat' => '#']"
    />

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <form action="{{ route('payments.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @csrf
            <input type="hidden" name="Idempotency_Key" value="{{ old('Idempotency_Key', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="md:col-span-2">
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Invoice</label>
                <select name="Invoice_ID" class="block w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">Pilih invoice</option>
                    @foreach($invoices as $item)
                        @php $invoiceId = $item['Invoice_ID'] ?? ''; @endphp
                        <option value="{{ $invoiceId }}" @selected(old('Invoice_ID', $invoice['Invoice_ID'] ?? '') === $invoiceId)>
                            {{ $invoiceId }} - {{ $item['Student_ID'] ?? $item['Company_ID'] ?? 'Customer' }} - Rp {{ number_format((float)($item['Amount'] ?? 0), 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nominal Dibayar</label>
                <input type="number" name="Amount_Paid" value="{{ old('Amount_Paid') }}" min="1" step="1" class="block w-full rounded-xl border-slate-200 text-sm" required>
            </div>

            <div>
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Tanggal Pembayaran</label>
                <input type="date" name="Payment_Date" value="{{ old('Payment_Date', now()->toDateString()) }}" class="block w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div>
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Metode</label>
                <select name="Payment_Method" class="block w-full rounded-xl border-slate-200 text-sm" required>
                    @foreach(['TRANSFER' => 'Transfer', 'CASH' => 'Tunai', 'QRIS' => 'QRIS'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('Payment_Method', 'TRANSFER') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Akun Penerima</label>
                <input type="text" name="Account_ID" value="{{ old('Account_ID') }}" class="block w-full rounded-xl border-slate-200 text-sm" placeholder="Opsional">
            </div>

            <div>
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Nama Pengirim</label>
                <input type="text" name="Sender_Name" value="{{ old('Sender_Name') }}" class="block w-full rounded-xl border-slate-200 text-sm" maxlength="255">
            </div>

            <div>
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Bukti Pembayaran</label>
                <input type="file" name="Proof_File" class="block w-full rounded-xl border-slate-200 text-sm" accept="image/*,.pdf">
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Catatan</label>
                <textarea name="Notes" rows="3" class="block w-full rounded-xl border-slate-200 text-sm">{{ old('Notes') }}</textarea>
            </div>

            <div class="md:col-span-2 flex justify-end gap-3">
                <a href="{{ route('payments.index') }}" class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-bold text-slate-600">Batal</a>
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-sm font-bold text-white">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection
