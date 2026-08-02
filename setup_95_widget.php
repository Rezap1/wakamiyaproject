<?php
$dirComp = 'resources/views/components/dashboard';
if(!is_dir($dirComp)) mkdir($dirComp, 0755, true);

$widget = <<<'EOT'
@php
    $userId = auth()->user()->email ?? (auth()->user()->User_ID ?? 'user@example.com');
    $userRole = session('role') ?? 'GUEST';
    $notifService = app(\App\Services\Core\NotificationService::class);
    $criticalNotif = $notifService->CriticalNotification($userId, $userRole);
    $recentNotifs = $notifService->RecentNotification($userId, $userRole, 5);
@endphp

<div class="space-y-6">
    @if($criticalNotif)
    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6 shadow-sm flex items-start gap-4">
        <div class="p-3 bg-rose-100 text-rose-600 rounded-xl shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div>
            <h3 class="font-bold text-rose-800 text-lg">{{ $criticalNotif['Title'] ?? 'Critical Alert' }}</h3>
            <p class="text-rose-700 text-sm mt-1">{{ $criticalNotif['Message'] ?? '' }}</p>
            <div class="mt-3 flex gap-3">
                @if(!empty($criticalNotif['Action_URL']))
                <a href="{{ $criticalNotif['Action_URL'] }}" class="text-sm font-bold text-white bg-rose-600 px-4 py-2 rounded-lg hover:bg-rose-700 shadow-sm">Take Action</a>
                @endif
                <form action="{{ route('notifications.markRead', $criticalNotif['Notification_ID']) }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm font-bold text-rose-700 bg-rose-100 px-4 py-2 rounded-lg hover:bg-rose-200 border border-rose-200">Dismiss</button>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-slate-800 text-lg">Recent Notifications</h3>
            <a href="{{ route('notifications.index') }}" class="text-sm font-bold text-blue-600 hover:underline">View All</a>
        </div>
        
        <div class="space-y-4">
            @forelse($recentNotifs as $notif)
                @php
                    $isRead = ($notif['Is_Read'] ?? 'FALSE') === 'TRUE';
                    $priority = $notif['Priority'] ?? 'Normal';
                    $priorityBadge = 'bg-slate-100 text-slate-500';
                    if($priority == 'High') $priorityBadge = 'bg-amber-100 text-amber-700';
                    if($priority == 'Critical') $priorityBadge = 'bg-rose-100 text-rose-700';
                @endphp
                <div class="flex items-start gap-4 p-4 rounded-xl {{ $isRead ? 'bg-slate-50' : 'bg-blue-50/50 border border-blue-100' }}">
                    <div class="flex-grow">
                        <div class="flex items-center gap-2 mb-1">
                            @if(!$isRead)
                                <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                            @endif
                            <span class="text-[10px] font-bold {{ $priorityBadge }} px-2 py-0.5 rounded">{{ $priority }}</span>
                            <span class="text-[10px] font-bold text-slate-500 bg-slate-200 px-2 py-0.5 rounded">{{ $notif['Notification_Type'] ?? 'System' }}</span>
                        </div>
                        <a href="{{ route('notifications.show', $notif['Notification_ID']) }}" class="font-bold text-slate-800 text-sm hover:text-blue-600 transition-colors {{ $isRead ? '' : 'text-blue-900' }}">{{ $notif['Title'] ?? 'Notification' }}</a>
                        <p class="text-xs text-slate-500 mt-1 line-clamp-1">{{ $notif['Message'] ?? '' }}</p>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-400 shrink-0">{{ \Carbon\Carbon::parse($notif['Created_At'])->diffForHumans(null, true, true) }} ago</span>
                </div>
            @empty
                <div class="p-8 text-center text-slate-400 bg-slate-50 rounded-xl border border-slate-100 border-dashed">
                    No recent notifications.
                </div>
            @endforelse
        </div>
    </div>
</div>
EOT;
file_put_contents("$dirComp/notification-widget.blade.php", $widget);

// Inject into dashboards
$dashboards = ['admin', 'hr', 'academic', 'finance', 'marketing', 'director', 'teacher', 'student'];
$dirDash = 'resources/views/dashboard';

foreach($dashboards as $dash) {
    $file = "$dirDash/$dash.blade.php";
    if(file_exists($file)) {
        $content = file_get_contents($file);
        if(strpos($content, '<x-dashboard.notification-widget />') === false) {
            // Find a good place to insert. Usually after the metrics grid.
            // Search for "grid grid-cols" or similar, and inject after that div closes.
            // A safer bet is just right after <x-page-header ... />
            
            $content = preg_replace(
                '/(<x-page-header[^>]*>.*?<\/x-page-header>)/s', 
                "$1\n\n    <!-- Notification Widget -->\n    <x-dashboard.notification-widget />", 
                $content, 
                1
            );
            
            file_put_contents($file, $content);
        }
    }
}

echo "Notification Widget created and injected.\n";
?>
