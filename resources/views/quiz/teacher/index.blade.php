@extends('layouts.app')
@section('header', 'Kuis')
@section('content')
<div class="space-y-6 pb-24 md:pb-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="text-2xl font-black text-slate-900">Kuis Kelas</h2><p class="text-sm text-slate-600">Buat dan pantau kuis untuk kelas yang Anda ajar.</p></div>
        <div class="flex gap-2"><a href="{{ route('teacher.quizzes.results') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold">Hasil</a><a href="{{ route('teacher.quizzes.create') }}" class="rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white shadow-sm">+ Buat Kuis</a></div>
    </div>
    @foreach(['active' => 'Aktif', 'upcoming' => 'Akan Datang', 'completed' => 'Selesai'] as $key => $label)
        <section aria-labelledby="quiz-{{ $key }}"><h3 id="quiz-{{ $key }}" class="mb-3 text-lg font-black text-slate-900">{{ $label }}</h3>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse($groups[$key] as $quiz)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><h4 class="font-black text-slate-900">{{ $quiz['Title'] }}</h4><span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">{{ $quiz['Lifecycle'] }}</span></div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">Kelas</dt><dd class="font-bold">{{ $quiz['Class_ID'] }}</dd></div><div><dt class="text-slate-500">Durasi</dt><dd class="font-bold">{{ $quiz['Duration_Minutes'] }} menit</dd></div><div><dt class="text-slate-500">Soal</dt><dd class="font-bold">{{ $quiz['Question_Count'] }}</dd></div><div><dt class="text-slate-500">Peserta</dt><dd class="font-bold">{{ $quiz['Participant_Count'] }}</dd></div></dl>
                        <p class="mt-4 text-xs text-slate-500">{{ $quiz['Start_At'] }} – {{ $quiz['End_At'] }} WIB</p>
                        <div class="mt-4 flex flex-wrap gap-2"><a class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white" href="{{ route('teacher.quizzes.show', $quiz['Quiz_ID']) }}">Detail</a><a class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold" href="{{ route('teacher.quizzes.leaderboard', $quiz['Class_ID']) }}">Peringkat</a>@if($quiz['Editable'])<a class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold" href="{{ route('teacher.quizzes.edit', $quiz['Quiz_ID']) }}">Edit</a>@endif</div>
                    </article>
                @empty <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500 md:col-span-2 xl:col-span-3">Belum ada kuis {{ strtolower($label) }}.</p> @endforelse
            </div>
        </section>
    @endforeach
</div>
@endsection
