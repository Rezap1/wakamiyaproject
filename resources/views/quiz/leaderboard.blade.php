@extends('layouts.app')
@section('header', 'Leaderboard')
@section('content')
@php
    $teacherClasses = collect($classOptions ?? []);
    $displayClass = $className ?? ($classId ?? 'Kelas Anda');
@endphp
<div class="mx-auto min-w-0 max-w-4xl space-y-5 pb-28 md:pb-10">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <a href="{{ $backRoute }}" class="inline-flex min-h-11 items-center text-sm font-black text-sky-700">← Kembali</a>
        <p class="mt-2 text-xs font-black uppercase tracking-widest text-sky-700">Leaderboard</p>
        <h2 class="mt-1 text-2xl font-black text-slate-900">{{ $displayClass }}</h2>
        <p class="mt-1 text-sm text-slate-600">Periode {{ $period['label'] }}</p>

        @if($teacherClasses->count() > 1)
            <nav class="mt-4 flex gap-2 overflow-x-auto pb-1" aria-label="Pilih leaderboard kelas">
                @foreach($teacherClasses as $class)
                    <a href="{{ route('teacher.quizzes.leaderboard', $class['Class_ID']) }}" class="shrink-0 rounded-full px-4 py-2 text-sm font-bold {{ ($classId ?? '') === $class['Class_ID'] ? 'bg-sky-100 text-sky-800 ring-1 ring-sky-300' : 'bg-slate-100 text-slate-700' }}">{{ $class['Class_Name'] ?? $class['Class_ID'] }}</a>
                @endforeach
            </nav>
        @endif
    </header>

    <div class="space-y-3">
        @forelse($entries as $entry)
            @php
                $topThree = (int) $entry['Rank'] <= 3;
                $rankStyle = match ((int) $entry['Rank']) {
                    1 => 'bg-amber-400 text-amber-950',
                    2 => 'bg-slate-300 text-slate-900',
                    3 => 'bg-orange-300 text-orange-950',
                    default => 'bg-slate-900 text-white',
                };
            @endphp
            <article class="flex min-w-0 items-center justify-between gap-3 rounded-2xl border p-4 shadow-sm {{ $currentStudentId && $entry['Student_ID'] === $currentStudentId ? 'border-sky-300 bg-sky-50' : ($topThree ? 'border-amber-200 bg-white' : 'border-slate-200 bg-white') }}">
                <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg font-black {{ $rankStyle }}">{{ $entry['Rank'] }}</span>
                    <div class="min-w-0">
                        <h3 class="break-words font-black text-slate-900">{{ $entry['Student_Name'] }}</h3>
                        <p class="text-xs text-slate-500">
                            {{ $entry['Quiz_Count'] ?? 0 }} kuis
                            @if($currentStudentId && $entry['Student_ID'] === $currentStudentId)
                                &middot; Posisi Anda
                            @endif
                        </p>
                    </div>
                </div>
                <strong class="shrink-0 text-xl text-slate-900 sm:text-2xl">{{ (float) $entry['Points'] }} poin</strong>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">Belum ada hasil pada periode ini.</p>
        @endforelse
    </div>
</div>
@endsection
