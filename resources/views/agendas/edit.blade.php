@extends('layouts.app')
@section('header', 'WAKAMIYA AGENDA MANAGEMENT')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-600">WAKAMIYA AGENDA MANAGEMENT</p>
        <h1 class="mt-1 text-2xl font-black text-slate-900">Edit {{ $agenda['Agenda_ID'] }}</h1>
        <p class="mt-1 text-sm font-medium text-slate-500">Jadwal • Hasil • Tindak Lanjut</p>
    </div>
    <x-universal.form action="{{ route('agendas.update', $agenda['Agenda_ID']) }}" method="PUT" title="Perbarui Agenda" description="Perbarui status kegiatan, hasil, dan progres tindak lanjut." buttonText="Simpan Perubahan">
        @include('agendas._form')
    </x-universal.form>
</div>
@endsection
