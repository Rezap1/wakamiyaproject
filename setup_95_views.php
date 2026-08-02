<?php
$dirNotif = 'resources/views/notifications';
if(!is_dir($dirNotif)) mkdir($dirNotif, 0755, true);

$index = <<<'EOT'
@extends('layouts.app')
@section('header', 'Notification Center')
@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-page-header title="Notification Center" description="View and manage all your notifications." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Notifications' => '#']">
        <x-slot:actions>
            <form action="{{ route('notifications.markAllRead') }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl shadow-sm hover:bg-slate-50">Mark All as Read</button>
            </form>
        </x-slot:actions>
    </x-page-header>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
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
                                <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                            @endif
                            <span class="text-xs font-bold {{ $priorityBadge }} px-2 py-0.5 rounded">{{ $priority }}</span>
                            <span class="text-xs font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded">{{ $notif['Notification_Type'] ?? 'System' }}</span>
                            <span class="text-xs text-slate-400 ml-auto md:ml-2">{{ \Carbon\Carbon::parse($notif['Created_At'] ?? now())->diffForHumans() }}</span>
                        </div>
                        <h4 class="font-bold text-slate-800 {{ $isRead ? '' : 'text-blue-900' }}">{{ $notif['Title'] ?? 'Notification' }}</h4>
                        <p class="text-sm text-slate-500 mt-1 line-clamp-1">{{ $notif['Message'] ?? '' }}</p>
                    </div>
                    <div class="flex flex-row md:flex-col gap-2 shrink-0 md:w-32">
                        <a href="{{ route('notifications.show', $notif['Notification_ID']) }}" class="w-full text-center px-4 py-2 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">Detail</a>
                        @if(!$isRead)
                        <form action="{{ route('notifications.markRead', $notif['Notification_ID']) }}" method="POST" class="w-full">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors">Mark Read</button>
                        </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <h3 class="text-slate-500 font-bold">You're all caught up!</h3>
                    <p class="text-sm text-slate-400 mt-1">No new notifications.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirNotif/index.blade.php", $index);

$show = <<<'EOT'
@extends('layouts.app')
@section('header', 'Notification Detail')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <x-page-header title="Notification Detail" description="View message details." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Notifications' => route('notifications.index'), 'Detail' => '#']" />

    <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
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
        
        <h2 class="text-2xl font-black text-slate-800 mb-4">{{ $notification['Title'] ?? 'Notification' }}</h2>
        <div class="prose prose-slate max-w-none text-slate-600 mb-8">
            {{ $notification['Message'] ?? '-' }}
        </div>

        @if(!empty($notification['Action_URL']))
            <a href="{{ $notification['Action_URL'] }}" class="inline-block px-6 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-md hover:bg-blue-700 transition-colors">Open Action Link</a>
        @endif
        
        <div class="mt-12 pt-6 border-t border-slate-100 flex gap-4">
            <form action="{{ route('notifications.archive', $notification['Notification_ID']) }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 text-sm font-bold text-amber-600 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors">Archive</button>
            </form>
            <form action="{{ route('notifications.destroy', $notification['Notification_ID']) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-rose-600 bg-rose-50 rounded-lg hover:bg-rose-100 transition-colors" onsubmit="return confirm('Delete this notification?');">Delete</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirNotif/show.blade.php", $show);

echo "Notification views created.\n";
?>
