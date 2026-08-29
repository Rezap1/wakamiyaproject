@extends('layouts.app')

@section('header', 'Pengajuan Presensi')

@section('content')
<div class="w-full">
    <!-- Header -->
    <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-sky-400/20 mb-6 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between">
        <div>
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-300">🎓 PRESENSI</span>
            <h3 class="text-xl font-black tracking-tight text-white mt-1">Riwayat Pengajuan</h3>
            <p class="text-xs text-sky-200 mt-1">Sakit / Izin</p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="{{ route('student.attendance.requests.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-sky-500/30">
                + Ajukan Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl p-4 mb-6 text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session('error'))
        <div class="bg-rose-50 text-rose-700 border border-rose-200 rounded-xl p-4 mb-6 text-sm font-bold">
            {{ session('error') }}
        </div>
    @endif

    @if($requests->isEmpty())
        <!-- EMPTY STATE -->
        <div class="bg-white rounded-3xl p-8 text-center shadow-sm border border-slate-200">
            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-4xl">📝</span>
            </div>
            <h3 class="text-lg font-extrabold text-slate-800">Belum ada pengajuan</h3>
            <p class="text-sm text-slate-500 mt-2 max-w-md mx-auto">
                Anda belum pernah mengajukan ketidakhadiran (sakit/izin).
            </p>
        </div>
    @else
        <!-- MOBILE VIEW (CARD LIST) -->
        <div class="block md:hidden space-y-4">
            @foreach($requests as $req)
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 relative overflow-hidden">
                    @php
                        $bgColor = 'bg-amber-500';
                        $badgeClass = 'bg-amber-50 text-amber-600 border-amber-200';
                        if ($req['Status'] === 'APPROVED') {
                            $bgColor = 'bg-emerald-500';
                            $badgeClass = 'bg-emerald-50 text-emerald-600 border-emerald-200';
                        } elseif ($req['Status'] === 'REJECTED') {
                            $bgColor = 'bg-rose-500';
                            $badgeClass = 'bg-rose-50 text-rose-600 border-rose-200';
                        }
                    @endphp
                    <div class="absolute top-0 left-0 w-1.5 h-full {{ $bgColor }}"></div>
                    
                    <div class="flex justify-between items-start mb-3 pl-2">
                        <div>
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">TANGGAL PRESENSI</span>
                            <div class="text-lg font-black text-slate-800 mt-0.5">{{ $req['Attendance_Date'] }}</div>
                        </div>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $badgeClass }}">
                            {{ $req['Status'] }}
                        </span>
                    </div>
                    
                    <div class="bg-slate-50 rounded-xl p-3 pl-4 border border-slate-100 mt-2">
                        <div class="mb-2">
                            <span class="text-[10px] text-slate-500 block">Tipe Pengajuan</span>
                            <span class="text-sm font-bold text-indigo-700 block">{{ $req['Request_Type'] }}</span>
                        </div>
                        <div class="mb-2">
                            <span class="text-[10px] text-slate-500 block">Alasan</span>
                            <span class="text-xs font-semibold text-slate-700 block">{{ $req['Reason'] }}</span>
                        </div>
                        @if(!empty($req['Academic_Notes']))
                        <div class="mt-2 pt-2 border-t border-slate-200/60">
                            <span class="text-[10px] text-rose-500 block">Catatan Academic</span>
                            <span class="text-xs font-bold text-slate-700 block">{{ $req['Academic_Notes'] }}</span>
                        </div>
                        @endif
                    </div>
                    @if(!empty($req['Evidence_URL']))
                        <a href="{{ route('student.attendance.requests.evidence', $req['Request_ID']) }}" class="block text-center mt-3 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold py-2 rounded-xl transition-colors border border-blue-200">
                            Download Bukti
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- DESKTOP VIEW (TABLE) -->
        <div class="hidden md:block">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 font-extrabold">
                            <th class="p-4">Tanggal Presensi</th>
                            <th class="p-4">Tipe</th>
                            <th class="p-4">Alasan</th>
                            <th class="p-4">Catatan Academic</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-right">Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($requests as $req)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                <td class="p-4 font-bold text-slate-800">{{ $req['Attendance_Date'] }}</td>
                                <td class="p-4 font-extrabold text-indigo-600">{{ $req['Request_Type'] }}</td>
                                <td class="p-4 text-slate-600 max-w-xs truncate">{{ $req['Reason'] }}</td>
                                <td class="p-4 text-slate-600 text-xs">{{ $req['Academic_Notes'] ?? '-' }}</td>
                                <td class="p-4 text-center">
                                    @php
                                        $badgeClass = 'bg-amber-50 text-amber-600 border-amber-200';
                                        if ($req['Status'] === 'APPROVED') $badgeClass = 'bg-emerald-50 text-emerald-600 border-emerald-200';
                                        if ($req['Status'] === 'REJECTED') $badgeClass = 'bg-rose-50 text-rose-600 border-rose-200';
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold border {{ $badgeClass }}">
                                        {{ $req['Status'] }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    @if(!empty($req['Evidence_URL']))
                                        <a href="{{ route('student.attendance.requests.evidence', $req['Request_ID']) }}" class="inline-block bg-blue-50 text-blue-700 hover:bg-blue-100 font-bold text-xs px-3 py-1.5 rounded-lg border border-blue-200 transition-colors">
                                            Download
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
