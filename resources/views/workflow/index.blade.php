@extends('layouts.app')
@section('header', 'Kotak Masuk Persetujuan')
@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-page-header title="Kotak Masuk Persetujuan" description="Tinjau dan proses permintaan yang memerlukan otorisasi Anda." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Persetujuan' => '#']" />
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="divide-y divide-slate-50">
            @forelse($approvals as $app)
                @php
                    $priority = $app['Priority'] ?? 'Normal';
                    $priorityBadge = 'bg-slate-100 text-slate-500';
                    if($priority == 'Tinggi' || $priority == 'High') $priorityBadge = 'bg-amber-100 text-amber-700';
                    if($priority == 'Kritis' || $priority == 'Critical') $priorityBadge = 'bg-rose-100 text-rose-700';
                @endphp
                <div class="bg-white p-6 hover:bg-slate-50 transition-colors flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex-grow">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                            <span class="text-[10px] font-bold uppercase tracking-widest {{ $priorityBadge }} px-2 py-0.5 rounded">{{ $priority }}</span>
                            <span class="text-[10px] font-bold bg-blue-50 text-blue-600 px-2 py-0.5 rounded uppercase tracking-widest">{{ $app['Reference_Type'] ?? 'Dokumen' }}</span>
                            <span class="text-[11px] font-bold text-slate-400 ml-auto md:ml-2">Diajukan {{ \Carbon\Carbon::parse($app['Submitted_At'] ?? now())->diffForHumans() }}</span>
                        </div>
                        <h4 class="font-bold text-slate-800 text-lg">Permintaan: {{ $app['Reference_ID'] ?? 'Tidak Diketahui' }}</h4>
                        <p class="text-sm text-slate-500 mt-1">Diminta oleh: <span class="font-bold text-slate-700">{{ $app['Requester_ID'] ?? '-' }}</span></p>
                    </div>
                    <div class="flex flex-row md:flex-col gap-2 shrink-0 md:w-32">
                        <a href="{{ route('approvals.show', $app['Approval_ID']) }}" class="w-full text-center px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors shadow-sm">Tinjau</a>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <h3 class="text-slate-500 font-bold">Semua bersih!</h3>
                    <p class="text-sm text-slate-400 mt-1">Anda tidak memiliki persetujuan yang tertunda.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection



