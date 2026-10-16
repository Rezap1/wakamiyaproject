@extends('layouts.app')
@section('header', 'WAKAMIYA AGENDA MANAGEMENT')
@section('content')
@php
    $overdue = !empty($agenda['Follow_Up_Deadline']) && $agenda['Follow_Up_Deadline'] < now()->toDateString() && $agenda['Follow_Up_Status'] !== 'COMPLETED';
    $documentIsUrl = filter_var($agenda['Document_Notes'] ?? null, FILTER_VALIDATE_URL);
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-600">WAKAMIYA AGENDA MANAGEMENT</p>
            <h1 class="mt-1 text-2xl font-black text-slate-900">{{ $types[$agenda['Agenda_Type']] ?? $agenda['Agenda_Type'] }} · {{ $agenda['Agenda_ID'] }}</h1>
            <p class="mt-1 text-sm font-medium text-slate-500">Jadwal • Hasil • Tindak Lanjut</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('agendas.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-600">Kembali</a>
            <a href="{{ route('agendas.edit', $agenda['Agenda_ID']) }}" class="rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-bold text-white">Edit Agenda</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="text-lg font-black text-slate-900">Jadwal dan Pembahasan</h2>
            <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase text-slate-400">Tanggal & Waktu</dt><dd class="mt-1 font-bold text-slate-800">{{ \Carbon\Carbon::parse($agenda['Agenda_Date'])->translatedFormat('d F Y') }}, {{ substr($agenda['Start_Time'], 0, 5) }}–{{ substr($agenda['End_Time'], 0, 5) }} WIB</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-400">PIC</dt><dd class="mt-1 font-bold text-slate-800">{{ $agenda['PIC_Name'] ?: $agenda['PIC_User_ID'] }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-slate-400">Pihak Terkait</dt><dd class="mt-1 whitespace-pre-line text-slate-700">{{ $agenda['Related_Parties'] }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-slate-400">Tujuan / Pembahasan</dt><dd class="mt-1 whitespace-pre-line text-slate-700">{{ $agenda['Purpose'] }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-slate-400">Hasil Kegiatan</dt><dd class="mt-1 whitespace-pre-line text-slate-700">{{ $agenda['Activity_Result'] ?: 'Belum diisi.' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-slate-400">Catatan / Dokumen</dt><dd class="mt-1 whitespace-pre-line text-slate-700">@if($documentIsUrl)<a class="font-bold text-sky-600 underline" href="{{ $agenda['Document_Notes'] }}" target="_blank" rel="noopener noreferrer">Buka dokumen pendukung</a>@else{{ $agenda['Document_Notes'] ?: 'Belum diisi.' }}@endif</dd></div>
            </dl>
        </section>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Status</h2>
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm font-bold text-slate-800">{{ $statuses[$agenda['Status']] ?? $agenda['Status'] }}</div>
            </section>
            <section class="rounded-2xl border {{ $overdue ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-white' }} p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Tindak Lanjut</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $agenda['Follow_Up'] ?: 'Belum ada tindak lanjut.' }}</p>
                <div class="mt-4 text-xs font-bold uppercase {{ $overdue ? 'text-rose-700' : 'text-slate-500' }}">{{ $followUpStatuses[$agenda['Follow_Up_Status']] ?? $agenda['Follow_Up_Status'] }}</div>
                <div class="mt-1 text-sm font-bold {{ $overdue ? 'text-rose-700' : 'text-slate-800' }}">@if($agenda['Follow_Up_Deadline']){{ $overdue ? 'Deadline terlewat · ' : 'Deadline · ' }}{{ \Carbon\Carbon::parse($agenda['Follow_Up_Deadline'])->format('d/m/Y') }}@else Tanpa deadline @endif</div>
                <div class="mt-3 text-xs text-slate-500">PIC tindak lanjut: {{ $agenda['PIC_Name'] ?: $agenda['PIC_User_ID'] }}</div>
            </section>
            <form method="POST" action="{{ route('agendas.destroy', $agenda['Agenda_ID']) }}" onsubmit="return confirm('Hapus agenda ini? Riwayat akan dinonaktifkan secara aman.')">
                @csrf @method('DELETE')
                <button class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-bold text-rose-600 hover:bg-rose-50">Hapus Agenda</button>
            </form>
        </aside>
    </div>
</div>
@endsection
