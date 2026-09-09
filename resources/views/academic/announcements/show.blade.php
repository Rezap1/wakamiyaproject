@extends('layouts.app')

@section('header', 'Detail Pengumuman')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        title="Pratinjau Pengumuman"
        description="Tampilan yang akan dibaca penerima pengumuman."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Pengumuman' => route('announcements.index'), 'Detail' => '#']"
    />
    @php
        $priority = $announcement['Priority_Label'] ?? 'Informasi';
        $priorityClass = $priority === 'Mendesak' ? 'bg-rose-100 text-rose-700' : ($priority === 'Penting' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700');
    @endphp
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $priorityClass }}">{{ $priority }}</span>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $announcement['Audience_Label'] ?? 'Semua Siswa' }}</span>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $announcement['Status_Label'] ?? 'Aktif' }}</span>
        </div>
        <h1 class="mt-5 break-words text-2xl font-black leading-tight text-slate-900">{{ $announcement['Title'] ?? 'Pengumuman' }}</h1>
        <div class="mt-5 whitespace-pre-line break-words text-base leading-7 text-slate-700">{{ $announcement['Message'] ?? $announcement['Content'] ?? '' }}</div>
        <dl class="mt-8 grid gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-slate-500">Mulai tayang</dt><dd class="mt-1 text-slate-800">{{ $announcement['Start_Label'] ?? '-' }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Berakhir</dt><dd class="mt-1 text-slate-800">{{ $announcement['Expiry_Label'] ?? '-' }}</dd></div>
        </dl>
        <div class="mt-6 flex flex-wrap gap-3">
            <x-button as="a" href="{{ route('announcements.edit', $announcement['Announcement_ID']) }}" variant="primary">Edit Pengumuman</x-button>
            <x-button as="a" href="{{ route('announcements.index') }}" variant="secondary">Kembali</x-button>
        </div>
    </article>
</div>
@endsection
