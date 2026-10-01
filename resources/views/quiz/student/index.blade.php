@extends('layouts.app')
@section('header', 'Kuis Saya')
@section('content')
<div class="mx-auto max-w-4xl space-y-7 pb-28">
    <div class="flex items-center justify-between"><div><h2 class="text-2xl font-black">Kuis Saya</h2><p class="text-sm text-slate-600">Kerjakan sesuai waktu yang tersedia.</p></div><a href="{{ route('student.quizzes.leaderboard') }}" class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-black text-amber-800">Peringkat Kelas</a></div>
    @foreach(['today'=>'Kuis Hari Ini','upcoming'=>'Akan Datang','completed'=>'Selesai'] as $key=>$label)<section aria-labelledby="student-quiz-{{ $key }}"><h3 id="student-quiz-{{ $key }}" class="mb-3 text-lg font-black">{{ $label }}</h3><div class="space-y-3">
        @forelse($$key as $quiz)<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-start justify-between gap-3"><div><h4 class="text-lg font-black">{{ $quiz['Title'] }}</h4><p class="mt-1 text-xs text-slate-500">{{ $quiz['Start_At'] }} – {{ $quiz['End_At'] }} WIB</p></div><span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-black text-sky-700">{{ $quiz['Result_ID'] ? 'SELESAI' : ($quiz['Attempt_ID'] ? 'SEDANG DIKERJAKAN' : $quiz['Lifecycle']) }}</span></div><div class="mt-4 flex items-center justify-between"><p class="text-sm text-slate-600">{{ $quiz['Duration_Minutes'] }} menit · {{ $quiz['Question_Count'] }} soal</p>
            @if($quiz['Result_ID'])<a href="{{ route('student.quizzes.result',$quiz['Result_ID']) }}" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white">Review · {{ (float) $quiz['Raw_Score'] }} / {{ (float) $quiz['Maximum_Score'] }} poin</a>
            @elseif($quiz['Attempt_ID'])<a href="{{ route('student.quizzes.play',$quiz['Attempt_ID']) }}" class="rounded-xl bg-sky-600 px-4 py-3 text-sm font-black text-white">Lanjutkan</a>
            @elseif($quiz['Lifecycle']==='ACTIVE')<form method="POST" action="{{ route('student.quizzes.start',$quiz['Quiz_ID']) }}">@csrf<button class="rounded-xl bg-sky-600 px-4 py-3 text-sm font-black text-white">Mulai Kuis</button></form>@endif
        </div></article>@empty<p class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500">Tidak ada {{ strtolower($label) }}.</p>@endforelse
    </div></section>@endforeach
</div>
@endsection
