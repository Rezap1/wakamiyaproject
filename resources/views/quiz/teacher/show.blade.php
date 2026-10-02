@extends('layouts.app')
@section('header', 'Detail Kuis')
@section('content')
<div x-data="{ deleteOpen: false }" x-effect="document.body.classList.toggle('overflow-hidden', deleteOpen)" class="mx-auto max-w-5xl space-y-5 pb-28 md:pb-10">
    <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:justify-between">
            <div class="min-w-0"><p class="break-words text-sm font-bold text-sky-600">{{ $className ?? 'Kelas Anda' }}</p><h2 class="break-words text-2xl font-black">{{ $quiz['Title'] }}</h2><p class="mt-2 break-words text-sm text-slate-500">{{ $quiz['Start_At'] }} &ndash; {{ $quiz['End_At'] }} WIB &middot; {{ $quiz['Duration_Minutes'] }} menit</p></div>
            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">@if($quiz['Editable'])<a href="{{ route('teacher.quizzes.edit', $quiz['Quiz_ID']) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-slate-900 px-4 font-bold text-white">Edit Kuis</a>@endif<button type="button" @click="deleteOpen = true" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-rose-300 bg-rose-50 px-4 font-bold text-rose-700">Hapus Kuis</button></div>
        </div>
    </section>
    <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6"><h3 class="font-black">Soal &amp; Kunci (khusus pengajar)</h3><ol class="mt-4 space-y-4">@foreach($quiz['questions'] as $q)<li class="min-w-0 rounded-xl border border-slate-200 p-4"><p class="break-words font-bold">{{ $loop->iteration }}. {{ $q['Question_Text'] }}</p><p class="mt-2 break-words text-sm text-slate-600">Kunci: <strong>{{ $q['Correct_Option'] }}</strong> &middot; {{ (float) $q['Point'] }} poin</p></li>@endforeach</ol></section>
    <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6"><h3 class="font-black">Hasil Peserta</h3><div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="p-2">Siswa</th><th class="p-2">Poin</th><th class="p-2">Selesai</th></tr></thead><tbody>@forelse($quiz['results'] as $result)<tr class="border-t"><td class="break-words p-2 font-bold">{{ $result['Student_Name'] }}</td><td class="whitespace-nowrap p-2">{{ (float) $result['Raw_Score'] }} / {{ (float) $result['Maximum_Score'] }} poin</td><td class="whitespace-nowrap p-2">{{ $result['Completed_At'] }} WIB</td></tr>@empty<tr><td colspan="3" class="p-4 text-center text-slate-500">Belum ada hasil.</td></tr>@endforelse</tbody></table></div></section>

    <div x-show="deleteOpen" x-cloak @keydown.escape.window="deleteOpen = false" class="fixed inset-0 z-[80] flex items-end justify-center overflow-y-auto overscroll-contain bg-slate-950/60 p-3 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="delete-quiz-title">
        <section x-show="deleteOpen" x-transition @click.outside="deleteOpen = false" class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-lg flex-col overflow-hidden rounded-3xl bg-white shadow-2xl sm:max-h-[calc(100dvh-3rem)]">
            <div class="min-h-0 overflow-y-auto overscroll-contain p-5 sm:p-7">
                <p class="text-xs font-black uppercase tracking-widest text-rose-700">Tindakan permanen</p><h2 id="delete-quiz-title" class="mt-2 break-words text-2xl font-black text-slate-900">Hapus kuis ini?</h2>
                <p class="mt-4 break-words text-sm leading-6 text-slate-700">Semua soal, percobaan pengerjaan, hasil/nilai siswa, dan poin leaderboard dari kuis ini akan dihapus permanen.</p><p class="mt-3 rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-800">Data yang sudah dihapus tidak dapat dikembalikan.</p>
            </div>
            <div class="sticky bottom-0 z-10 grid shrink-0 grid-cols-1 gap-2 border-t border-slate-200 bg-white p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:grid-cols-2 sm:p-5"><button type="button" @click="deleteOpen = false" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 font-bold text-slate-700">Batal</button><form method="POST" action="{{ route('teacher.quizzes.destroy', $quiz['Quiz_ID']) }}">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-rose-600 px-4 font-black text-white">Hapus Permanen</button></form></div>
        </section>
    </div>
</div>
@endsection
