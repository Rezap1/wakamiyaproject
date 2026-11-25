@extends('layouts.app')
@section('header', 'Kuis Latihan')
@section('content')
<div class="mx-auto max-w-6xl space-y-6 pb-24">
    <div class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-sky-700 to-indigo-700 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="text-2xl font-black">Katalog Kuis Latihan</h1><p class="mt-1 text-sm text-sky-100">Latihan permanen untuk seluruh siswa. Hasil tidak masuk nilai akademik atau ranking.</p></div>
        <a href="{{ route('teacher.practice-quizzes.create') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-5 font-black text-sky-800">Buat Kuis Latihan</a>
    </div>
    <div class="flex flex-col gap-3 sm:flex-row">
        <form class="flex flex-1 gap-2"><input name="q" value="{{ request('q') }}" placeholder="Cari judul latihan..." class="min-h-12 flex-1 rounded-xl border-slate-300"><button class="rounded-xl bg-slate-900 px-5 font-bold text-white">Cari</button></form>
        <a href="{{ route('teacher.quizzes.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 font-bold">Kuis Reguler</a>
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($practices as $practice)
            <article class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3"><h2 class="text-lg font-black">{{ $practice->Title }}</h2><span class="rounded-full px-2 py-1 text-xs font-black {{ $practice->Status === 'PUBLISHED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $practice->Status === 'PUBLISHED' ? 'Terbit' : 'Draft' }}</span></div>
                <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $practice->Description ?: 'Tanpa deskripsi.' }}</p>
                <p class="mt-4 text-xs font-bold text-slate-500">{{ $practice->Duration_Minutes }} menit</p>
                <a href="{{ route('teacher.practice-quizzes.show', $practice->Practice_Quiz_ID) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-sky-600 font-black text-white">Lihat Detail</a>
            </article>
        @empty
            <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">Belum ada kuis latihan.</div>
        @endforelse
    </div>
    {{ $practices->links() }}
</div>
@endsection
