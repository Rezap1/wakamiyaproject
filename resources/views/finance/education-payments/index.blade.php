@extends('layouts.app')
@section('header', 'Pembayaran Pendidikan Siswa')

@section('content')
@php
    $rupiah = fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
    $badgeClasses = [
        'fee_unset' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'unpaid' => 'bg-rose-100 text-rose-700 ring-rose-200',
        'partial' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'paid' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    ];
@endphp

<div class="min-w-0 space-y-6">
    <x-page-header
        title="Pembayaran Pendidikan Siswa"
        description="Monitoring read-only tagihan pendidikan resmi dan pembayaran terverifikasi berdasarkan kelas aktif."
        :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Pembayaran Pendidikan Siswa' => '#']"
    />

    <form method="GET" action="{{ route('finance.education-payments.index') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="search" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Search siswa</label>
                <input id="search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Nama, nomor, atau ID siswa"
                       class="min-h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            </div>
            <div>
                <label for="class_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Filter kelas</label>
                <select id="class_id" name="class_id" class="min-h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Semua Kelas</option>
                    @foreach($classOptions as $class)
                        <option value="{{ $class['id'] }}" @selected($filters['class_id'] === $class['id'])>{{ $class['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Filter status</label>
                <select id="status" name="status" class="min-h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Semua Status</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-sky-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">Terapkan Filter</button>
            <a href="{{ route('finance.education-payments.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <section aria-label="Ringkasan pembayaran" class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
        @foreach([
            ['label' => 'Jumlah Siswa', 'value' => number_format($kpi['students'], 0, ',', '.')],
            ['label' => 'Lunas', 'value' => number_format($kpi['paid_students'], 0, ',', '.')],
            ['label' => 'Cicilan', 'value' => number_format($kpi['partial_students'], 0, ',', '.')],
            ['label' => 'Belum Bayar', 'value' => number_format($kpi['unpaid_students'], 0, ',', '.')],
            ['label' => 'Total Biaya Pendidikan', 'value' => $rupiah($kpi['education_fee'])],
            ['label' => 'Total Terbayar', 'value' => $rupiah($kpi['paid'])],
            ['label' => 'Total Sisa', 'value' => $rupiah($kpi['remaining'])],
        ] as $item)
            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $item['label'] }}</p>
                <p class="mt-2 break-words text-lg font-black text-slate-900">{{ $item['value'] }}</p>
            </div>
        @endforeach
    </section>

    @forelse($groups as $group)
        <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="class-{{ $group['class_id'] }}">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-sky-600">Kelas</p>
                    <h2 id="class-{{ $group['class_id'] }}" class="truncate text-lg font-black text-slate-900">{{ $group['class_name'] }}</h2>
                </div>
                <span class="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">{{ $group['students']->count() }} siswa</span>
            </div>

            <div class="divide-y divide-slate-100 md:hidden">
                @foreach($group['students'] as $student)
                    <article class="min-w-0 space-y-4 p-4">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="break-words text-sm font-black uppercase text-slate-900">{{ $student['student_name'] }}</h3>
                                <p class="mt-1 text-xs font-medium text-slate-500">{{ $group['class_name'] }} · {{ $student['student_number'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 {{ $badgeClasses[$student['status']] }}">{{ $student['status_label'] }}</span>
                        </div>
                        <dl class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-xs">
                            <div class="min-w-0"><dt class="text-slate-500">Biaya Pendidikan</dt><dd class="mt-1 break-words font-extrabold text-slate-900">{{ $rupiah($student['education_fee']) }}</dd></div>
                            <div class="min-w-0"><dt class="text-slate-500">Sudah Dibayar</dt><dd class="mt-1 break-words font-extrabold text-emerald-700">{{ $rupiah($student['paid']) }}</dd></div>
                            <div class="min-w-0"><dt class="text-slate-500">Sisa</dt><dd class="mt-1 break-words font-extrabold text-rose-700">{{ $rupiah($student['remaining']) }}</dd></div>
                        </dl>
                        <a href="{{ route('finance.education-payments.show', $student['student_id']) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-sky-700 hover:text-sky-900">Lihat Detail</a>
                    </article>
                @endforeach
            </div>

            <div class="hidden md:block">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="bg-white text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="w-[28%] px-6 py-4">Nama Siswa</th>
                            <th class="w-[16%] px-4 py-4 text-right">Biaya Pendidikan</th>
                            <th class="w-[16%] px-4 py-4 text-right">Sudah Dibayar</th>
                            <th class="w-[16%] px-4 py-4 text-right">Sisa</th>
                            <th class="w-[14%] px-4 py-4">Status</th>
                            <th class="w-[10%] px-6 py-4 text-right">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($group['students'] as $student)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="break-words font-extrabold text-slate-900">{{ $student['student_name'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $student['student_number'] }}</p>
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-slate-800">{{ $rupiah($student['education_fee']) }}</td>
                                <td class="px-4 py-4 text-right font-bold text-emerald-700">{{ $rupiah($student['paid']) }}</td>
                                <td class="px-4 py-4 text-right font-bold text-rose-700">{{ $rupiah($student['remaining']) }}</td>
                                <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 {{ $badgeClasses[$student['status']] }}">{{ $student['status_label'] }}</span></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('finance.education-payments.show', $student['student_id']) }}" class="font-bold text-sky-700 hover:text-sky-900">Buka</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-slate-200 bg-white p-10 shadow-sm">
            <x-empty-state icon="cash" title="Data pembayaran siswa tidak ditemukan" message="Ubah filter pencarian atau pastikan siswa aktif sudah memiliki kelas aktif." />
        </div>
    @endforelse
</div>
@endsection
