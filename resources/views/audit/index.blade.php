@extends('layouts.app')
@section('header', 'Jejak Audit')
@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Rekam Jejak</h2>
            <p class="text-slate-500 text-sm mt-1">Aktivitas sistem dan log keamanan.</p>
        </div>
        <div class="flex gap-2">
            <x-universal.multi-export route-prefix="audit" />
            <a href="{{ route('audit.statistics') }}" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                Statistik
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-bold text-slate-700">Aktivitas Terbaru</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white text-xs uppercase tracking-wider text-slate-500 border-b border-slate-100">
                        <th class="p-4 font-bold">Stempel Waktu</th>
                        <th class="p-4 font-bold">Pengguna</th>
                        <th class="p-4 font-bold">Modul / Aksi</th>
                        <th class="p-4 font-bold">Referensi</th>
                        <th class="p-4 font-bold">Klien / IP</th>
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
                            <span class="font-bold text-slate-800">{{ $log['User_ID'] ?? 'Sistem' }}</span><br>
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
                        <td colspan="6" class="p-8 text-center text-slate-400 text-sm">Tidak ada log audit yang ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection



