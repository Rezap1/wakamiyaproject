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
        <div class="mt-2 flex min-w-0 items-start gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-400 text-amber-950 shadow-sm" aria-hidden="true"><x-sidebar.icon name="trophy" class="h-7 w-7" /></span>
            <div class="min-w-0">
                <p class="text-xs font-black uppercase tracking-widest text-amber-700">Peringkat Kuis</p>
                <h2 class="mt-1 break-words text-2xl font-black text-slate-900">{{ $displayClass }}</h2>
                <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-600"><x-sidebar.icon name="calendar" class="h-4 w-4 shrink-0" /> Periode {{ $period['label'] }}</p>
            </div>
        </div>

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
                $rank = (int) $entry['Rank'];
                $topThree = $rank <= 3;
                $rankStyle = match ($rank) {
                    1 => 'bg-amber-400 text-amber-950',
                    2 => 'bg-slate-300 text-slate-900',
                    3 => 'bg-orange-300 text-orange-950',
                    default => 'bg-slate-900 text-white',
                };
                $cardStyle = match ($rank) {
                    1 => 'border-amber-300 bg-gradient-to-r from-amber-50 to-white ring-1 ring-amber-200',
                    2 => 'border-slate-300 bg-gradient-to-r from-slate-100 to-white',
                    3 => 'border-orange-200 bg-gradient-to-r from-orange-50 to-white',
                    default => 'border-slate-200 bg-white',
                };
                $isCurrent = $currentStudentId && $entry['Student_ID'] === $currentStudentId;
            @endphp
            <article class="flex min-w-0 items-center justify-between gap-3 rounded-2xl border p-4 shadow-sm {{ $isCurrent ? 'ring-2 ring-sky-400' : '' }} {{ $cardStyle }}">
                <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                    <span class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-base font-black {{ $rankStyle }}">
                        @if($topThree)<x-sidebar.icon name="trophy" class="absolute -right-1 -top-1 h-4 w-4 rounded-full bg-white/90 p-0.5" />@endif
                        #{{ $entry['Rank'] }}
                    </span>
                    <div class="min-w-0">
                        <div class="flex min-w-0 flex-wrap items-center gap-2"><h3 class="min-w-0 break-words font-black text-slate-900">{{ $entry['Student_Name'] }}</h3>@if($isCurrent)<span class="rounded-full bg-sky-600 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-white">Anda</span>@endif</div>
                        <p class="text-xs text-slate-500">{{ $entry['Quiz_Count'] ?? 0 }} kuis</p>
                    </div>
                </div>
                <strong class="flex shrink-0 items-center gap-1 text-base text-slate-900 sm:text-xl"><x-sidebar.icon name="star" class="h-5 w-5 text-amber-500" /> {{ (float) $entry['Points'] }} poin</strong>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">Belum ada hasil pada periode ini.</p>
        @endforelse
    </div>
</div>
@endsection
