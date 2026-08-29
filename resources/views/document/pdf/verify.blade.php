@extends('layouts.app')

@section('header', 'Verifikasi Dokumen')

@section('content')
<div class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center">
    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Kode Verifikasi</p>
    <h1 class="text-2xl font-black text-slate-900 font-mono break-all">{{ $code }}</h1>
    <p class="text-sm text-slate-500 mt-4">Dokumen elektronik berhasil dibuka dari tautan verifikasi.</p>
</div>
@endsection
