@extends('layouts.app')
@section('header', 'WAKAMIYA AGENDA MANAGEMENT')
@section('content')
@php
    $picOptions = collect($pics)->mapWithKeys(fn ($pic) => [
        $pic['User_ID'] => (trim((string) ($pic['Full_Name'] ?? '')) ?: ($pic['Username'] ?? $pic['User_ID']))
    ]);
    $agendaColors = ['SCHEDULED' => 'bg-blue-100 text-blue-700', 'IN_PROGRESS' => 'bg-amber-100 text-amber-700', 'COMPLETED' => 'bg-emerald-100 text-emerald-700', 'CANCELLED' => 'bg-rose-100 text-rose-700'];
@endphp
<x-universal.index-layout
    title="WAKAMIYA AGENDA MANAGEMENT"
    description="Jadwal • Hasil • Tindak Lanjut"
    :breadcrumbs="['Dasbor' => route('dashboard'), 'Agenda Management' => route('agendas.index')]"
    add-action="{{ route('agendas.create') }}"
    add-text="Buat Agenda"
>
    <x-slot:toolbar>
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form action="{{ route('agendas.index') }}" method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari agenda, pihak, pembahasan..." class="rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-sky-500 focus:ring-sky-500">
                <input type="date" name="date_from" value="{{ request('date_from') }}" title="Dari tanggal" class="rounded-xl border-slate-200 bg-slate-50 text-sm">
                <input type="date" name="date_to" value="{{ request('date_to') }}" title="Sampai tanggal" class="rounded-xl border-slate-200 bg-slate-50 text-sm">
                <select name="type" class="rounded-xl border-slate-200 bg-slate-50 text-sm">
                    <option value="">Semua Jenis</option>
                    @foreach($types as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach
                </select>
                <select name="pic" class="rounded-xl border-slate-200 bg-slate-50 text-sm">
                    <option value="">Semua PIC</option>
                    @foreach($picOptions as $value => $label)<option value="{{ $value }}" @selected(request('pic') === $value)>{{ $label }}</option>@endforeach
                </select>
                <select name="agenda_status" class="rounded-xl border-slate-200 bg-slate-50 text-sm">
                    <option value="">Semua Status Agenda</option>
                    @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('agenda_status') === $value)>{{ $label }}</option>@endforeach
                </select>
                <select name="follow_up_status" class="rounded-xl border-slate-200 bg-slate-50 text-sm">
                    <option value="">Semua Status Tindak Lanjut</option>
                    @foreach($followUpStatuses as $value => $label)<option value="{{ $value }}" @selected(request('follow_up_status') === $value)>{{ $label }}</option>@endforeach
                </select>
                <div class="flex gap-2">
                    <button class="min-h-11 flex-1 rounded-xl bg-sky-600 px-4 py-2 text-sm font-bold text-white hover:bg-sky-700">Terapkan</button>
                    <a href="{{ route('agendas.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </div>
    </x-slot:toolbar>

    <x-universal.data-table :empty="$agendas->isEmpty()" empty-title="Belum ada agenda" empty-description="Buat agenda pertama untuk mulai mengelola jadwal dan tindak lanjut.">
        <x-slot:header>
            <th class="px-5 py-4">Agenda</th>
            <th class="px-5 py-4">Jadwal</th>
            <th class="px-5 py-4">PIC & Pihak Terkait</th>
            <th class="px-5 py-4">Status</th>
            <th class="px-5 py-4">Tindak Lanjut</th>
            <th class="px-5 py-4 text-right">Aksi</th>
        </x-slot:header>
        @foreach($agendas as $agenda)
            @php
                $overdue = !empty($agenda->Follow_Up_Deadline) && $agenda->Follow_Up_Deadline < now()->toDateString() && $agenda->Follow_Up_Status !== 'COMPLETED';
            @endphp
            <tr class="transition hover:bg-slate-50">
                <td class="max-w-sm whitespace-normal px-5 py-4">
                    <div class="font-mono text-[11px] font-bold text-sky-600">{{ $agenda->Agenda_ID }}</div>
                    <div class="mt-1 font-bold text-slate-900">{{ $types[$agenda->Agenda_Type] ?? $agenda->Agenda_Type }}</div>
                    <div class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $agenda->Purpose }}</div>
                </td>
                <td class="px-5 py-4">
                    <div class="font-bold text-slate-800">{{ \Carbon\Carbon::parse($agenda->Agenda_Date)->translatedFormat('d M Y') }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ substr($agenda->Start_Time, 0, 5) }}–{{ substr($agenda->End_Time, 0, 5) }} WIB</div>
                </td>
                <td class="max-w-xs whitespace-normal px-5 py-4">
                    <div class="font-semibold text-slate-800">{{ $agenda->PIC_Name ?: $agenda->PIC_User_ID }}</div>
                    <div class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $agenda->Related_Parties }}</div>
                </td>
                <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $agendaColors[$agenda->Status] ?? 'bg-slate-100 text-slate-700' }}">{{ $statuses[$agenda->Status] ?? $agenda->Status }}</span></td>
                <td class="px-5 py-4">
                    <div class="text-xs font-bold text-slate-700">{{ $followUpStatuses[$agenda->Follow_Up_Status] ?? $agenda->Follow_Up_Status }}</div>
                    @if($agenda->Follow_Up_Deadline)
                        <div class="mt-1 text-xs font-bold {{ $overdue ? 'text-rose-600' : 'text-slate-500' }}">{{ $overdue ? 'TERLEWAT · ' : '' }}{{ \Carbon\Carbon::parse($agenda->Follow_Up_Deadline)->format('d/m/Y') }}</div>
                    @else
                        <div class="mt-1 text-xs text-slate-400">Tanpa deadline</div>
                    @endif
                </td>
                <td class="px-5 py-4 text-right">
                    <div class="flex justify-end gap-2">
                        <a href="{{ route('agendas.show', $agenda->Agenda_ID) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Detail</a>
                        <a href="{{ route('agendas.edit', $agenda->Agenda_ID) }}" class="rounded-lg bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100">Edit</a>
                    </div>
                </td>
            </tr>
        @endforeach
        <x-slot:pagination><x-universal.pagination :paginator="$agendas" /></x-slot:pagination>
    </x-universal.data-table>
</x-universal.index-layout>
@endsection
