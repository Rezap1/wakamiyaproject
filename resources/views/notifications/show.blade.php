@extends('layouts.app')
@section('header', 'Detail Notifikasi')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <x-page-header title="Detail Notifikasi" description="Lihat detail pesan." :breadcrumbs="['Dasbor' => route('dashboard'), 'Notifikasi' => route('notifications.index'), 'Detail' => '#']" />

    <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
        <div class="flex items-center gap-3 mb-6">
            @php
                $priority = $notification['Priority'] ?? 'Normal';
                $priorityBadge = 'bg-slate-100 text-slate-500';
                if($priority == 'High') $priorityBadge = 'bg-amber-100 text-amber-700';
                if($priority == 'Critical') $priorityBadge = 'bg-rose-100 text-rose-700';
            @endphp
            <span class="text-xs font-bold {{ $priorityBadge }} px-2 py-1 rounded">{{ $priority }}</span>
            <span class="text-xs font-bold bg-blue-50 text-blue-600 px-2 py-1 rounded">{{ $notification['Notification_Type'] ?? 'System' }}</span>
            <span class="text-xs text-slate-400 ml-auto">{{ $notification['Created_At'] ?? '-' }}</span>
        </div>

        <h2 class="text-2xl font-black text-slate-800 mb-4">{{ $notification['Title'] ?? 'Notifikasi' }}</h2>
        <div class="prose prose-slate max-w-none text-slate-600 mb-8">
            {{ $notification['Message'] ?? '-' }}
        </div>

        @if(!empty($notification['Action_URL']))
            <a href="{{ $notification['Action_URL'] }}" class="inline-block px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl shadow-md hover:bg-emerald-700 transition-colors">Buka Tautan Aksi</a>
        @endif

        <div class="mt-12 pt-6 border-t border-slate-100 flex gap-4">
            <form action="{{ route('notifications.archive', $notification['Notification_ID']) }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 text-sm font-bold text-amber-600 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors">Arsip</button>
            </form>
            <form action="{{ route('notifications.destroy', $notification['Notification_ID']) }}" method="POST" onsubmit="return confirm('Hapus notifikasi ini?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-rose-600 bg-rose-50 rounded-lg hover:bg-rose-100 transition-colors">Hapus</button>
            </form>
        </div>
    </div>
</div>
@endsection



