@extends('layouts.app')

@section('header', 'Pusat Laporan Direktur')

@section('content')
@php
    $divisionLabels = [
        'finance' => 'Finance',
        'academic' => 'Academic',
        'hr' => 'HR & Kehadiran',
        'marketing' => 'Marketing',
        'approvals' => 'Persetujuan',
    ];
@endphp

<div class="space-y-5" data-director-report-center>
    <section class="rounded-3xl bg-slate-950 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="text-xs font-black uppercase tracking-[0.18em] text-blue-300">Read-only Management Reporting</p><h1 class="mt-2 text-2xl font-black">Pusat Laporan Direktur</h1><p class="mt-2 max-w-2xl text-sm text-slate-300">Cari, filter, tinjau, dan ekspor snapshot data sumber tanpa membuka hak mutasi modul operasional.</p></div>
            <a href="{{ route('dashboard.director', ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-extrabold text-slate-900">Kembali ke Dasbor</a>
        </div>
    </section>

    <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Divisi laporan">
        @foreach($divisionLabels as $division => $label)
            <a href="{{ route('director.reports', ['division' => $division, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}" class="whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-extrabold transition {{ $filters['division'] === $division ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}" @if($filters['division'] === $division) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('director.reports') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filter laporan">
        <input type="hidden" name="division" value="{{ $filters['division'] }}">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="text-xs font-bold uppercase tracking-wide text-slate-600">Cari
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="ID, nama, atau referensi" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </label>
            @if($filters['division'] !== 'marketing')
                <label class="text-xs font-bold uppercase tracking-wide text-slate-600">Status / Tipe
                    <input type="text" name="status" value="{{ $filters['status'] }}" placeholder="Contoh: Income, PRESENT" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
            @endif
            <label class="text-xs font-bold uppercase tracking-wide text-slate-600">Mulai
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </label>
            <label class="text-xs font-bold uppercase tracking-wide text-slate-600">Sampai
                <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </label>
            @if($filters['division'] === 'academic')
                <label class="text-xs font-bold uppercase tracking-wide text-slate-600">Program
                    <select name="program_id" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm"><option value="">Semua program</option>@foreach($options['programs'] ?? [] as $program)<option value="{{ $program['Program_ID'] }}" @selected($filters['program_id'] === ($program['Program_ID'] ?? ''))>{{ $program['Program_Name'] ?? $program['Program_ID'] }}</option>@endforeach</select>
                </label>
                <label class="text-xs font-bold uppercase tracking-wide text-slate-600">Batch
                    <select name="batch_id" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm"><option value="">Semua batch</option>@foreach($options['batches'] ?? [] as $batch)<option value="{{ $batch['Batch_ID'] }}" @selected($filters['batch_id'] === ($batch['Batch_ID'] ?? ''))>{{ $batch['Batch_Name'] ?? $batch['Batch_ID'] }}</option>@endforeach</select>
                </label>
            @endif
            @if($filters['division'] === 'hr')
                <label class="text-xs font-bold uppercase tracking-wide text-slate-600">Divisi
                    <select name="department_id" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm"><option value="">Semua divisi</option>@foreach($options['departments'] ?? [] as $department)<option value="{{ $department['Department_ID'] }}" @selected($filters['department_id'] === ($department['Department_ID'] ?? ''))>{{ $department['Department_Name'] ?? $department['Department_ID'] }}</option>@endforeach</select>
                </label>
            @endif
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button class="min-h-11 rounded-xl bg-blue-600 px-5 py-2 text-sm font-extrabold text-white hover:bg-blue-700">Terapkan Filter</button>
            <a href="{{ route('director.reports', ['division' => $filters['division']]) }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-100 px-5 py-2 text-sm font-extrabold text-slate-700 hover:bg-slate-200">Reset</a>
            @if(!isset($api_error) || !$api_error)
                <a href="{{ route('director.reports.export-csv', $filters) }}" class="inline-flex min-h-11 items-center rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-2 text-sm font-extrabold text-emerald-800 hover:bg-emerald-100">Ekspor CSV</a>
            @endif
        </div>
        @foreach($errors->all() as $error)<p class="mt-2 text-sm font-semibold text-rose-600">{{ $error }}</p>@endforeach
    </form>

    @if(isset($api_error) && $api_error)
        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center" role="alert"><h2 class="font-black text-rose-900">Laporan gagal dimuat</h2><p class="mt-2 text-sm text-rose-700">{{ $error_message }}</p></section>
    @else
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="report-title">
            <div class="flex flex-col gap-2 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 id="report-title" class="text-lg font-black text-slate-900">{{ $title }}</h2><p class="mt-1 text-xs text-slate-500">Sumber: {{ $source }} · {{ number_format($paginated->total()) }} hasil setelah filter</p></div><span class="text-xs font-bold text-slate-500">{{ $filters['start_date'] }}—{{ $filters['end_date'] }}</span></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50"><tr>@foreach($columns as $label)<th scope="col" class="whitespace-nowrap px-4 py-3 text-xs font-black uppercase tracking-wide text-slate-600">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($paginated as $row)
                            <tr class="hover:bg-blue-50/40">
                                @foreach($columns as $field => $label)
                                    @php $value = $row[$field] ?? '-'; @endphp
                                    <td class="max-w-xs whitespace-nowrap px-4 py-3 font-medium text-slate-700">
                                        @if($field === 'Amount')
                                            Rp {{ number_format((float) $value, 0, ',', '.') }}
                                        @elseif(in_array($field, ['Status', 'Status_Label', 'Type'], true))
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $value ?: '-' }}</span>
                                        @else
                                            <span class="block max-w-xs truncate" title="{{ $value }}">{{ $value ?: '-' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}" class="px-6 py-12 text-center"><p class="font-bold text-slate-600">Tidak ada data yang cocok.</p><p class="mt-1 text-xs text-slate-400">Ubah periode atau filter pencarian.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($paginated->hasPages())<div class="border-t border-slate-200 p-4"><x-universal.pagination :paginator="$paginated" /></div>@endif
        </section>
    @endif
</div>
@endsection
