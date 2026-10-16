@extends('layouts.app')

@section('header', 'Dasbor Direktur')

@section('content')
@php
    $periodQuery = [
        'start_date' => $filters['start_date'] ?? now()->startOfMonth()->toDateString(),
        'end_date' => $filters['end_date'] ?? now()->toDateString(),
    ];
@endphp

<div class="space-y-6" data-director-dashboard>
    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white shadow-xl" aria-labelledby="director-title">
        <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-end">
            <div>
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-blue-100">
                    Executive Control Center
                </div>
                <h1 id="director-title" class="text-2xl font-black tracking-tight sm:text-3xl">Dasbor Direktur</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Ringkasan lintas divisi, risiko operasional, dan keputusan manajemen dari sumber data WMS yang terverifikasi.</p>
                @if(!isset($api_error) || !$api_error)
                    <p class="mt-4 text-xs font-semibold text-slate-400">Data dimuat {{ $data_updated_at->timezone(config('app.timezone'))->format('d M Y, H:i') }} WIB · Periode transaksi {{ $periodQuery['start_date'] }}—{{ $periodQuery['end_date'] }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('director.reports', $periodQuery) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-extrabold text-slate-900 transition hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300">Buka Pusat Laporan</a>
                <a href="{{ route('approvals.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-extrabold text-white transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300">Kotak Persetujuan</a>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('dashboard.director') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filter periode dasbor">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600">Mulai periode
                    <input type="date" name="start_date" value="{{ $periodQuery['start_date'] }}" class="mt-1 block min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600">Akhir periode
                    <input type="date" name="end_date" value="{{ $periodQuery['end_date'] }}" class="mt-1 block min-h-11 w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
            </div>
            <button class="min-h-11 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-extrabold text-white transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Terapkan Periode</button>
        </div>
        @error('start_date')<p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
        @error('end_date')<p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
    </form>

    @if(isset($api_error) && $api_error)
        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center" role="alert">
            <h2 class="text-lg font-black text-rose-900">Data dashboard belum dapat dimuat</h2>
            <p class="mx-auto mt-2 max-w-2xl text-sm text-rose-700">{{ $error_message ?? 'Sumber data WMS tidak merespons. Tidak ada angka nol pengganti yang ditampilkan.' }}</p>
            <a href="{{ request()->fullUrl() }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-rose-700 px-4 py-2 text-sm font-bold text-white">Coba muat ulang</a>
        </section>
    @else
        @php
            $cards = [
                ['label' => 'Siswa aktif', 'value' => number_format($kpi['active_students']), 'note' => number_format($kpi['new_students']).' pendaftar pada periode', 'source' => 'Siswa operasional pada MASTER_STUDENT', 'tone' => 'blue'],
                ['label' => 'Arus kas bersih', 'value' => 'Rp '.number_format($kpi['net_cash_flow'], 0, ',', '.'), 'note' => 'Masuk Rp '.number_format($kpi['income'], 0, ',', '.').' · Keluar Rp '.number_format($kpi['expense'], 0, ',', '.'), 'source' => 'Transaksi aktif dalam periode', 'tone' => $kpi['net_cash_flow'] >= 0 ? 'emerald' : 'rose'],
                ['label' => 'Piutang aktif', 'value' => 'Rp '.number_format($kpi['outstanding_amount'], 0, ',', '.'), 'note' => number_format($kpi['overdue_invoices']).' invoice lewat jatuh tempo', 'source' => 'Invoice bukan Draft/Cancelled/Paid', 'tone' => 'amber'],
                ['label' => 'Menunggu keputusan', 'value' => number_format($kpi['pending_approvals']), 'note' => 'Approver aktif: DIRECTOR', 'source' => 'MASTER_APPROVAL · Waiting Approval', 'tone' => 'violet'],
            ];
            $tones = [
                'blue' => 'border-blue-200 bg-blue-50 text-blue-900',
                'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
                'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
                'violet' => 'border-violet-200 bg-violet-50 text-violet-900',
            ];
        @endphp

        <section aria-labelledby="executive-summary-title">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="executive-summary-title" class="text-lg font-black text-slate-900">Ringkasan Eksekutif</h2>
                <span class="text-xs font-semibold text-slate-500">{{ $periodQuery['start_date'] }}—{{ $periodQuery['end_date'] }}</span>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($cards as $card)
                    <article class="rounded-2xl border p-5 {{ $tones[$card['tone']] }}">
                        <p class="text-xs font-black uppercase tracking-[0.12em] opacity-70">{{ $card['label'] }}</p>
                        <p class="mt-3 break-words text-2xl font-black tracking-tight">{{ $card['value'] }}</p>
                        <p class="mt-2 text-xs font-bold opacity-80">{{ $card['note'] }}</p>
                        <p class="mt-4 border-t border-current/10 pt-3 text-[11px] font-semibold opacity-60">Sumber: {{ $card['source'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="cashflow-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="cashflow-title" class="text-lg font-black text-slate-900">Tren Arus Kas</h2>
                        <p class="mt-1 text-xs text-slate-500">Penerimaan dan pengeluaran dari FINANCE_TRANSACTION.</p>
                    </div>
                    <a href="{{ route('reports.finance.cash_flow', $periodQuery) }}" class="text-sm font-extrabold text-blue-700 hover:text-blue-900">Lihat laporan →</a>
                </div>
                @php
                    $trendMax = max(1, collect($cash_flow_trend)->flatMap(fn($row) => [$row['income'], $row['expense']])->max() ?? 1);
                @endphp
                @if(collect($cash_flow_trend)->sum('income') > 0 || collect($cash_flow_trend)->sum('expense') > 0)
                    <div class="mt-6 flex min-h-56 items-end gap-3 overflow-x-auto" role="img" aria-label="Grafik arus kas per bulan">
                        @foreach($cash_flow_trend as $point)
                            <div class="flex min-w-14 flex-col items-center gap-2">
                                <div class="flex h-40 w-full items-end justify-center gap-1 rounded-lg bg-slate-50 px-1">
                                    <div class="w-3 rounded-t bg-emerald-500" style="height: {{ max(3, ($point['income'] / $trendMax) * 100) }}%" title="Penerimaan Rp {{ number_format($point['income'], 0, ',', '.') }}"></div>
                                    <div class="w-3 rounded-t bg-rose-400" style="height: {{ max(3, ($point['expense'] / $trendMax) * 100) }}%" title="Pengeluaran Rp {{ number_format($point['expense'], 0, ',', '.') }}"></div>
                                </div>
                                <span class="text-center text-[10px] font-bold text-slate-500">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex gap-4 text-xs font-bold text-slate-600"><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500"></i>Penerimaan</span><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-sm bg-rose-400"></i>Pengeluaran</span></div>
                @else
                    <div class="mt-6 rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm font-semibold text-slate-500">Belum ada transaksi pada periode ini.</div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="risk-title">
                <h2 id="risk-title" class="text-lg font-black text-slate-900">Alert & Tindak Lanjut</h2>
                <p class="mt-1 text-xs text-slate-500">Hanya alert dengan aturan sumber yang terverifikasi.</p>
                <div class="mt-4 space-y-3">
                    @forelse($alerts as $alert)
                        <a href="{{ $alert['url'] }}" class="block rounded-xl border {{ $alert['level'] === 'high' ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50' }} p-4 transition hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                            <p class="text-sm font-black text-slate-900">{{ $alert['title'] }}</p>
                            <p class="mt-1 text-xs leading-5 text-slate-600">{{ $alert['description'] }}</p>
                        </a>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm font-semibold text-slate-500">Tidak ada alert terverifikasi saat ini.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="attendance-title">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 id="attendance-title" class="text-lg font-black text-slate-900">Kehadiran Pegawai</h2><p class="mt-1 text-xs text-slate-500">Record pegawai sah pada periode; status mengikuti AttendanceStatusHelper.</p></div>
                    <a href="{{ route('hr.attendance.monitoring', ['start_date' => $periodQuery['start_date'], 'end_date' => $periodQuery['end_date']]) }}" class="shrink-0 text-sm font-extrabold text-blue-700">Rincian →</a>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach([
                        'present' => ['Hadir','bg-emerald-50 text-emerald-900'], 'late' => ['Terlambat','bg-amber-50 text-amber-900'], 'absent' => ['Alpa','bg-rose-50 text-rose-900'],
                        'sick' => ['Sakit','bg-blue-50 text-blue-900'], 'permitted' => ['Izin','bg-violet-50 text-violet-900'], 'total' => ['Total record','bg-slate-100 text-slate-900']
                    ] as $key => [$label, $classes])
                        <div class="rounded-xl p-3 {{ $classes }}"><p class="text-[11px] font-bold uppercase opacity-70">{{ $label }}</p><p class="mt-1 text-xl font-black">{{ number_format($attendance[$key]) }}</p></div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="people-title">
                <div class="flex items-start justify-between gap-3"><div><h2 id="people-title" class="text-lg font-black text-slate-900">Kapasitas Organisasi</h2><p class="mt-1 text-xs text-slate-500">Snapshot aktif, tidak dipengaruhi filter transaksi.</p></div><a href="{{ route('director.reports', ['division' => 'hr'] + $periodQuery) }}" class="text-sm font-extrabold text-blue-700">Laporan →</a></div>
                <dl class="mt-5 divide-y divide-slate-100">
                    <div class="flex items-center justify-between py-3"><dt class="text-sm font-bold text-slate-600">Pegawai aktif</dt><dd class="text-lg font-black text-slate-900">{{ number_format($kpi['active_employees']) }}</dd></div>
                    <div class="flex items-center justify-between py-3"><dt class="text-sm font-bold text-slate-600">Guru aktif</dt><dd class="text-lg font-black text-slate-900">{{ number_format($kpi['active_teachers']) }}</dd></div>
                    <div class="flex items-center justify-between py-3"><dt class="text-sm font-bold text-slate-600">Perusahaan mitra aktif</dt><dd class="text-lg font-black text-slate-900">{{ number_format($kpi['active_companies']) }}</dd></div>
                    <div class="flex items-center justify-between py-3"><dt class="text-sm font-bold text-slate-600">Pembayaran menunggu verifikasi</dt><dd class="text-lg font-black text-slate-900">{{ number_format($kpi['pending_payments']) }}</dd></div>
                </dl>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach(['programs' => 'Distribusi Program', 'batches' => 'Distribusi Batch'] as $key => $heading)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">{{ $heading }}</h2>
                    <div class="mt-4 space-y-3">
                        @php $distributionMax = max(1, collect($distributions[$key])->max('value') ?? 1); @endphp
                        @forelse($distributions[$key] as $item)
                            <div>
                                <div class="mb-1 flex justify-between gap-3 text-xs font-bold text-slate-700"><span class="truncate">{{ $item['label'] }}</span><span>{{ number_format($item['value']) }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600" style="width: {{ ($item['value'] / $distributionMax) * 100 }}%"></div></div>
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed p-5 text-center text-sm text-slate-500">Belum ada data distribusi.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="approval-title">
                <div class="flex justify-between gap-3"><h2 id="approval-title" class="text-lg font-black text-slate-900">Antrean Keputusan</h2><a href="{{ route('approvals.index') }}" class="text-sm font-extrabold text-blue-700">Semua →</a></div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($approvals as $approval)
                        <a href="{{ $approval['url'] }}" class="flex items-center justify-between gap-4 py-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                            <div class="min-w-0"><p class="truncate text-sm font-black text-slate-900">{{ $approval['type'] }} · {{ $approval['reference'] }}</p><p class="mt-1 truncate text-xs text-slate-500">Pengaju {{ $approval['requester'] }} · {{ $approval['submitted_at'] ?: 'Waktu tidak tersedia' }}</p></div>
                            <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase text-amber-800">{{ $approval['priority'] }}</span>
                        </a>
                    @empty
                        <p class="rounded-xl border border-dashed p-6 text-center text-sm font-semibold text-slate-500">Tidak ada pengajuan yang menunggu Direktur.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="activity-title">
                <h2 id="activity-title" class="text-lg font-black text-slate-900">Aktivitas Terbaru</h2>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($recent_activities as $activity)
                        <article class="py-3"><div class="flex items-center justify-between gap-3"><p class="text-sm font-black text-slate-900">{{ $activity['title'] }}</p><span class="rounded bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{{ $activity['module'] }}</span></div><p class="mt-1 text-xs leading-5 text-slate-600">{{ $activity['description'] }}</p><p class="mt-1 text-[11px] font-semibold text-slate-400">{{ $activity['occurred_at'] ?: 'Waktu tidak tersedia' }}</p></article>
                    @empty
                        <p class="rounded-xl border border-dashed p-6 text-center text-sm font-semibold text-slate-500">Belum ada aktivitas audit yang dapat ditampilkan.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</div>
@endsection
