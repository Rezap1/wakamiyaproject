@extends('layouts.app')

@section('header', 'Direktori Alumni')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Registry Alumni</p>
            <h1 class="mt-1 text-2xl font-black text-slate-900">Direktori Alumni</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">
                Setiap kartu berasal dari pencatatan keberangkatan resmi. Alumni tidak dibuat otomatis hanya karena kelas berakhir atau status kelulusan berubah.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('alumni.export-csv', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                Export CSV
            </a>
            <span class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-black text-indigo-700">
                <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                {{ $totalAlumni }} Alumni aktif
            </span>
            <a href="{{ route('alumni.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700">
                <span aria-hidden="true">+</span>
                Tambah Alumni
            </a>
        </div>
    </div>

    @if($schemaError)
        <div class="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <p class="font-black">Registry Alumni belum tersedia</p>
            <p class="mt-1">{{ $schemaError }}</p>
            <p class="mt-1 text-xs text-amber-800">Tidak ada perubahan schema produksi yang dilakukan oleh aplikasi.</p>
        </div>
    @endif

    <form method="GET" action="{{ route('alumni.index') }}" class="grid grid-cols-1 gap-3 border-y border-slate-200 bg-white py-4 md:grid-cols-6">
        <label class="md:col-span-2">
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Cari</span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Nama, NIK, visa, kota, ID..." class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Sumber</span>
            <select name="source" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua sumber</option>
                <option value="WMS" @selected(request('source') === 'WMS')>Dari Siswa WMS</option>
                <option value="MANUAL" @selected(request('source') === 'MANUAL')>Input Manual</option>
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Tahun berangkat</span>
            <input type="number" min="2000" max="{{ now()->year }}" name="departure_year" value="{{ request('departure_year') }}" placeholder="2026" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Status data</span>
            <select name="status" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="active" @selected(request('status', 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                <option value="all" @selected(request('status') === 'all')>Semua</option>
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white transition hover:bg-slate-700">
                Terapkan
            </button>
            @if(request()->anyFilled(['search', 'source', 'departure_year', 'program', 'batch', 'class', 'status']))
                <a href="{{ route('alumni.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Reset</a>
            @endif
        </div>
    </form>

    @if($alumni->count() > 0)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($alumni as $row)
                @php
                    $isLegacy = !empty($row['Is_Legacy']);
                    $isActive = strtoupper((string) ($row['Is_Active'] ?? 'TRUE')) !== 'FALSE';
                    $initial = strtoupper(substr(trim((string) ($row['Full_Name'] ?? 'A')), 0, 1));
                @endphp
                <article class="flex min-h-[300px] flex-col border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start gap-3 border-b border-slate-100 p-4">
                        @if(!empty($row['Photo_URL']))
                            <img src="{{ $row['Photo_URL'] }}" alt="Foto {{ $row['Full_Name'] ?? 'Alumni' }}" class="h-14 w-14 shrink-0 rounded-lg border border-slate-200 object-cover">
                        @else
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-lg font-black text-indigo-700">{{ $initial }}</div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-black text-slate-900">{{ $row['Full_Name'] ?? '-' }}</h2>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600">{{ $row['Source_Label'] ?? '-' }}</span>
                            </div>
                            <p class="mt-1 font-mono text-xs text-slate-500">{{ $row['Alumni_ID'] ?? '-' }} @if(!empty($row['Student_ID'])) · {{ $row['Student_ID'] }} @endif</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-[10px] font-black {{ $isLegacy ? 'bg-amber-50 text-amber-700' : ($isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500') }}">{{ $isLegacy ? 'Legacy' : ($isActive ? 'Aktif' : 'Nonaktif') }}</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 p-4 text-sm">
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">NIK</dt>
                            <dd class="mt-0.5 break-all font-mono text-slate-700">{{ filled($row['NIK'] ?? null) ? $row['NIK'] : 'Belum diisi' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Nomor Visa</dt>
                            <dd class="mt-0.5 break-all font-mono text-slate-700">{{ filled($row['Visa_Number'] ?? null) ? $row['Visa_Number'] : 'Belum diisi' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Orang tua</dt>
                            <dd class="mt-0.5 truncate text-slate-700" title="{{ filled($row['Parent_Name'] ?? null) ? $row['Parent_Name'] : 'Belum diisi' }}">{{ filled($row['Parent_Name'] ?? null) ? $row['Parent_Name'] : 'Belum diisi' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Kota Jepang</dt>
                            <dd class="mt-0.5 truncate text-slate-700">{{ filled($row['Japan_City'] ?? null) ? $row['Japan_City'] : 'Belum diisi' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Keberangkatan</dt>
                            <dd class="mt-0.5 font-bold text-slate-800">{{ filled($row['Departure_Date'] ?? null) ? $row['Departure_Date'] : 'Belum diisi' }}</dd>
                        </div>
                        @if(!empty($row['Program_Name']) || !empty($row['Batch_Name']) || !empty($row['Class_Name']))
                            <div class="col-span-2 border-t border-slate-100 pt-3">
                                <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Snapshot pendidikan</dt>
                                <dd class="mt-0.5 text-xs text-slate-600">{{ $row['Program_Name'] ?? '-' }} · {{ $row['Batch_Name'] ?? '-' }} · {{ $row['Class_Name'] ?? '-' }}</dd>
                            </div>
                        @endif
                    </dl>
                    <div class="mt-auto flex items-center justify-between border-t border-slate-100 px-4 py-3">
                        <a href="{{ route('alumni.show', $row['Alumni_ID']) }}" class="text-xs font-black text-indigo-700 hover:text-indigo-900">Lihat detail</a>
                        <div class="flex items-center gap-3">
                            @if(!$isLegacy)
                                <a href="{{ route('alumni.edit', $row['Alumni_ID']) }}" class="text-xs font-bold text-slate-600 hover:text-slate-900">Edit</a>
                            @endif
                            @if($isActive && !$isLegacy)
                                <form method="POST" action="{{ route('alumni.destroy', $row['Alumni_ID']) }}" onsubmit="return confirm('Nonaktifkan data Alumni ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800">Nonaktifkan</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <p class="text-sm font-black text-slate-700">{{ $schemaError ? 'Data belum dapat ditampilkan' : 'Belum ada Alumni terdaftar' }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $schemaError ? 'Perbaiki schema MASTER_ALUMNI lalu muat ulang halaman.' : 'Gunakan tombol Tambah Alumni untuk mencatat keberangkatan pertama.' }}</p>
        </div>
    @endif

    @if($alumni->hasPages())
        <div class="border-t border-slate-200 pt-4">{{ $alumni->links() }}</div>
    @endif
</div>
@endsection
