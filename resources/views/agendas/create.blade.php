@extends('layouts.app')
@section('header', 'WAKAMIYA AGENDA MANAGEMENT')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-600">WAKAMIYA AGENDA MANAGEMENT</p>
        <h1 class="mt-1 text-2xl font-black text-slate-900">Buat Agenda</h1>
        <p class="mt-1 text-sm font-medium text-slate-500">Jadwal • Hasil • Tindak Lanjut</p>
    </div>
    <x-universal.form action="{{ route('agendas.store') }}" method="POST" title="Agenda Baru" description="Lengkapi jadwal dan pihak terkait. Hasil serta tindak lanjut dapat diisi kemudian." buttonText="Simpan Agenda">
        @include('agendas._form')
    </x-universal.form>
</div>
@endsection
