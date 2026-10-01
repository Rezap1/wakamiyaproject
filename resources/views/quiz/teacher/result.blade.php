@extends('layouts.app')
@section('header', 'Detail Hasil Kuis')
@section('content')
<div class="mx-auto max-w-3xl space-y-5 pb-28 md:pb-10">
    <a href="{{ route('teacher.quizzes.results', ['class' => $result['Class_ID']]) }}" class="inline-flex min-h-11 items-center text-sm font-black text-sky-700">← Kembali ke hasil kelas</a>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <p class="text-xs font-black uppercase tracking-widest text-sky-700">{{ $result['Quiz_Title'] ?? 'Kuis' }}</p>
        <h2 class="mt-2 break-words text-2xl font-black text-slate-900">{{ $result['Student_Name'] ?? 'Siswa' }}</h2>
        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-emerald-50 p-4"><p class="text-xs font-bold text-emerald-700">Nilai Normalisasi</p><strong class="mt-1 block text-3xl text-emerald-900">{{ $result['Normalized_Score'] }}</strong></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">Poin</p><strong class="mt-1 block text-xl text-slate-900">{{ $result['Raw_Score'] }} / {{ $result['Maximum_Score'] }}</strong></div>
            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">Status</p><strong class="mt-1 block text-lg text-slate-900">Selesai</strong></div>
        </div>
        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-bold text-slate-500">Kelas</dt><dd class="mt-1 font-black text-slate-900">{{ $result['Class_ID'] }}</dd></div>
            <div><dt class="font-bold text-slate-500">Waktu Selesai</dt><dd class="mt-1 font-black text-slate-900">{{ $result['Completed_At'] ?? '-' }} WIB</dd></div>
        </dl>
    </section>
</div>
@endsection
