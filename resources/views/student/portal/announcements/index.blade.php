@extends('layouts.app')

@section('header', 'Pengumuman')

@section('content')
<div class="space-y-6">
    <x-page-header title="Pengumuman" description="Informasi aktif yang ditujukan untuk Anda." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Pengumuman' => '#']" />
    @if($announcements->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">Belum ada pengumuman aktif untuk Anda.</div>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($announcements as $announcement)
                @php $priority = $announcement['Priority_Label'] ?? 'Informasi'; $tone = $priority === 'Mendesak' ? 'border-rose-300 bg-rose-50' : ($priority === 'Penting' ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white'); @endphp
                <article class="min-w-0 rounded-2xl border p-5 shadow-sm {{ $tone }}">
                    <span class="rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-bold text-slate-700">{{ $priority }}</span>
                    <h2 class="mt-3 break-words text-lg font-black text-slate-900">{{ $announcement['Title'] ?? 'Pengumuman' }}</h2>
                    <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $announcement['Message'] ?? $announcement['Content'] ?? '' }}</p>
                    <p class="mt-4 text-xs font-semibold text-slate-600">Berakhir: {{ $announcement['Expires_At_Label'] ?? '-' }}</p>
                    <a href="{{ route('student.portal.announcements.show', $announcement['Announcement_ID']) }}" class="mt-3 inline-flex text-sm font-bold text-emerald-700 hover:underline">Lihat Detail</a>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
