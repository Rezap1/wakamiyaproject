@extends('layouts.app')
@section('header', 'Pembayaran Mandiri')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <x-page-header title="Pembayaran Mandiri" description="Kirim bukti pembayaran untuk kewajiban yang belum memiliki invoice resmi. Finance akan melakukan verifikasi." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Billing' => route('student.billing.index'), 'Pembayaran Mandiri' => '#']" />
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-900">
        Submission ini bukan invoice resmi. Status awal selalu <strong>Waiting Verification</strong> dan belum memengaruhi saldo atau laporan Finance sampai diverifikasi.
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <form action="{{ route('student.billing.self-service.pay') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="Idempotency_Key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div>
                <label class="block mb-1.5 text-sm font-bold text-slate-700">Nominal Pembayaran (Rp)</label>
                <input type="number" name="Amount_Paid" min="0.01" step="0.01" class="block w-full rounded-xl border-slate-200" required>
            </div>
            <div>
                <label class="block mb-1.5 text-sm font-bold text-slate-700">Metode Pembayaran</label>
                <select name="Payment_Method" class="block w-full rounded-xl border-slate-200" required>
                    <option value="TRANSFER">Transfer</option>
                    <option value="CASH">Tunai</option>
                </select>
            </div>
            <div>
                <label class="block mb-1.5 text-sm font-bold text-slate-700">Nama Pengirim</label>
                <input type="text" name="Sender_Name" maxlength="255" class="block w-full rounded-xl border-slate-200" required>
            </div>
            <div>
                <label class="block mb-1.5 text-sm font-bold text-slate-700">Tanggal Transfer</label>
                <input type="date" name="Transfer_Date" value="{{ now()->toDateString() }}" class="block w-full rounded-xl border-slate-200" required>
            </div>
            <div>
                <label class="block mb-1.5 text-sm font-bold text-slate-700">Bukti Pembayaran</label>
                <input type="file" name="Proof_File" accept="image/jpeg,image/png,application/pdf" class="block w-full rounded-xl border-slate-200" required>
                <p class="mt-1 text-xs text-slate-500">JPG, PNG, atau PDF; maksimum 5 MB.</p>
            </div>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl">Kirim ke Verifikasi Finance</button>
        </form>
    </div>
</div>
@endsection
