@extends('layouts.app')
@section('header', 'Pusat Notifikasi')
@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-page-header title="Pusat Notifikasi" description="Lihat dan kelola semua notifikasi Anda." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Notifikasi' => '#']">
        <x-slot:actions>
            <form action="{{ route('notifications.markAllRead') }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl shadow-sm hover:bg-slate-50">Tandai Semua Telah Dibaca</button>
            </form>
        </x-slot:actions>
    </x-page-header>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="divide-y divide-slate-50">
            @forelse($notifications as $notif)
                @php
                    $isRead = ($notif['Is_Read'] ?? 'FALSE') === 'TRUE';
                    $bgClass = $isRead ? 'bg-white' : 'bg-blue-50/50';
                    $priority = $notif['Priority'] ?? 'Normal';
                    $priorityBadge = 'bg-slate-100 text-slate-500';
                    if($priority == 'High') $priorityBadge = 'bg-amber-100 text-amber-700';
                    if($priority == 'Critical') $priorityBadge = 'bg-rose-100 text-rose-700';
                @endphp
                <div class="{{ $bgClass }} p-6 hover:bg-slate-50 transition-colors flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex-grow">
                        <div class="flex items-center gap-2 mb-2">
                            @if(!$isRead)
                                <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                            @endif
                            <span class="text-xs font-bold {{ $priorityBadge }} px-2 py-0.5 rounded">{{ $priority }}</span>
                            <span class="text-xs font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded">{{ $notif['Notification_Type'] ?? 'System' }}</span>
                            <span class="text-xs text-slate-400 ml-auto md:ml-2">{{ \Carbon\Carbon::parse($notif['Created_At'] ?? now())->diffForHumans() }}</span>
                        </div>
                        <h4 class="font-bold text-slate-800 {{ $isRead ? '' : 'text-blue-900' }}">{{ $notif['Title'] ?? 'Notifikasi' }}</h4>
                        <p class="text-sm text-slate-500 mt-1 line-clamp-1">{{ $notif['Message'] ?? '' }}</p>
                    </div>
                    <div class="flex flex-row md:flex-col gap-2 shrink-0 md:w-32">
                        <a href="{{ route('notifications.show', $notif['Notification_ID']) }}" class="w-full text-center px-4 py-2 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">Detail</a>
                        @if(!$isRead)
                        <form action="{{ route('notifications.markRead', $notif['Notification_ID']) }}" method="POST" class="w-full">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors">Tandai Dibaca</button>
                        </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <h3 class="text-slate-500 font-bold">Anda sudah membaca semuanya!</h3>
                    <p class="text-sm text-slate-400 mt-1">Tidak ada notifikasi baru.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection



