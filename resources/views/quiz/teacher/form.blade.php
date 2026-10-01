@extends('layouts.app')
@section('header', $quiz ? 'Edit Kuis' : 'Buat Kuis')
@section('content')
@php $seed = old('questions', $questions->map(fn($q) => (array)$q)->values()->all()); @endphp
<form method="POST" action="{{ $quiz ? route('teacher.quizzes.update', $quiz['Quiz_ID']) : route('teacher.quizzes.store') }}" class="mx-auto max-w-5xl space-y-6 pb-24" x-data="quizBuilder(@js($seed))">
    @csrf @if($quiz) @method('PUT') @endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7"><h2 class="text-xl font-black">Informasi Kuis</h2>
        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
            <label class="sm:col-span-2 text-sm font-bold">Judul Kuis<input name="Title" value="{{ old('Title', $quiz['Title'] ?? '') }}" required maxlength="160" class="mt-2 min-h-12 w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"></label>
            <label class="text-sm font-bold">Kelas<select name="Class_ID" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300">@foreach($classes as $class)<option value="{{ $class['Class_ID'] }}" @selected(old('Class_ID', $quiz['Class_ID'] ?? '') === $class['Class_ID'])>{{ $class['Class_Name'] ?? $class['Class_ID'] }}</option>@endforeach</select></label>
            <label class="text-sm font-bold">Durasi (menit)<input type="number" min="1" max="480" name="Duration_Minutes" value="{{ old('Duration_Minutes', $quiz['Duration_Minutes'] ?? 30) }}" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-bold">Mulai<input type="datetime-local" name="Start_At" value="{{ old('Start_At', isset($quiz['Start_At']) ? str_replace(' ', 'T', substr($quiz['Start_At'], 0, 16)) : '') }}" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-bold">Selesai<input type="datetime-local" name="End_At" value="{{ old('End_At', isset($quiz['End_At']) ? str_replace(' ', 'T', substr($quiz['End_At'], 0, 16)) : '') }}" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300"></label>
        </div>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7"><div class="flex items-center justify-between"><div><h2 class="text-xl font-black">Soal A–D</h2><p class="text-sm text-slate-500">Skor maksimum: <strong x-text="maximum"></strong></p></div><button type="button" @click="add" class="rounded-xl bg-sky-50 px-4 py-2 font-bold text-sky-700">+ Soal</button></div>
        <div class="mt-5 space-y-5"><template x-for="(q, i) in questions" :key="q.key"><fieldset class="rounded-2xl border border-slate-200 p-4"><legend class="px-2 font-black" x-text="`Soal ${i+1}`"></legend>
            <textarea :name="`questions[${i}][Question_Text]`" x-model="q.Question_Text" required rows="3" class="w-full rounded-xl border-slate-300" placeholder="Tulis pertanyaan"></textarea>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2"><template x-for="letter in ['A','B','C','D']"><label class="text-sm font-bold" x-text="letter"><input :name="`questions[${i}][Option_${letter}]`" x-model="q[`Option_${letter}`]" required class="mt-1 min-h-11 w-full rounded-xl border-slate-300"></label></template></div>
            <div class="mt-3 grid grid-cols-2 gap-3"><label class="text-sm font-bold">Jawaban benar<select :name="`questions[${i}][Correct_Option]`" x-model="q.Correct_Option" class="mt-1 min-h-11 w-full rounded-xl border-slate-300"><option>A</option><option>B</option><option>C</option><option>D</option></select></label><label class="text-sm font-bold">Poin<input type="number" min="0.01" step="0.01" :name="`questions[${i}][Point]`" x-model.number="q.Point" required class="mt-1 min-h-11 w-full rounded-xl border-slate-300"></label></div>
            <button type="button" @click="remove(i)" class="mt-3 text-sm font-bold text-rose-600">Hapus soal</button>
        </fieldset></template></div>
    </section>
    <div class="sticky bottom-20 z-20 flex gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur md:bottom-4"><button name="intent" value="draft" class="min-h-12 flex-1 rounded-xl border border-slate-300 font-black">Simpan Draft</button><button name="intent" value="publish" class="min-h-12 flex-1 rounded-xl bg-sky-600 font-black text-white">Terbitkan</button></div>
</form>
@push('scripts')<script>function quizBuilder(seed){return{questions:seed.map((q,i)=>({...q,key:q.Question_ID||`${Date.now()}-${i}`})),get maximum(){return this.questions.reduce((n,q)=>n+(Number(q.Point)||0),0)},add(){this.questions.push({key:`${Date.now()}-${Math.random()}`,Question_Text:'',Option_A:'',Option_B:'',Option_C:'',Option_D:'',Correct_Option:'A',Point:10})},remove(i){this.questions.splice(i,1)}}}</script>@endpush
@endsection
