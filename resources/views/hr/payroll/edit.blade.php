@extends('layouts.app')
@section('header', 'Edit Penggajian')
@section('content')
<div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 text-center">
    <p class="text-slate-500">Edit penggajian dibatasi untuk tujuan audit. Silakan batalkan dan buat ulang.</p>
    <a href="{{ route('payrolls.index') }}" class="mt-4 inline-block px-4 py-2 bg-slate-800 text-white rounded-lg">Kembali</a>
</div>
@endsection



