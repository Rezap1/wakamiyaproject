@extends('layouts.app')
@section('header', 'Kuis Kelas')
@section('content')
@php
    $className = $class['Class_Name'] ?? 'Kelas Anda';
    $leaderboardEntries = collect($leaderboard['entries'] ?? [])->take(5);
    $quizGroups = [
        'active' => ['title' => 'Kuis Aktif', 'tone' => 'emerald', 'rows' => collect($groups['active'] ?? [])],
        'upcoming' => ['title' => 'Akan Datang', 'tone' => 'amber', 'rows' => collect($groups['upcoming'] ?? [])],
        'completed' => ['title' => 'Selesai', 'tone' => 'slate', 'rows' => collect($groups['completed'] ?? [])],
    ];
@endphp
<div class="mx-auto max-w-6xl space-y-6 pb-28 md:pb-10">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <a href="{{ route('teacher.quizzes.index') }}" class="inline-flex min-h-11 items-center text-sm font-black text-sky-700">&larr; Kembali ke Daftar Kelas</a>
        <div class="mt-2 flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-black uppercase tracking-widest text-sky-700">Ruang Kuis Kelas</p>
                <h2 class="mt-1 break-words text-2xl font-black text-slate-900 sm:text-3xl">{{ $className }}</h2>
            </div>
            <a href="{{ route('teacher.quizzes.class.create', $class['Class_ID']) }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-sky-600 px-5 text-sm font-black text-white shadow-sm hover:bg-sky-700">+ Buat Kuis</a>
        </div>
    </header>

    <section aria-labelledby="class-quiz-summary" class="grid min-w-0 grid-cols-2 gap-3 lg:grid-cols-4">
        <h3 id="class-quiz-summary" class="sr-only">Ringkasan kelas</h3>
        @foreach([
            ['label' => 'Kuis Aktif', 'value' => $summary['active'] ?? 0, 'class' => 'bg-emerald-50 text-emerald-900'],
            ['label' => 'Akan Datang', 'value' => $summary['upcoming'] ?? 0, 'class' => 'bg-amber-50 text-amber-900'],
            ['label' => 'Selesai', 'value' => $summary['completed'] ?? 0, 'class' => 'bg-slate-100 text-slate-900'],
            ['label' => 'Jumlah Siswa', 'value' => $summary['students'] ?? 0, 'class' => 'bg-sky-50 text-sky-900'],
        ] as $item)
            <div class="min-w-0 rounded-2xl border border-white/60 p-4 shadow-sm {{ $item['class'] }}">
                <p class="break-words text-[11px] font-black uppercase tracking-wide opacity-70">{{ $item['label'] }}</p>
                <p class="mt-1 text-2xl font-black">{{ $item['value'] }}</p>
            </div>
        @endforeach
    </section>

    <section aria-labelledby="class-leaderboard-heading" class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
        <div class="flex min-w-0 flex-col gap-3 bg-gradient-to-r from-amber-50 to-white p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-400 text-amber-950" aria-hidden="true"><x-sidebar.icon name="trophy" class="h-6 w-6" /></span>
                <div class="min-w-0">
                    <h3 id="class-leaderboard-heading" class="font-black text-slate-900">Peringkat Kelas</h3>
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-600"><x-sidebar.icon name="calendar" class="h-4 w-4 shrink-0" /> Periode {{ $leaderboard['period']['label'] ?? '-' }}</p>
                </div>
            </div>
            <a href="{{ route('teacher.quizzes.leaderboard', $class['Class_ID']) }}" class="inline-flex min-h-11 shrink-0 items-center font-black text-amber-900">Lihat Semua Peringkat</a>
        </div>
        <div class="space-y-2 border-t border-amber-100 p-4 sm:p-5">
            @forelse($leaderboardEntries as $entry)
                @php
                    $rank = (int) $entry['Rank'];
                    $rankTone = match ($rank) {
                        1 => 'bg-amber-400 text-amber-950',
                        2 => 'bg-slate-300 text-slate-900',
                        3 => 'bg-orange-300 text-orange-950',
                        default => 'bg-slate-100 text-slate-700',
                    };
                @endphp
                <article class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-slate-100 bg-white p-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm font-black {{ $rankTone }}">#{{ $rank }}</span>
                        <div class="min-w-0"><p class="break-words text-sm font-black text-slate-900">{{ $entry['Student_Name'] }}</p><p class="text-xs text-slate-500">{{ $entry['Quiz_Count'] ?? 0 }} kuis</p></div>
                    </div>
                    <strong class="flex shrink-0 items-center gap-1 text-sm text-slate-900"><x-sidebar.icon name="star" class="h-4 w-4 text-amber-500" /> {{ (float) $entry['Points'] }} poin</strong>
                </article>
            @empty
                <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada hasil pada periode ini.</p>
            @endforelse
        </div>
    </section>

    @if(($quiz_count ?? 0) === 0)
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-sky-700"><x-sidebar.icon name="clipboard-list" class="h-7 w-7" /></span>
            <h3 class="mt-4 font-black text-slate-900">Belum ada kuis untuk kelas ini.</h3>
            <p class="mt-1 text-sm text-slate-500">Buat kuis pertama untuk mulai mengisi ruang kelas ini.</p>
        </section>
    @else
        @foreach($quizGroups as $key => $group)
            <section aria-labelledby="quiz-group-{{ $key }}" class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="quiz-group-{{ $key }}" class="text-lg font-black text-slate-900">{{ $group['title'] }}</h3>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $group['rows']->count() }}</span>
                </div>
                <div class="grid min-w-0 gap-3 lg:grid-cols-2">
                    @forelse($group['rows'] as $quiz)
                        @php
                            $statusTone = match ($quiz['Lifecycle']) {
                                'ACTIVE' => 'bg-emerald-100 text-emerald-800',
                                'DRAFT' => 'bg-slate-100 text-slate-700',
                                'SCHEDULED' => 'bg-amber-100 text-amber-800',
                                default => 'bg-slate-100 text-slate-600',
                            };
                            $statusLabel = match ($quiz['Lifecycle']) {
                                'ACTIVE' => 'Aktif',
                                'DRAFT' => 'Draft',
                                'SCHEDULED' => 'Terjadwal',
                                'CLOSED' => 'Ditutup',
                                default => 'Selesai',
                            };
                        @endphp
                        <article class="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <h4 class="min-w-0 break-words font-black text-slate-900">{{ $quiz['Title'] }}</h4>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $statusTone }}">{{ $statusLabel }}</span>
                            </div>
                            <p class="mt-3 flex min-w-0 items-start gap-2 break-words text-xs leading-5 text-slate-600"><x-sidebar.icon name="calendar" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $quiz['Start_At'] }} &ndash; {{ $quiz['End_At'] }} WIB</p>
                            <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-lg bg-slate-50 p-2"><dt class="text-[9px] font-bold uppercase text-slate-500">Durasi</dt><dd class="mt-1 text-xs font-black text-slate-900">{{ $quiz['Duration_Minutes'] }} mnt</dd></div>
                                <div class="rounded-lg bg-slate-50 p-2"><dt class="text-[9px] font-bold uppercase text-slate-500">Soal</dt><dd class="mt-1 text-xs font-black text-slate-900">{{ $quiz['Question_Count'] }}</dd></div>
                                <div class="rounded-lg bg-slate-50 p-2"><dt class="text-[9px] font-bold uppercase text-slate-500">Selesai</dt><dd class="mt-1 text-xs font-black text-slate-900">{{ $quiz['Completion_Count'] }}</dd></div>
                            </dl>
                            <p class="mt-3 text-xs text-slate-500">{{ $quiz['Participant_Count'] }} peserta</p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <a href="{{ route('teacher.quizzes.show', $quiz['Quiz_ID']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-3 text-sm font-black text-slate-700">Detail</a>
                                <a href="{{ route('teacher.quizzes.leaderboard', $class['Class_ID']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-3 text-sm font-black text-white">Peringkat</a>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-500 lg:col-span-2">Belum ada {{ strtolower($group['title']) }}.</p>
                    @endforelse
                </div>
                @if(($group_totals[$key] ?? 0) > ($section_limit ?? 12))
                    <p class="text-xs text-slate-500">Menampilkan maksimal {{ $section_limit }} kuis terbaru pada bagian ini.</p>
                @endif
            </section>
        @endforeach
    @endif
</div>
@endsection
