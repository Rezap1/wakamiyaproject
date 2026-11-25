@extends('layouts.app')
@section('header', 'Detail Kuis Latihan')
@section('content')
<div class="mx-auto max-w-5xl space-y-6 pb-24">
    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>@endif
    <section class="rounded-2xl bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div><span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-black text-sky-800">{{ $revision['Status'] === 'PUBLISHED' ? 'TERBIT' : 'DRAFT' }} · REV {{ $revision['Revision_Number'] }}</span><h1 class="mt-3 text-2xl font-black">{{ $revision['Title'] }}</h1><p class="mt-2 whitespace-pre-line text-slate-600">{{ $revision['Description'] }}</p></div>
            <div class="flex gap-2"><a href="{{ route('teacher.practice-quizzes.edit', $quiz['Practice_Quiz_ID']) }}" class="inline-flex min-h-11 items-center rounded-xl bg-sky-600 px-4 font-bold text-white">Edit</a></div>
        </div>
        @if($quiz['Draft_Revision_ID'] && $quiz['Status'] === 'PUBLISHED')<p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm font-bold text-amber-800">Ada draft perubahan. Siswa tetap memakai revision published sampai draft diterbitkan.</p>@endif
        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm"><div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Durasi</dt><dd class="font-black">{{ $revision['Duration_Minutes'] }} menit</dd></div><div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Jumlah soal</dt><dd class="font-black">{{ $revision['Question_Count'] }}</dd></div></dl>
        @if($revision['Instructions'])<div class="mt-4 rounded-xl border border-slate-200 p-4"><h2 class="font-black">Instruksi</h2><p class="mt-2 whitespace-pre-line text-sm">{{ $revision['Instructions'] }}</p></div>@endif
    </section>
    <div class="space-y-4">@foreach($questions as $question)<article class="rounded-2xl bg-white p-5 shadow-sm"><h2 class="font-black">Soal {{ $loop->iteration }}</h2><p class="mt-2 whitespace-pre-line">{{ $question['Question_Text'] }}</p>@if($question['Image_URL'])<img src="{{ $question['Image_URL'] }}" alt="Gambar soal" class="mt-4 max-h-80 w-full rounded-xl object-contain">@endif @if($question['Audio_URL'])<audio src="{{ $question['Audio_URL'] }}" controls preload="metadata" class="mt-4 w-full"></audio>@endif<div class="mt-4 grid gap-2 sm:grid-cols-2">@foreach(['A','B','C','D'] as $letter)<div class="rounded-xl border p-3 {{ $question['Correct_Option'] === $letter ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200' }}"><strong>{{ $letter }}.</strong> {{ $question['Option_'.$letter] }}</div>@endforeach</div><p class="mt-3 text-sm font-bold">Bobot: {{ $question['Point'] }}</p>@if($question['Explanation'])<div class="mt-3 rounded-xl bg-slate-50 p-3 text-sm"><strong>Pembahasan:</strong> {{ $question['Explanation'] }}</div>@endif</article>@endforeach</div>
    <form method="POST" action="{{ route('teacher.practice-quizzes.destroy', $quiz['Practice_Quiz_ID']) }}" onsubmit="return confirm('Arsipkan latihan ini? Siswa tidak dapat memulai attempt baru, tetapi attempt aktif, histori, PDF, revision, dan media lama tetap dipertahankan.')">@csrf @method('DELETE')<button class="min-h-11 rounded-xl border border-rose-300 px-4 font-bold text-rose-700">Arsipkan Kuis Latihan</button></form>
</div>
@endsection
