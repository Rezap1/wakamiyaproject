<?php
$dirWf = 'resources/views/workflow';
if(!is_dir($dirWf)) mkdir($dirWf, 0755, true);

$index = <<<'EOT'
@extends('layouts.app')
@section('header', 'Approval Inbox')
@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-page-header title="Approval Inbox" description="Review and process requests requiring your authorization." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Approvals' => '#']" />
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="divide-y divide-slate-50">
            @forelse($approvals as $app)
                @php
                    $priority = $app['Priority'] ?? 'Normal';
                    $priorityBadge = 'bg-slate-100 text-slate-500';
                    if($priority == 'High') $priorityBadge = 'bg-amber-100 text-amber-700';
                    if($priority == 'Critical') $priorityBadge = 'bg-rose-100 text-rose-700';
                @endphp
                <div class="bg-white p-6 hover:bg-slate-50 transition-colors flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex-grow">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                            <span class="text-[10px] font-bold uppercase tracking-widest {{ $priorityBadge }} px-2 py-0.5 rounded">{{ $priority }}</span>
                            <span class="text-[10px] font-bold bg-blue-50 text-blue-600 px-2 py-0.5 rounded uppercase tracking-widest">{{ $app['Reference_Type'] ?? 'Document' }}</span>
                            <span class="text-[11px] font-bold text-slate-400 ml-auto md:ml-2">Submitted {{ \Carbon\Carbon::parse($app['Submitted_At'] ?? now())->diffForHumans() }}</span>
                        </div>
                        <h4 class="font-bold text-slate-800 text-lg">Request: {{ $app['Reference_ID'] ?? 'Unknown' }}</h4>
                        <p class="text-sm text-slate-500 mt-1">Requested by: <span class="font-bold text-slate-700">{{ $app['Requester_ID'] ?? '-' }}</span></p>
                    </div>
                    <div class="flex flex-row md:flex-col gap-2 shrink-0 md:w-32">
                        <a href="{{ route('approvals.show', $app['Approval_ID']) }}" class="w-full text-center px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm">Review</a>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <h3 class="text-slate-500 font-bold">All clear!</h3>
                    <p class="text-sm text-slate-400 mt-1">You have no pending approvals.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirWf/index.blade.php", $index);

$show = <<<'EOT'
@extends('layouts.app')
@section('header', 'Approval Detail')
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <x-page-header title="Approval Details" description="Review the request before taking action." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Approvals' => route('approvals.index'), 'Details' => '#']" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h3 class="font-bold text-slate-800">Request Information</h3>
                    <span class="px-2 py-1 bg-amber-50 text-amber-600 text-xs font-bold rounded uppercase tracking-widest">{{ $approval['Status'] ?? 'Waiting' }}</span>
                </div>
                
                <table class="w-full text-sm">
                    <tbody>
                        <tr>
                            <td class="py-2 text-slate-500 w-1/3">Reference Type</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Reference_Type'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-500">Reference ID</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Reference_ID'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-500">Requester</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Requester_ID'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-500">Priority</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Priority'] ?? 'Normal' }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-500">Submitted At</td>
                            <td class="py-2 font-bold text-slate-800">{{ $approval['Submitted_At'] ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Approval History -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="font-bold text-slate-800 mb-6">Approval History</h3>
                <div class="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 before:to-transparent">
                    @forelse($history as $h)
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-slate-100 text-slate-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-slate-100 bg-white shadow-sm">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-800 text-sm">{{ $h['Action'] ?? 'Update' }}</span>
                                <span class="text-[10px] font-bold text-slate-400">{{ \Carbon\Carbon::parse($h['Created_At'])->format('d M H:i') }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Status changed from <span class="font-semibold">{{ $h['Old_Status'] ?? '-' }}</span> to <span class="font-semibold">{{ $h['New_Status'] ?? '-' }}</span></p>
                            <p class="text-xs text-slate-500 mt-1">By: <span class="font-semibold">{{ $h['Performed_By'] ?? '-' }}</span></p>
                            @if(!empty($h['Remarks']))
                                <div class="mt-2 p-2 bg-slate-50 rounded text-xs italic text-slate-600 border border-slate-100">"{{ $h['Remarks'] }}"</div>
                            @endif
                        </div>
                    </div>
                    @empty
                        <p class="text-sm text-slate-500 text-center relative z-10 bg-white inline-block px-4 mx-auto w-max left-1/2 -translate-x-1/2">No history recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar / Actions -->
        <div class="space-y-6">
            @if(($approval['Status'] ?? '') === 'Waiting Approval' && ($approval['Current_Approver'] ?? '') === (session('role') ?? 'GUEST'))
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="font-bold text-slate-800 mb-4">Action Required</h3>
                
                <form action="{{ route('approvals.approve', $approval['Approval_ID']) }}" method="POST" class="mb-3">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Remarks (Optional)</label>
                        <textarea name="remarks" rows="2" class="w-full text-sm rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500" placeholder="Add approval notes..."></textarea>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 font-bold rounded-xl text-sm border border-emerald-200 transition-colors shadow-sm" onsubmit="return confirm('Approve this request?');">Approve Request</button>
                </form>

                <form action="{{ route('approvals.reject', $approval['Approval_ID']) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Rejection Reason</label>
                        <textarea name="remarks" rows="2" class="w-full text-sm rounded-lg border-slate-200 focus:border-red-500 focus:ring-red-500" placeholder="Why is this rejected?" required></textarea>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-white text-red-600 hover:bg-red-50 font-bold rounded-xl text-sm border border-red-200 transition-colors" onsubmit="return confirm('Reject this request?');">Reject Request</button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirWf/show.blade.php", $show);

echo "Workflow Views created.\n";
?>
