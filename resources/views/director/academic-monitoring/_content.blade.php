@php
    $ctx = $monitoring['context'];
@endphp
<div class="space-y-5">
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Ringkasan monitoring">
        @foreach([
            ['Angkatan', $monitoring['summary']['batches'], 'bg-indigo-50 text-indigo-700'],
            ['Kelas', $monitoring['summary']['classes'], 'bg-sky-50 text-sky-700'],
            ['Siswa aktif', $monitoring['summary']['students'], 'bg-emerald-50 text-emerald-700'],
            ['Sudah dinilai', $monitoring['summary']['with_scores'], 'bg-violet-50 text-violet-700'],
            ['Belum dinilai', $monitoring['summary']['without_scores'], 'bg-amber-50 text-amber-700'],
        ] as [$label,$value,$color])
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-black {{ $color }}">{{ $label }}</span><p class="mt-3 text-3xl font-black text-slate-900">{{ number_format($value, 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">Periode/filter aktif</p></article>
        @endforeach
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @forelse($monitoring['comparison'] as $stat)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-black text-slate-900">{{ $stat['label'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $stat['students'] }} siswa · {{ $stat['observations'] }} observasi</p></div><span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">0–100</span></div>
                <p class="mt-4 text-3xl font-black text-slate-900">{{ $stat['average_label'] }}</p>
                <p class="mt-1 text-xs font-bold {{ ($stat['delta'] ?? 0) > 0 ? 'text-emerald-600' : (($stat['delta'] ?? 0) < 0 ? 'text-rose-600' : 'text-slate-500') }}">{{ $stat['delta_label'] }}</p>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"><p class="font-black text-slate-700">Belum ada nilai akademik</p><p class="mt-1 text-sm text-slate-500">Tidak ada data aktual pada periode dan filter yang dipilih.</p></div>
        @endforelse
    </section>

    <section class="grid gap-5 xl:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 xl:col-span-2"><div class="mb-4"><h2 class="text-lg font-black text-slate-900">Perkembangan akademik</h2><p class="text-xs text-slate-500">Gap berarti tidak ada penilaian pada periode tersebut; bukan nilai nol.</p></div><div data-chart="trend"></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"><h2 class="text-lg font-black text-slate-900">Distribusi nilai</h2><p class="mb-5 text-xs text-slate-500">Jumlah observasi per rentang nilai</p><div data-chart="distribution" class="space-y-3"></div></article>
    </section>

    @if($monitoring['scope'] === 'student')
        <section class="grid gap-5 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wider text-sky-600">Profil akademik</p><h2 class="mt-2 text-xl font-black text-slate-900">{{ $ctx['student']['Full_Name'] }}</h2>
                <dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs font-bold text-slate-400">Nomor siswa</dt><dd class="font-bold text-slate-800">{{ $ctx['student']['Student_Number'] ?: $ctx['student']['Student_ID'] }}</dd></div><div><dt class="text-xs font-bold text-slate-400">Angkatan</dt><dd class="font-bold text-slate-800">{{ $ctx['batch']['Batch_Name'] }}</dd></div><div><dt class="text-xs font-bold text-slate-400">Kelas saat ini</dt><dd class="font-bold text-slate-800">{{ $ctx['class']['Class_Name'] }}</dd></div></dl>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2"><h2 class="text-lg font-black text-slate-900">Perkembangan Ujian Bab</h2><p class="mb-3 text-xs text-slate-500">Diurutkan berdasarkan nomor bab aktual bila tersedia.</p><div data-chart="chapter"></div></article>
        </section>
    @endif

    @if($monitoring['scope'] === 'index' || $monitoring['scope'] === 'batch')
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-lg font-black text-slate-900">{{ $monitoring['scope'] === 'index' ? 'Daftar angkatan' : 'Daftar kelas' }}</h2>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @forelse($monitoring['entities'] as $entity)
                    @php
                        $isBatch = $monitoring['scope'] === 'index';
                        $entityUrl = $isBatch ? route('director.academic-monitoring.batch', $entity['Batch_ID']) : route('director.academic-monitoring.class', [$ctx['batch']['Batch_ID'], $entity['Class_ID']]);
                    @endphp
                    <article class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:shadow-md">
                        <div class="flex items-start justify-between gap-3"><div><h3 class="font-black text-slate-900">{{ $isBatch ? ($entity['Batch_Name'] ?: $entity['Batch_ID']) : ($entity['Class_Name'] ?: $entity['Class_ID']) }}</h3><p class="mt-1 text-xs text-slate-500">@if($isBatch){{ $entity['classes'] }} kelas · @endif{{ $entity['students'] }} siswa</p></div><a href="{{ $entityUrl }}" class="rounded-lg bg-sky-50 px-3 py-2 text-xs font-black text-sky-700 hover:bg-sky-100">Lihat Detail</a></div>
                        <div class="mt-4 flex flex-wrap gap-2">@forelse($entity['monitoring'] as $stat)<span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $stat['label'] }}: {{ $stat['average_label'] }}</span>@empty<span class="text-xs font-semibold text-slate-400">Belum dinilai</span>@endforelse</div>
                    </article>
                @empty<p class="col-span-full py-8 text-center text-sm font-semibold text-slate-500">Belum ada data pada tingkat ini.</p>@endforelse
            </div>
        </section>
    @endif

    @if($monitoring['scope'] === 'class')
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-lg font-black text-slate-900">Siswa {{ $ctx['class']['Class_Name'] }}</h2><p class="text-xs text-slate-500">Sensei terkait: {{ $monitoring['teachers']->pluck('Full_Name')->filter()->join(', ') ?: 'Belum ada assignment' }}</p></div>
                <form method="GET" class="flex flex-wrap gap-2"><input type="hidden" name="period" value="{{ $monitoring['filters']['period'] }}"><input type="hidden" name="start_date" value="{{ $monitoring['filters']['start_date'] }}"><input type="hidden" name="end_date" value="{{ $monitoring['filters']['end_date'] }}"><input name="search" value="{{ $monitoring['filters']['search'] }}" placeholder="Cari nama/nomor…" class="min-h-11 rounded-lg border-slate-300 text-sm"><select name="sort" class="min-h-11 rounded-lg border-slate-300 text-sm"><option value="name">Nama</option><option value="number" @selected($monitoring['filters']['sort']==='number')>Nomor</option><option value="status" @selected($monitoring['filters']['sort']==='status')>Status</option></select><button class="min-h-11 rounded-lg bg-slate-900 px-4 text-sm font-black text-white">Cari</button></form>
            </div>
            <div class="mt-4 overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead><tr class="text-left text-xs uppercase tracking-wide text-slate-500"><th class="px-3 py-3">Siswa</th><th class="px-3 py-3">Ringkasan nilai</th><th class="px-3 py-3">Status</th><th class="px-3 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($monitoring['student_paginator'] as $student)<tr><td class="px-3 py-4"><p class="font-black text-slate-900">{{ $student['Full_Name'] }}</p><p class="text-xs text-slate-500">{{ $student['Student_Number'] ?: $student['Student_ID'] }}</p></td><td class="px-3 py-4"><div class="flex flex-wrap gap-1.5">@forelse($student['monitoring'] as $stat)<span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold">{{ $stat['label'] }} {{ $stat['average_label'] }}</span>@empty<span class="text-xs font-bold text-amber-600">Belum dinilai</span>@endforelse</div></td><td class="px-3 py-4 text-xs font-bold text-slate-600">{{ $student['Enrollment_Status'] ?: 'Aktif' }}</td><td class="px-3 py-4 text-right"><a class="font-black text-sky-700 hover:underline" href="{{ route('director.academic-monitoring.student', [$ctx['batch']['Batch_ID'], $ctx['class']['Class_ID'], $student['Student_ID']]) }}">Detail Siswa</a></td></tr>@empty<tr><td colspan="4" class="px-3 py-10 text-center text-slate-500">Tidak ada siswa yang cocok.</td></tr>@endforelse
            </tbody></table></div><div class="mt-4">{{ $monitoring['student_paginator']->links() }}</div>
        </section>
    @endif

    @if($monitoring['scope'] === 'student')
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"><h2 class="text-lg font-black text-slate-900">Riwayat nilai</h2><div class="mt-4 overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead><tr class="text-left text-xs uppercase tracking-wide text-slate-500"><th class="px-3 py-3">Tanggal</th><th class="px-3 py-3">Jenis</th><th class="px-3 py-3">Ujian / materi</th><th class="px-3 py-3">Nilai</th><th class="px-3 py-3">Sensei</th><th class="px-3 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($monitoring['student_history'] as $row)<tr><td class="px-3 py-3 font-semibold">{{ \Carbon\Carbon::parse($row['occurred_on'])->format('d M Y') }}</td><td class="px-3 py-3">{{ $row['category_label'] }}</td><td class="px-3 py-3">{{ $row['title'] }}</td><td class="px-3 py-3 text-lg font-black">{{ $row['score_label'] }}</td><td class="px-3 py-3">{{ $row['evaluator'] }}</td><td class="px-3 py-3"><span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-black text-emerald-700">{{ $row['status'] }}</span></td></tr>@empty<tr><td colspan="6" class="px-3 py-10 text-center text-slate-500">Belum ada riwayat nilai.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $monitoring['student_history']->links() }}</div></section>
    @endif

    <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs leading-5 text-slate-600"><strong class="text-slate-800">Metode:</strong> {{ $monitoring['methodology'] }} <span class="ml-1"><strong class="text-slate-800">Histori:</strong> {{ $monitoring['history_note'] }}</span></aside>
</div>
