<?php
$dirAudit = 'resources/views/audit';
if(!is_dir($dirAudit)) mkdir($dirAudit, 0755, true);

$index = <<<'EOT'
@extends('layouts.app')
@section('header', 'Audit Trail')
@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Audit Trail</h2>
            <p class="text-slate-500 text-sm mt-1">System activity and security logs.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('audit.statistics') }}" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                Statistics
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-bold text-slate-700">Recent Activities</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white text-xs uppercase tracking-wider text-slate-500 border-b border-slate-100">
                        <th class="p-4 font-bold">Timestamp</th>
                        <th class="p-4 font-bold">User</th>
                        <th class="p-4 font-bold">Module / Action</th>
                        <th class="p-4 font-bold">Reference</th>
                        <th class="p-4 font-bold">Client / IP</th>
                        <th class="p-4 font-bold w-10"></th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-50">
                    @forelse($logs as $log)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="p-4 whitespace-nowrap">
                            <span class="font-semibold text-slate-700">{{ \Carbon\Carbon::parse($log['Created_At'])->format('d M Y') }}</span><br>
                            <span class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($log['Created_At'])->format('H:i:s') }}</span>
                        </td>
                        <td class="p-4">
                            <span class="font-bold text-slate-800">{{ $log['User_ID'] ?? 'System' }}</span><br>
                            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{{ $log['Role'] ?? '-' }}</span>
                        </td>
                        <td class="p-4">
                            <span class="text-xs font-bold px-2 py-0.5 bg-blue-50 text-blue-600 rounded">{{ $log['Module'] ?? '-' }}</span><br>
                            <span class="text-sm font-semibold text-slate-700 mt-1 block">{{ $log['Action'] ?? '-' }}</span>
                        </td>
                        <td class="p-4">
                            <span class="text-xs text-slate-500">{{ $log['Reference_Type'] ?? '-' }}</span><br>
                            <span class="font-bold text-slate-700">{{ $log['Reference_ID'] ?? '-' }}</span>
                        </td>
                        <td class="p-4">
                            <span class="text-xs text-slate-500">{{ $log['IPAddress'] ?? '-' }}</span><br>
                            <span class="text-[10px] font-semibold text-slate-400">{{ $log['Browser'] ?? '-' }} &middot; {{ $log['Operating_System'] ?? '-' }}</span>
                        </td>
                        <td class="p-4 text-right">
                            <a href="{{ route('audit.show', $log['Audit_ID']) }}" class="text-slate-400 hover:text-blue-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400 text-sm">No audit logs found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirAudit/index.blade.php", $index);

$show = <<<'EOT'
@extends('layouts.app')
@section('header', 'Audit Details')
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <x-page-header title="Audit Details" description="Detailed trace of the activity log." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Audit Trail' => route('audit.index'), 'Details' => '#']" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Event Details -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2">Event Information</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Audit ID</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Audit_ID'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Timestamp</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Created_At'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Module</span>
                    <span class="font-semibold text-blue-600 text-sm">{{ $log['Module'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Action</span>
                    <span class="font-semibold text-slate-800 text-sm">{{ $log['Action'] }}</span>
                </div>
            </div>
            
            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 mt-6">Target Reference</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Type</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Reference_Type'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">ID</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Reference_ID'] }}</span>
                </div>
            </div>
        </div>

        <!-- Actor & Client -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2">Actor (User)</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <span class="block text-xs font-bold text-slate-400 uppercase">User ID (Email)</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['User_ID'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Role</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Role'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Department</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Department'] }}</span>
                </div>
            </div>

            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 mt-6">Client Device</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">IP Address</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['IPAddress'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Location</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Location'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">Browser</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Browser'] }}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase">OS</span>
                    <span class="font-semibold text-slate-700 text-sm">{{ $log['Operating_System'] }}</span>
                </div>
                <div class="col-span-2">
                    <span class="block text-xs font-bold text-slate-400 uppercase">User Agent</span>
                    <span class="font-mono text-slate-600 text-xs break-all mt-1 block bg-slate-50 p-2 rounded border border-slate-100">{{ $log['Device'] }}</span>
                </div>
            </div>
        </div>

        <!-- Data Changes -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 md:col-span-2">
            <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">Payload & Changes</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase mb-2">Old Value</span>
                    <pre class="bg-slate-50 p-4 rounded-xl text-xs text-slate-600 overflow-x-auto border border-slate-200 whitespace-pre-wrap">{{ empty($log['Old_Value']) ? 'null' : $log['Old_Value'] }}</pre>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-400 uppercase mb-2">New Value</span>
                    <pre class="bg-slate-50 p-4 rounded-xl text-xs text-slate-600 overflow-x-auto border border-slate-200 whitespace-pre-wrap">{{ empty($log['New_Value']) ? 'null' : $log['New_Value'] }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirAudit/show.blade.php", $show);

$statistics = <<<'EOT'
@extends('layouts.app')
@section('header', 'Audit Statistics')
@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <x-page-header title="Audit Statistics" description="Overview of system activities and event distributions." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Audit Trail' => route('audit.index'), 'Statistics' => '#']" />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-wider">Total Events</p>
                    <p class="text-2xl font-black text-slate-800">{{ $stats['total'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-wider">Events Today</p>
                    <p class="text-2xl font-black text-slate-800">{{ $stats['today'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Top Active Modules</h3>
            <div class="space-y-4">
                @foreach($stats['top_modules'] as $module => $count)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-bold text-slate-700">{{ $module ?: 'System' }}</span>
                        <span class="font-bold text-slate-500">{{ $count }} events</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(($count / max(1, $stats['total'])) * 100, 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Top Active Users</h3>
            <div class="space-y-4">
                @foreach($stats['top_users'] as $user => $count)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-bold text-slate-700">{{ $user ?: 'System' }}</span>
                        <span class="font-bold text-slate-500">{{ $count }} events</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ min(($count / max(1, $stats['total'])) * 100, 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirAudit/statistics.blade.php", $statistics);

echo "Audit Views created.\n";
?>
